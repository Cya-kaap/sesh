<?php

/**
 * SESH - Coordinator: Department Bookings
 *
 * Purpose:
 *   Filterable table of all bookings within the Coordinator's
 *   programme scope, with cancel (status -> Rejected) actions.
 *   Editing is handled on a separate page (edit_booking.php).
 *
 * Requires tables:
 *   - bookings, rooms, users, programmes, semesters
 *
 * Access:
 *   Coordinator only.
 *
 * Scope note:
 *   Same as coordinator/index.php — scope is derived by matching
 *   users.department to programmes.name.
 *
 * Cancel note:
 *   bookings.status has no 'Cancelled' value (enum is Pending,
 *   Approved, Rejected, Completed). "Cancel" here sets status to
 *   'Rejected' — the closest existing terminal state. Flag if you'd
 *   rather add a real 'Cancelled' value to the enum instead.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['Coordinator']);

$user  = currentUser();
$flash = getFlash();

// Pull department fresh from the DB on every load - see the note in
// coordinator/index.php and getCurrentDepartment() in functions.php.
$department = getCurrentDepartment($pdo, (int) $user['id']);

$programmeStmt = $pdo->prepare("SELECT id, name FROM programmes WHERE name = :dept LIMIT 1");
$programmeStmt->execute([':dept' => $department ?? '']);
$programme = $programmeStmt->fetch();

if (!$programme) {
    setFlash('error', 'Your account department does not match any configured programme. Contact an Admin.');
    header('Location: index.php');
    exit;
}

$statusOptions = getEnumValues($pdo, 'bookings', 'status');

/*
|--------------------------------------------------------------------------
| Handle Cancel (status -> Rejected)
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id']) && $_POST['action'] === 'cancel') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        setFlash('error', 'Your session expired. Please try again.');

    } else {

        $bookingId = (int) $_POST['id'];

        // Only allow cancelling bookings within this coordinator's own
        // programme scope — never someone else's, regardless of the
        // booking ID submitted.
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
                UPDATE bookings SET status = 'Rejected', updated_at = NOW() WHERE id = :id
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
        b.exam_name, b.subject, b.invigilator_name, b.num_students, b.status,
        r.name AS room_name, r.type AS room_type,
        u.full_name AS booked_by, u.role AS booked_by_role,
        s.number AS semester_number
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN users u ON b.user_id = u.id
    JOIN semesters s ON b.semester_id = s.id
    WHERE $whereSql
    ORDER BY b.date DESC, b.start_time DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$csrfToken = generateCsrfToken();

// Editing is only allowed while a booking's date is MORE than 2 days
// away - see edit_booking.php for the matching server-side check.
$editCutoff = date('Y-m-d', strtotime('+2 days'));

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
        .badge-completed { background: #d1ecf1; color: #0c5460; }

        .row-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .row-actions form { display: inline; }
        .row-actions a, .row-actions button {
            font-size: 10px; font-weight: 700; padding: 6px 10px; border: 1px solid #111;
            background: #fff; color: #111; cursor: pointer; text-decoration: none;
        }
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

                                    <?php $isEditable = $b['date'] > $editCutoff; ?>

                                    <?php if (in_array($b['status'], ['Pending', 'Approved'], true)): ?>

                                        <?php if ($isEditable): ?>
                                            <a href="edit_booking.php?id=<?= (int) $b['id'] ?>">EDIT</a>
                                        <?php else: ?>
                                            <span class="btn-none" title="Editing locks 2 days before the booking date">Locked</span>
                                        <?php endif; ?>

                                        <form method="POST" action="bookings.php<?= $filterStatus !== '' ? '?status=' . urlencode($filterStatus) : '' ?>"
                                              onsubmit="return confirm('Cancel this booking?');">
                                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <button type="submit" class="btn-cancel">CANCEL</button>
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