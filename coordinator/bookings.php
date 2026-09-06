<?php

/**
 * SESH - Coordinator: Department Bookings
 *
 * Purpose:
 *   Filterable table of all bookings within the Coordinator's
 *   programme scope, with approve, reject, and cancel actions.
 *   Editing is handled on a separate page (edit_booking.php).
 *
 * Requires tables:
 *   - bookings, rooms, users, programmes, semesters
 *
 * Access:
 *   Coordinator only.
 *
 * Scope note:
 *   Same as coordinator/index.php — scope is derived by joining
 *   users.programme_id to programmes.id.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requirePermission(FEATURE_BOOKINGS_CANCEL);

$user  = currentUser();
$flash = getFlash();

// Resolve the coordinator's programme scope from their foreign key.
$programmeStmt = $pdo->prepare("
    SELECT p.id, p.name
    FROM users u
    JOIN programmes p ON p.id = u.programme_id
    WHERE u.id = :id
    LIMIT 1
");
$programmeStmt->execute([':id' => $user['id']]);
$programme = $programmeStmt->fetch();

if (!$programme) {
    setFlash('error', 'Your account department does not match any configured programme. Contact an Admin.');
    header('Location: index.php');
    exit;
}

$statusOptions = getEnumValues($pdo, 'bookings', 'status');

/*
|--------------------------------------------------------------------------
| Handle Cancel (status -> Cancelled)
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id']) && $_POST['action'] === 'cancel') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your session expired. Please try again.');
    } else {
        $bookingId = (int) $_POST['id'];

        // Only allow cancelling bookings within this coordinator's own programme scope
        $check = $pdo->prepare("
            SELECT status FROM bookings WHERE id = :id AND programme_id = :pid
        ");
        $check->execute([':id' => $bookingId, ':pid' => $programme['id']]);
        $current = $check->fetchColumn();

        if ($current === false) {
            setFlash('error', 'Booking not found in your programme scope.');
        } elseif (!in_array($current, ['Pending', 'Approved'], true)) {
            setFlash('error', 'Only pending or approved bookings can be cancelled.');
        } else {
            $update = $pdo->prepare("
                UPDATE bookings SET status = 'Cancelled', updated_at = NOW() WHERE id = :id
            ");
            $update->execute([':id' => $bookingId]);
            setFlash('success', 'Booking cancelled.');
        }
    }

    header('Location: bookings.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

/*
|--------------------------------------------------------------------------
| Handle Approve / Reject (scoped to this Coordinator's own programme —
| per the Role Matrix, Coordinator has full Approve/Reject access, but
| only within bookings they manage.)
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id']) && in_array($_POST['action'], ['approve', 'reject'], true)) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your session expired. Please try again.');
    } else {
        $bookingId = (int) $_POST['id'];
        $newStatus = $_POST['action'] === 'approve' ? 'Approved' : 'Rejected';

        $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $pdo->beginTransaction();

        $check = $pdo->prepare("SELECT status, room_id, date, start_time, end_time FROM bookings WHERE id = :id AND programme_id = :pid FOR UPDATE");
        $check->execute([':id' => $bookingId, ':pid' => $programme['id']]);
        $current = $check->fetch();

        if (!$current) {
            $pdo->rollBack();
            setFlash('error', 'Booking not found in your programme scope.');
        } elseif ($current['status'] !== 'Pending') {
            $pdo->rollBack();
            setFlash('error', 'Only pending bookings can be approved or rejected.');
        } elseif ($newStatus === 'Approved' && findBookingConflict($pdo, (int) $current['room_id'], $current['date'], $current['start_time'], $current['end_time'], $bookingId, true)) {
            $pdo->rollBack();
            setFlash('error', 'Cannot approve — this room now conflicts with another booking.');
        } else {
            $update = $pdo->prepare("UPDATE bookings SET status = :status, updated_at = NOW() WHERE id = :id");
            $update->execute([':status' => $newStatus, ':id' => $bookingId]);
            $pdo->commit();
            setFlash('success', "Booking {$newStatus}.");
        }
    }

    header('Location: bookings.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$filterStatus = $_GET['status'] ?? '';
$filterType   = $_GET['type']   ?? '';

$where  = ['b.programme_id = :pid'];
$params = [':pid' => $programme['id']];

if ($filterStatus !== '' && in_array($filterStatus, $statusOptions, true)) {
    $where[] = 'b.status = :status';
    $params[':status'] = $filterStatus;
}

if ($filterType !== '' && in_array($filterType, ['Class', 'Exam'], true)) {
    $where[] = 'b.type = :type';
    $params[':type'] = $filterType;
}

$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT
        b.id, b.date, b.start_time, b.end_time, b.type, b.purpose,
        COALESCE(ed.exam_name, b.exam_name) AS exam_name,
        COALESCE(ed.subject, b.subject) AS subject,
        COALESCE(ed.invigilator_name, b.invigilator_name) AS invigilator_name,
        COALESCE(ed.num_students, b.num_students) AS num_students, b.status,
        r.name AS room_name, r.type AS room_type,
        u.full_name AS booked_by, u.role AS booked_by_role,
        s.number AS semester_number
    FROM bookings b
    LEFT JOIN exam_details ed ON ed.booking_id = b.id
    JOIN rooms r ON b.room_id = r.id
    JOIN users u ON b.user_id = u.id
    JOIN semesters s ON b.semester_id = s.id
    WHERE $whereSql
    ORDER BY b.date DESC, b.start_time DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Bookings | SESH Coordinator</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .coord-page { min-height: 100vh; background: #f4f3ef; color: #111; }
        .coord-nav { min-height: 80px; padding: 0 6vw; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #d5d5d0; background: #f4f3ef; }
        .coord-logo { font-size: 28px; font-weight: 900; letter-spacing: -0.08em; color: #111; text-decoration: none; }
        .coord-nav-links a { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: #555; text-decoration: none; }

        .coord-content { width: 92%; max-width: 1400px; margin: 0 auto; padding: 60px 0; }
        .page-label { margin-bottom: 12px; font-size: 10px; font-weight: 700; letter-spacing: 0.2em; color: #777; }
        .coord-content h1 { margin: 0 0 10px 0; font-size: clamp(36px, 5vw, 56px); letter-spacing: -0.05em; }

        .flash { margin-top: 20px; padding: 14px 18px; font-size: 13px; }
        .flash-success { background: #111; color: #fff; }
        .flash-error { background: #a33; color: #fff; }

        .filter-bar { margin-top: 30px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-bar select { padding: 10px 12px; border: 1px solid #ccc; background: #fff; font-size: 13px; }
        .filter-bar button, .filter-bar a.clear {
            padding: 10px 16px; font-size: 10px; font-weight: 700; letter-spacing: 0.08em;
            background: #111; color: #fff; border: none; cursor: pointer; text-decoration: none;
        }
        .filter-bar a.clear { background: #ddd; color: #111; }

        .data-table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        .data-table th, .data-table td { padding: 14px 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 13px; vertical-align: top; }
        .data-table th { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; color: #777; background: #eae8e1; }
        .data-table small { color: #777; }

        .badge { display: inline-block; padding: 4px 8px; font-size: 9px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        .badge-cancelled { background: #e2e3e5; color: #383d41; }
        .badge-completed { background: #d1ecf1; color: #0c5460; }

        .row-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .row-actions form { display: inline; }
        .row-actions a, .row-actions button {
            font-size: 10px; font-weight: 700; padding: 6px 10px; border: 1px solid #111;
            background: #fff; color: #111; cursor: pointer; text-decoration: none;
        }
        .row-actions .btn-approve { border-color: #155724; color: #155724; }
        .row-actions .btn-reject { border-color: #721c24; color: #721c24; }
        .row-actions .btn-cancel { border-color: #c9302c; color: #c9302c; }
        .row-actions .btn-none { border: none; color: #999; font-weight: 400; padding: 6px 0; }

        @media (max-width: 900px) {
            .data-table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
</head>
<body>
<div class="coord-page">

    <header class="coord-nav">
        <a href="index.php" class="coord-logo">SESH</a>
        <nav class="coord-nav-links">
            <a href="index.php">← BACK TO DASHBOARD</a>
        </nav>
    </header>

    <main class="coord-content">

        <p class="page-label"><?= htmlspecialchars($programme['name']) ?> PROGRAMME</p>
        <h1>Department Bookings</h1>

        <?php if (!empty($flash['success'])): ?>
            <div class="flash flash-success"><?= htmlspecialchars($flash['success']) ?></div>
        <?php endif; ?>

        <?php if (!empty($flash['error'])): ?>
            <div class="flash flash-error"><?= htmlspecialchars($flash['error']) ?></div>
        <?php endif; ?>

        <form method="GET" action="bookings.php" class="filter-bar">

            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach ($statusOptions as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>" <?= $filterStatus === $opt ? 'selected' : '' ?>>
                        <?= htmlspecialchars($opt) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="type" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="Class" <?= $filterType === 'Class' ? 'selected' : '' ?>>Class</option>
                <option value="Exam" <?= $filterType === 'Exam' ? 'selected' : '' ?>>Exam</option>
            </select>

            <noscript><button type="submit">FILTER</button></noscript>

            <?php if ($filterStatus !== '' || $filterType !== ''): ?>
                <a href="bookings.php" class="clear">CLEAR FILTERS</a>
            <?php endif; ?>

        </form>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Booked By</th>
                    <th>Date &amp; Time</th>
                    <th>Type / Details</th>
                    <th>Semester</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                    <tr><td colspan="7">No bookings match these filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($bookings as $b): ?>
                        <?php $badgeClass = 'badge-' . strtolower($b['status']); ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($b['room_name']) ?></strong><br>
                                <small><?= htmlspecialchars($b['room_type']) ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($b['booked_by']) ?><br>
                                <small><?= htmlspecialchars($b['booked_by_role']) ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars(date('d M Y', strtotime($b['date']))) ?><br>
                                <small>
                                    <?= htmlspecialchars(date('g:i A', strtotime($b['start_time']))) ?>
                                    &ndash;
                                    <?= htmlspecialchars(date('g:i A', strtotime($b['end_time']))) ?>
                                </small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($b['type']) ?></strong><br>
                                <?php if ($b['type'] === 'Exam'): ?>
                                    <small>
                                        <?= htmlspecialchars($b['exam_name'] ?: '') ?>
                                        <?= $b['subject'] ? ' — ' . htmlspecialchars($b['subject']) : '' ?>
                                    </small>
                                <?php else: ?>
                                    <small><?= htmlspecialchars($b['purpose'] ?: 'General Session') ?></small>
                                <?php endif; ?>
                            </td>
                            <td>Sem <?= (int) $b['semester_number'] ?></td>
                            <td>
                                <span class="badge <?= htmlspecialchars($badgeClass) ?>">
                                    <?= htmlspecialchars($b['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">

                                    <?php if ($b['status'] === 'Pending'): ?>

                                        <form method="POST" action="bookings.php<?= $filterStatus !== '' ? '?status=' . urlencode($filterStatus) : '' ?>">
                                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <button type="submit" class="btn-approve">APPROVE</button>
                                        </form>

                                        <form method="POST" action="bookings.php<?= $filterStatus !== '' ? '?status=' . urlencode($filterStatus) : '' ?>"
                                              onsubmit="return confirm('Reject this booking request?');">
                                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <button type="submit" class="btn-reject">REJECT</button>
                                        </form>

                                    <?php endif; ?>

                                    <?php if (in_array($b['status'], ['Pending', 'Approved'], true)): ?>

                                        <a href="edit_booking.php?id=<?= (int) $b['id'] ?>">EDIT</a>

                                        <form method="POST" action="bookings.php<?= $filterStatus !== '' ? '?status=' . urlencode($filterStatus) : '' ?>"
                                              onsubmit="return confirm('Cancel this booking?');">
                                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <button type="submit" class="btn-cancel">CANCEL</button>
                                        </form>

                                    <?php elseif ($b['status'] !== 'Pending'): ?>
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