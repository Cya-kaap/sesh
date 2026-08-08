<?php

/**
 * SESH - Admin: Booking Overrides & Management
 *
 * Purpose:
 *   Lists all bookings system-wide. Admin can approve/reject pending
 *   bookings, and cancel already-approved bookings (via cancel.php).
 *
 * Requires tables:
 *   - bookings
 *   - users
 *   - rooms
 *
 * Access:
 *   Admin only.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['Admin']);

$user  = currentUser();
$flash = getFlash();

$statusOptions = getEnumValues($pdo, 'bookings', 'status');

// Only offer actions whose target status actually exists in the schema.
$actionMap = array_filter(
    ['approve' => 'Approved', 'reject' => 'Rejected'],
    fn ($status) => in_array($status, $statusOptions, true)
);

$canCancel = in_array('Cancelled', $statusOptions, true);

/*
|--------------------------------------------------------------------------
| Handle Approve / Reject
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        setFlash('error', 'Your session expired. Please try again.');

    } else {

        $bookingId = (int) $_POST['id'];
        $action    = $_POST['action'];

        if ($bookingId < 1 || !isset($actionMap[$action])) {

            setFlash('error', 'Invalid action.');

        } else {

            $newStatus = $actionMap[$action];

            $check = $pdo->prepare("SELECT status FROM bookings WHERE id = :id");
            $check->execute([':id' => $bookingId]);
            $current = $check->fetchColumn();

            if ($current === false) {
                setFlash('error', 'Booking not found.');
            } elseif ($current !== 'Pending') {
                setFlash('error', 'Only pending bookings can be approved or rejected.');
            } else {
                $update = $pdo->prepare("
                    UPDATE bookings SET status = :status, updated_at = NOW() WHERE id = :id
                ");
                $update->execute([':status' => $newStatus, ':id' => $bookingId]);
                setFlash('success', "Booking {$newStatus} successfully.");
            }
        }
    }

    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Bookings
|--------------------------------------------------------------------------
*/

$bookings = $pdo->query("
    SELECT
        b.id, b.date, b.start_time, b.end_time, b.type, b.purpose, b.status,
        u.full_name AS user_name,
        u.role AS user_role,
        r.name AS room_name,
        r.type AS room_type
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN rooms r ON b.room_id = r.id
    ORDER BY b.created_at DESC
")->fetchAll();

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Overrides | SESH Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .admin-page { min-height: 100vh; background: #f4f3ef; color: #111; }
        .admin-nav { min-height: 80px; padding: 0 6vw; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #d5d5d0; background: #f4f3ef; }
        .admin-logo { font-size: 28px; font-weight: 900; letter-spacing: -0.08em; color: #111; text-decoration: none; }
        .admin-nav-links { display: flex; align-items: center; gap: 25px; }
        .admin-nav-links a { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: #555; text-decoration: none; }
        .admin-nav-links a:hover { color: #111; }
        .admin-nav-links .logout { padding: 10px 16px; background: #111; color: #fff; }

        .admin-content { width: 92%; max-width: 1400px; margin: 0 auto; padding: 60px 0; }
        .page-label { margin-bottom: 12px; font-size: 10px; font-weight: 700; letter-spacing: 0.2em; color: #777; }
        .admin-content h1 { margin: 0 0 10px 0; font-size: clamp(36px, 5vw, 56px); letter-spacing: -0.05em; }

        .flash { margin-top: 30px; padding: 14px 18px; font-size: 13px; }
        .flash-success { background: #111; color: #fff; }
        .flash-error { background: #a33; color: #fff; }

        .data-table { width: 100%; border-collapse: collapse; margin-top: 40px; }
        .data-table th, .data-table td { padding: 16px 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 13px; vertical-align: top; }
        .data-table th { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; color: #777; background: #eae8e1; }
        .data-table small { color: #777; }

        .badge { display: inline-block; padding: 4px 8px; font-size: 9px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        .badge-cancelled { background: #e2e3e5; color: #383d41; }
        .badge-completed { background: #d1ecf1; color: #0c5460; }

        .table-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .table-actions form { display: inline; }
        .table-actions button, .table-actions .btn-none {
            font-size: 10px; font-weight: 700; padding: 6px 10px; border: 1px solid #111;
            background: #111; color: #fff; cursor: pointer;
        }
        .table-actions .btn-reject { background: #fff; border-color: #c9302c; color: #c9302c; }
        .table-actions .btn-none { border: none; color: #999; font-weight: 400; background: none; padding: 6px 0; }

        @media (max-width: 900px) {
            .data-table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
</head>
<body>
<div class="admin-page">

    <header class="admin-nav">
        <a href="../index.php" class="admin-logo">SESH</a>
        <nav class="admin-nav-links">
            <a href="../index.php">DASHBOARD</a>
            <a href="../../logout.php" class="logout">LOGOUT</a>
        </nav>
    </header>

    <main class="admin-content">

        <p class="page-label">SESSION OVERRIDES</p>
        <h1>Booking Management</h1>

        <?php if (!empty($flash['success'])): ?>
            <div class="flash flash-success"><?= htmlspecialchars($flash['success']) ?></div>
        <?php endif; ?>

        <?php if (!empty($flash['error'])): ?>
            <div class="flash flash-error"><?= htmlspecialchars($flash['error']) ?></div>
        <?php endif; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Booked By</th>
                    <th>Date &amp; Time</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                    <tr><td colspan="6">No booking records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($bookings as $b): ?>
                        <?php $badgeClass = 'badge-' . strtolower($b['status']); ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($b['room_name']) ?></strong><br>
                                <small><?= htmlspecialchars($b['room_type']) ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($b['user_name']) ?><br>
                                <small><?= htmlspecialchars($b['user_role']) ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars(date('d M Y', strtotime($b['date']))) ?><br>
                                <small>
                                    <?= htmlspecialchars(date('g:i A', strtotime($b['start_time']))) ?>
                                    &ndash;
                                    <?= htmlspecialchars(date('g:i A', strtotime($b['end_time']))) ?>
                                </small>
                            </td>
                            <td><?= htmlspecialchars($b['purpose'] ?: 'General Session') ?></td>
                            <td>
                                <span class="badge <?= htmlspecialchars($badgeClass) ?>">
                                    <?= htmlspecialchars($b['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">

                                    <?php if ($b['status'] === 'Pending'): ?>

                                        <?php if (isset($actionMap['approve'])): ?>
                                            <form method="POST" action="index.php">
                                                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <button type="submit">APPROVE</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (isset($actionMap['reject'])): ?>
                                            <form method="POST" action="index.php">
                                                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <button type="submit" class="btn-reject">REJECT</button>
                                            </form>
                                        <?php endif; ?>

                                    <?php elseif ($b['status'] === 'Approved' && $canCancel): ?>

                                        <form method="POST" action="cancel.php"
                                              onsubmit="return confirm('Override and cancel this booking?');">
                                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <button type="submit" class="btn-reject">CANCEL</button>
                                        </form>

                                    <?php else: ?>
                                        <span class="btn-none">None</span>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    </main>

</div>
</body>
</html>