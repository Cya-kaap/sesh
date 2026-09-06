<?php

/**
 * SESH - Bookings List (modules pathway)
 *
 * Purpose:
 *   "My Bookings" view for Faculty, or "All Bookings" / department view
 *   for Coordinator and Admin (toggled via ?all=1). Embeds Edit, Cancel,
 *   and Review actions inline per row.
 *
 * Requires tables:
 *   - bookings, rooms, users, programmes, semesters
 *
 * Access:
 *   Faculty, Coordinator, Admin — the same role set as
 *   FEATURE_BOOKINGS_CANCEL's matrix cells. Student and Maintenance have
 *   No Access to bookings entirely and are excluded automatically.
 *
 * Fixes in this revision:
 *   - modules/bookings/delete.php was hardened to require POST + a valid
 *     CSRF token. This page previously linked to it with a bare GET <a>
 *     and no token, which would silently stop the Cancel action from
 *     working at all. The Cancel action here is now a POST <form> that
 *     carries a CSRF token, matching the exact convention already used in
 *     admin/bookings/index.php's cancel action.
 *   - The "All Bookings" (?all=1) query previously returned every booking
 *     system-wide to a Coordinator, not just their own department. This is
 *     the same "Department Bookings" scope violation fixed for mutation in
 *     delete.php, but as a read/data-exposure gap: a Coordinator could see
 *     every other department's bookings and requesters' names. Admin's
 *     "All Bookings" remains genuinely unrestricted, matching the matrix's
 *     "Any Booking" / Full Access for Admin.
 *   - Two case-mismatches ('pending' vs the schema's 'Pending') that
 *     silently hid the Edit, Cancel, and Review actions for every booking
 *     regardless of status are corrected.
 *   - The Cancel action's visibility is now decided by authorizeScope()
 *     against the same feature/scope delete.php itself enforces, instead
 *     of a hardcoded "owner only" check — so Admin and Coordinator now
 *     correctly see a Cancel action on bookings delete.php already allows
 *     them to cancel, not only their own.
 *
 * Deliberately NOT changed in this revision:
 *   - Edit's visibility (still "own booking only") and Review's visibility
 *     (still $canViewAll, i.e. Admin/Coordinator on any booking) are left
 *     as originally designed, aside from the case-mismatch fix. edit.php
 *     and approve.php have not yet been retrofitted with the RBAC engine
 *     the way delete.php has; widening this page's UI for those actions
 *     ahead of their backends would advertise access those endpoints don't
 *     yet correctly enforce.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

// Matrix-driven gate: identical role set to the original
// requireRole(['Faculty', 'Coordinator', 'Admin']), now derived from the
// Permission Matrix instead of a hardcoded list.
requirePermission(FEATURE_BOOKINGS_CANCEL);

$user   = currentUser();
$userId = $user['id'];
$role   = $user['role'];

$canViewAll = in_array($role, [ROLE_ADMIN, ROLE_COORDINATOR], true);
$viewAll    = $canViewAll && isset($_GET['all']);

// Resolved once, fresh from the database, and reused both to scope the
// "All Bookings" query below and to authorize each row's Cancel action —
// the same reasoning already applied in coordinator/index.php and in the
// hardened delete.php.
$actorProgrammeId = null;

if ($role === ROLE_COORDINATOR) {
    $programmeStmt = $pdo->prepare("SELECT programme_id FROM users WHERE id = :id");
    $programmeStmt->execute([':id' => $userId]);
    $programmeId = $programmeStmt->fetchColumn();
    $actorProgrammeId = ($programmeId !== false && $programmeId !== null) ? (int) $programmeId : null;
}

if ($viewAll && $role === ROLE_ADMIN) {

    // Admin: Any Booking — unrestricted, matches the original query exactly.
    $stmt = $pdo->query("
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
        ORDER BY b.date DESC, b.start_time DESC
    ");

} elseif ($viewAll && $role === ROLE_COORDINATOR) {

    // Coordinator: Department Bookings — scoped to their own programme,
    // fixing the previous system-wide exposure.
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
        WHERE b.programme_id = :programme_id
        ORDER BY b.date DESC, b.start_time DESC
    ");

    $stmt->execute([
        ':programme_id' => $actorProgrammeId,
    ]);

} else {

    // "My Bookings" — own bookings only, for any role (including Admin
    // and Coordinator when not toggled to the department/all view).
    $stmt = $pdo->prepare("
        SELECT
            b.*,
            r.name AS room_name,
            p.name AS programme_name,
            s.number AS semester_number
        FROM bookings b
        JOIN rooms r
            ON b.room_id = r.id
        JOIN programmes p
            ON b.programme_id = p.id
        JOIN semesters s
            ON b.semester_id = s.id
        WHERE b.user_id = :user_id
        ORDER BY b.date DESC, b.start_time DESC
    ");

    $stmt->execute([
        ':user_id' => $userId,
    ]);
}

$bookings  = $stmt->fetchAll();
$csrfToken = generateCsrfToken();
$flash     = getFlash();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $viewAll ? 'All Bookings' : 'My Bookings' ?> | SESH</title>

    <link rel="stylesheet" href="../../assets/css/style.css">

</head>

<body>

<div class="container">

    <h1>
        <?= $viewAll ? 'All Bookings' : 'My Bookings' ?>
    </h1>

    <p>

        <a href="create.php">
            + New Booking
        </a>

        |

        <?php if ($canViewAll && !$viewAll): ?>

            <a href="list.php?all=1">
                View All Bookings
            </a>

            |

        <?php endif; ?>

        <?php if ($role === ROLE_ADMIN): ?>

            <a href="../../admin/">
                ← Admin Dashboard
            </a>

        <?php elseif ($role === ROLE_COORDINATOR): ?>

            <a href="../../coordinator/">
                ← Coordinator Dashboard
            </a>

        <?php else: ?>

            <a href="../../faculty/">
                ← Faculty Dashboard
            </a>

        <?php endif; ?>

    </p>


    <?php if (isset($_GET['success'])): ?>

        <div class="success-message">
            Booking request submitted successfully.
        </div>

    <?php endif; ?>

    <?php foreach ($flash as $type => $message): ?>

        <div class="<?= $type === 'success' ? 'success-message' : 'error-message' ?>">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endforeach; ?>


    <?php if (empty($bookings)): ?>

        <p>No bookings found.</p>

    <?php else: ?>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>ID</th>
                        <th>Room</th>
                        <th>Programme</th>
                        <th>Semester</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Status</th>

                        <?php if ($viewAll): ?>
                            <th>Requested By</th>
                        <?php endif; ?>

                        <th>Actions</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($bookings as $booking): ?>

                    <?php
                        $canCancelThisRow = $booking['status'] === 'Pending'
                            && authorizeScope(
                                FEATURE_BOOKINGS_CANCEL,
                                [
                                    'owner_id'     => (int) $booking['user_id'],
                                    'programme_id' => (int) $booking['programme_id'],
                                ],
                                true,
                                $actorProgrammeId
                            );
                    ?>

                    <tr>

                        <td>
                            <?= (int)$booking['id'] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($booking['room_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($booking['programme_name']) ?>
                        </td>

                        <td>
                            Semester <?= (int)$booking['semester_number'] ?>
                        </td>

                        <td>
                            <?= date('M j, Y', strtotime($booking['date'])) ?>
                        </td>

                        <td>
                            <?= date('g:i A', strtotime($booking['start_time'])) ?>
                            -
                            <?= date('g:i A', strtotime($booking['end_time'])) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($booking['type']) ?>
                        </td>

                        <td>
                            <strong>
                                <?= htmlspecialchars(ucfirst($booking['status'])) ?>
                            </strong>
                        </td>

                        <?php if ($viewAll): ?>

                            <td>
                                <?= htmlspecialchars($booking['full_name']) ?>
                            </td>

                        <?php endif; ?>

                        <td>

                            <?php if (
                                $booking['status'] === 'Pending'
                                && (int)$booking['user_id'] === $userId
                            ): ?>

                                <a href="edit.php?id=<?= (int)$booking['id'] ?>">
                                    Edit
                                </a>

                                |

                            <?php endif; ?>


                            <?php if ($canCancelThisRow): ?>

                                <form
                                    method="POST"
                                    action="delete.php"
                                    style="display:inline"
                                    onsubmit="return confirm('Cancel this booking request?');"
                                >
                                    <input type="hidden" name="id" value="<?= (int)$booking['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <button type="submit" class="btn-reject">Cancel</button>
                                </form>

                                |

                            <?php endif; ?>


                            <?php if (
                                $canViewAll
                                && $booking['status'] === 'Pending'
                            ): ?>

                                <a href="approve.php?id=<?= (int)$booking['id'] ?>">
                                    Review
                                </a>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>

</body>

</html>