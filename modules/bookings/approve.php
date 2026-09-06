<?php

/**
 * SESH - Review (Approve / Reject) Booking (modules pathway)
 *
 * Purpose:
 *   Lets Admin or Coordinator approve or reject a Pending booking, after
 *   re-checking for scheduling conflicts immediately before approval.
 *
 * Requires tables:
 *   - bookings, rooms, users, programmes, semesters
 *
 * Access:
 *   The Permission Matrix has no dedicated "Approve/Reject Booking" row.
 *   The Permission Definitions describe "Full Access" as including
 *   "approve" among create/read/update/delete/manage, so approval could be
 *   read as already covered by Coordinator's Full Access on Book Regular
 *   Classes / Book Examination Rooms. This file does NOT use that mapping,
 *   for consistency with edit.php: approving, like editing, acts on a
 *   booking that already exists rather than creating a new one, and the
 *   Business Rules describe Coordinator's authority in general as
 *   "Department-level scheduling and management authority." So this file
 *   reuses FEATURE_BOOKINGS_CANCEL's tiers, exactly as edit.php does:
 *     Admin       - Any Booking (System Override — bypasses scope checks)
 *     Coordinator - Department Bookings (own programme_id only)
 *     Faculty, Student, Maintenance - No Access (Faculty was never
 *       permitted to approve/reject bookings, even under the original
 *       requireRole(['Admin', 'Coordinator']) gate; ACCESS_ASSIGNED is
 *       therefore deliberately excluded from the page-level gate below,
 *       not just left unscoped, since Faculty holding write access to
 *       Cancel Bookings for their own bookings does not imply they should
 *       reach this page at all).
 *
 * Security notes (fixed in this revision):
 *   - Previously had no CSRF protection at all. Now POST submissions
 *     require a valid csrf_token, matching delete.php, list.php, and
 *     edit.php.
 *   - Previously let Coordinator approve/reject ANY booking system-wide
 *     with no department scoping. Now scoped through authorizeScope(),
 *     applied immediately after the booking is fetched — a Coordinator
 *     outside a booking's department is redirected away before seeing
 *     any of its details, not only blocked from acting on it.
 *   - Previously compared $booking['status'] against the lowercase
 *     literal 'pending' in PHP (a strict, case-sensitive comparison).
 *     Since the schema's actual value is 'Pending', this comparison was
 *     always true, meaning every submission always hit the "already
 *     processed" branch and no booking could ever actually be approved
 *     or rejected through this file. Fixed to 'Pending'.
 *   - Previously wrote lowercase 'approved'/'rejected' into the status
 *     column. Unlike delete.php's original 'cancelled' bug, these two
 *     values do have case-variant matches in the schema's ENUM
 *     ('Approved', 'Rejected'), so this most likely already stored
 *     correctly under the schema's case-insensitive collation — but it
 *     relied on that collation rather than being correct as written, so
 *     it is corrected to the exact defined casing here regardless.
 *   - Previously required a second, independent copy of
 *     hasBookingConflict() from modules/bookings/conflict_check.php. This
 *     file now requires includes/functions.php and uses that single
 *     implementation instead — do not also require conflict_check.php
 *     from this file; both define hasBookingConflict() and loading both
 *     is a fatal "cannot redeclare function" error.
 *   - Previously used die() for the booking-not-found case. This now
 *     setFlash()s and redirects to list.php?all=1, which already renders
 *     flashes.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requirePermission(FEATURE_BOOKINGS_CANCEL);
// Matrix-driven gate: admits exactly the roles whose Cancel Bookings tier
// is Full Access or Department Bookings (Admin, Coordinator), and excludes
// Assigned-scope roles (Faculty) as well as No Access roles (Student,
// Maintenance) — identical admission to the original
// requireRole(['Admin', 'Coordinator']), derived from the matrix instead
// of a hardcoded role list.
$approvalLevel = getPermissionLevel(FEATURE_BOOKINGS_CANCEL);

if (!in_array($approvalLevel, [ACCESS_FULL, ACCESS_DEPARTMENT], true)) {
    header('Location: ' . basePath('unauthorized.php'));
    exit;
}

$user = currentUser();

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$id     = $isPost ? (int) ($_POST['id'] ?? 0) : (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Invalid booking.');
    header('Location: list.php?all=1');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        b.*,
        r.name AS room_name,
        u.full_name,
        p.name AS programme_name,
        s.number AS semester_number
    FROM bookings b
    JOIN rooms r
        ON b.room_id = r.id
    JOIN users u
        ON b.user_id = u.id
    JOIN programmes p
        ON b.programme_id = p.id
    JOIN semesters s
        ON b.semester_id = s.id
    WHERE b.id = :id
");

$stmt->execute([
    ':id' => $id,
]);

$booking = $stmt->fetch();

if (!$booking) {
    setFlash('error', 'Booking not found.');
    header('Location: list.php?all=1');
    exit;
}

// Department Scope Validation only matters for the Coordinator tier, so
// the extra lookup is skipped for Admin. Fetched fresh from the database
// rather than trusted from session — same reasoning already applied in
// coordinator/index.php, delete.php, and edit.php.
$actorProgrammeId = null;

if ($user['role'] === ROLE_COORDINATOR) {
    $programmeStmt = $pdo->prepare("SELECT programme_id FROM users WHERE id = :id");
    $programmeStmt->execute([':id' => $user['id']]);
    $programmeId = $programmeStmt->fetchColumn();
    $actorProgrammeId = ($programmeId !== false && $programmeId !== null) ? (int) $programmeId : null;
}

$authorized = authorizeScope(
    FEATURE_BOOKINGS_CANCEL,
    [
        'owner_id'     => (int) $booking['user_id'],
        'programme_id' => (int) $booking['programme_id'],
    ],
    true,
    $actorProgrammeId
);

if (!$authorized) {
    setFlash('error', 'You are not authorized to review this booking.');
    header('Location: list.php?all=1');
    exit;
}

$message = '';
$error   = '';

if ($isPost) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your session expired. Please try again.');
        header('Location: list.php?all=1');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($booking['status'] !== 'Pending') {

        $error = 'This booking has already been processed.';

    } elseif ($action === 'approve') {

        $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $pdo->beginTransaction();

        // Re-check conflict immediately before approval.
        if (
            findBookingConflict(
                $pdo,
                (int) $booking['room_id'],
                $booking['date'],
                $booking['start_time'],
                $booking['end_time'],
                $booking['id'],
                true
            )
        ) {

            $pdo->rollBack();

            $error = 'Cannot approve this booking because another booking conflicts with this time slot.';

        } else {

            $update = $pdo->prepare("
                UPDATE bookings
                SET status = 'Approved'
                WHERE id = :id
                  AND status = 'Pending'
            ");

            $update->execute([
                ':id' => $id,
            ]);

            $pdo->commit();

            $message = 'Booking approved successfully.';

            $booking['status'] = 'Approved';
        }

    } elseif ($action === 'reject') {

        $update = $pdo->prepare("
            UPDATE bookings
            SET status = 'Rejected'
            WHERE id = :id
              AND status = 'Pending'
        ");

        $update->execute([
            ':id' => $id,
        ]);

        $message = 'Booking rejected successfully.';

        $booking['status'] = 'Rejected';

    } else {

        $error = 'Invalid action.';
    }
}

$csrfToken = generateCsrfToken();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Review Booking | SESH</title>

    <link rel="stylesheet" href="../../assets/css/style.css">

</head>

<body>

<div class="container">

    <h1>Review Booking #<?= $id ?></h1>

    <p>
        <a href="list.php?all=1">
            ← Back to All Bookings
        </a>
    </p>


    <?php if ($message): ?>

        <div class="success-message">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="form-error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <div class="card">

        <p>
            <strong>Room:</strong>
            <?= htmlspecialchars($booking['room_name']) ?>
        </p>

        <p>
            <strong>Requested By:</strong>
            <?= htmlspecialchars($booking['full_name']) ?>
        </p>

        <p>
            <strong>Programme:</strong>
            <?= htmlspecialchars($booking['programme_name']) ?>
        </p>

        <p>
            <strong>Semester:</strong>
            <?= (int)$booking['semester_number'] ?>
        </p>

        <p>
            <strong>Date:</strong>
            <?= date('M j, Y', strtotime($booking['date'])) ?>
        </p>

        <p>
            <strong>Time:</strong>
            <?= date('g:i A', strtotime($booking['start_time'])) ?>
            -
            <?= date('g:i A', strtotime($booking['end_time'])) ?>
        </p>

        <p>
            <strong>Type:</strong>
            <?= htmlspecialchars($booking['type']) ?>
        </p>

        <p>
            <strong>Purpose:</strong><br>
            <?= nl2br(htmlspecialchars($booking['purpose'])) ?>
        </p>

        <p>
            <strong>Status:</strong>
            <?= htmlspecialchars(ucfirst($booking['status'])) ?>
        </p>

    </div>


    <?php if ($booking['status'] === 'Pending'): ?>

        <form method="POST" action="approve.php">

            <input
                type="hidden"
                name="id"
                value="<?= $id ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken) ?>"
            >

            <button
                type="submit"
                name="action"
                value="approve"
            >
                Approve Booking
            </button>

            <button
                type="submit"
                name="action"
                value="reject"
            >
                Reject Booking
            </button>

        </form>

    <?php else: ?>

        <p>
            This booking has already been processed.
        </p>

    <?php endif; ?>

</div>

</body>

</html>