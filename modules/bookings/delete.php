<?php

/**
 * SESH - Cancel Booking (modules pathway)
 *
 * Purpose:
 *   Cancels a Pending booking. Referenced by list.php's inline Cancel
 *   form (POST /delete.php) and by the RBAC audit as the file every other
 *   modules/bookings/*.php page cites as "already hardened" — this is
 *   that implementation.
 *
 * Access:
 *   Reuses FEATURE_BOOKINGS_CANCEL directly (this file's actual purpose
 *   matches the matrix row name exactly, unlike edit.php/approve.php
 *   which had to borrow it):
 *     Admin       - Any Booking (System Override)
 *     Coordinator - Department Bookings (own programme_id only)
 *     Faculty     - Assigned Bookings Only (own user_id only)
 *     Student, Maintenance - No Access
 *
 * Security notes:
 *   - POST-only, enforced before any other logic runs.
 *   - CSRF token required, matching edit.php / approve.php / list.php.
 *   - authorizeScope() enforces ownership/department scope — the same
 *     engine and same feature list.php already uses to decide whether to
 *     show the Cancel button for a given row, so a Coordinator or Faculty
 *     member cannot cancel a booking outside their scope even by posting
 *     directly to this endpoint with a guessed id.
 *   - Only 'Pending' bookings can be cancelled (matches edit.php's rule
 *     that only Pending bookings are mutable pre-approval).
 *
 * Known schema gap (see RBAC audit, Section D):
 *   database/schema.sql's bookings.status ENUM does not currently include
 *   'Cancelled'. Rather than attempt the UPDATE and rely on MySQL's
 *   default behavior for an out-of-range ENUM value (which silently
 *   coerces to '' under non-strict SQL modes, corrupting the row), this
 *   file checks getEnumValues() first and refuses with a clear flash
 *   message if the migration hasn't run yet — the same defensive pattern
 *   admin/bookings/cancel.php and admin/bookings/index.php already use.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireWritePermission(FEATURE_BOOKINGS_CANCEL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('error', 'Invalid request method.');
    header('Location: list.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Your session expired. Please try again.');
    header('Location: list.php');
    exit;
}

$user = currentUser();
$id   = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Invalid booking.');
    header('Location: list.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = :id");
$stmt->execute([':id' => $id]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlash('error', 'Booking not found.');
    header('Location: list.php');
    exit;
}

// Department Scope Validation only matters for the Coordinator tier —
// fetched fresh from the database, same reasoning as edit.php/approve.php.
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
    setFlash('error', 'You are not authorized to cancel this booking.');
    header('Location: list.php');
    exit;
}

if ($booking['status'] !== 'Pending') {
    setFlash('error', 'Only pending bookings can be cancelled.');
    header('Location: list.php');
    exit;
}

$statusOptions = getEnumValues($pdo, 'bookings', 'status');

if (!in_array('Cancelled', $statusOptions, true)) {
    setFlash('error', 'Cancelling bookings is not yet supported by the current database schema. Please contact an administrator.');
    header('Location: list.php');
    exit;
}

$update = $pdo->prepare("
    UPDATE bookings
    SET status = 'Cancelled'
    WHERE id = :id
      AND status = 'Pending'
");

$update->execute([':id' => $id]);

setFlash('success', 'Booking cancelled successfully.');
header('Location: list.php');
exit;