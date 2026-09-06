<?php

/**
 * SESH - Admin: Utilization Reports
 *
 * Purpose:
 *   Date-range filtered booking status breakdown, per-room utilization,
 *   and a summary of reported room-condition feedback.
 *
 * Note on "utilization %":
 *   The schema has no concept of a room's operating hours, so this
 *   estimates utilization as booked-hours ÷ (days-in-range × 8h/day).
 *   That 8-hour assumption is a simplification worth calling out in
 *   your report/viva — a real system would let Admin configure each
 *   room's actual operating hours.
 *
 * Requires tables:
 *   - bookings, rooms, room_feedback
 *   (room_feedback and the 'Cancelled' status value require the
 *   migrations from the earlier audit's Section 11 to be applied.)
 *
 * Access: Admin only.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requirePermission(FEATURE_REPORTS_VIEW);

$defaultFrom = date('Y-m-01');
$defaultTo   = date('Y-m-t');

$dateFrom = $_GET['from'] ?? $defaultFrom;
$dateTo   = $_GET['to'] ?? $defaultTo;

if (!isValidDate($dateFrom)) {
    $dateFrom = $defaultFrom;
}
if (!isValidDate($dateTo)) {
    $dateTo = $defaultTo;
}
if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

/*
|--------------------------------------------------------------------------
| Booking Status Breakdown
|--------------------------------------------------------------------------
*/

$statusStmt = $pdo->prepare("
    SELECT status, COUNT(*) AS total
    FROM bookings
    WHERE date BETWEEN :from AND :to
    GROUP BY status
");
$statusStmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
$statusRows = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$allStatuses = ['Pending', 'Approved', 'Rejected', 'Completed', 'Cancelled'];
$statusCounts = [];
foreach ($allStatuses as $s) {
    $statusCounts[$s] = (int) ($statusRows[$s] ?? 0);
}
$totalBookings = array_sum($statusCounts);

/*
|--------------------------------------------------------------------------
| Per-Room Utilization
|--------------------------------------------------------------------------
*/

$roomStmt = $pdo->prepare("
    SELECT
        r.id, r.name, r.type, r.capacity,
        COUNT(b.id) AS booking_count,
        COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(b.end_time, b.start_time))) / 3600, 0) AS booked_hours
    FROM rooms r
    LEFT JOIN bookings b
        ON b.room_id = r.id
        AND b.date BETWEEN :from AND :to
        AND b.status IN ('Approved', 'Completed')
    GROUP BY r.id, r.name, r.type, r.capacity
    ORDER BY booking_count DESC, r.name ASC
");
$roomStmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
$roomStats = $roomStmt->fetchAll();

$daysInRange = (int) ((strtotime($dateTo) - strtotime($dateFrom)) / 86400) + 1;
$assumedDailyHours = 8;
$capacityHours = max($daysInRange * $assumedDailyHours, 1);

/*
|--------------------------------------------------------------------------
| Room Condition Feedback Summary
|--------------------------------------------------------------------------
*/

$feedbackAvailable = true;
try {
    $feedbackStmt = $pdo->prepare("
        SELECT
            r.name AS room_name,
            ROUND(AVG(f.cleanliness_rating), 1) AS avg_cleanliness,
            ROUND(AVG(f.equipment_rating), 1) AS avg_equipment,
            SUM(CASE WHEN f.issue_text IS NOT NULL AND f.issue_text != '' THEN 1 ELSE 0 END) AS issues_reported,
            COUNT(*) AS feedback_count
        FROM room_feedback f
        JOIN bookings b ON b.id = f.booking_id
        JOIN rooms r ON r.id = f.room_id
        WHERE b.date BETWEEN :from AND :to
        GROUP BY r.id, r.name
        ORDER BY issues_reported DESC, r.name ASC
    ");
    $feedbackStmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
    $feedbackStats = $feedbackStmt->fetchAll();
} catch (PDOException $e) {
    // room_feedback table not present yet — see the note at the top of this file.
    $feedbackAvailable = false;
    $feedbackStats = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | SESH Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-page { min-height: 100vh; background: #f4f3ef; color: #111; }
        .admin-nav { min-height: 80px; padding: 0 6vw; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #d5d5d0; background: #f4f3ef; }
        .admin-logo { font-size: 28px; font-weight: 900; letter-spacing: -0.08em; color: #111; text-decoration: none; }
        .admin-nav-links a { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: #555; text-decoration: none; }
        .admin-content { width: 92%; max-width: 1300px; margin: 0 auto; padding: 60px 0; }
        .page-label { margin-bottom: 12px; font-size: 10px; font-weight: 700; letter-spacing: 0.2em; color: #777; }
        .admin-content h1 { margin: 0 0 10px 0; font-size: clamp(36px, 5vw, 56px); letter-spacing: -0.05em; }

        .filter-bar { margin-top: 30px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .filter-bar label { display: block; font-size: 10px; font-weight: 700; letter-spacing: 0.1em; margin-bottom: 8px; }
        .filter-bar input { padding: 11px; border: 1px solid #ccc; }
        .filter-bar button { padding: 12px 20px; background: #111; color: #fff; border: none; font-size: 10px; font-weight: 700; letter-spacing: 0.1em; cursor: pointer; }

        .stat-grid { margin-top: 40px; display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; }
        .stat-card { padding: 25px; background: #fff; border: 1px solid #ddd; }
        .stat-card small { display: block; font-size: 9px; font-weight: 700; letter-spacing: 0.12em; color: #888; }
        .stat-card .stat-value { margin-top: 15px; font-size: 34px; font-weight: 800; }

        .section-title { margin-top: 60px; padding-top: 30px; border-top: 1px solid #ccc; font-size: 24px; letter-spacing: -0.02em; }

        .data-table { width: 100%; border-collapse: collapse; margin-top: 25px; }
        .data-table th, .data-table td { padding: 14px 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 13px; }
        .data-table th { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; color: #777; background: #eae8e1; }

        .util-bar-track { width: 140px; height: 8px; background: #eee; }
        .util-bar-fill { height: 8px; background: #111; }
        .util-pct { font-size: 11px; color: #777; margin-top: 4px; }

        .empty-note { margin-top: 25px; padding: 20px; background: #fff; border: 1px solid #ddd; font-size: 13px; color: #777; }
    </style>
</head>
<body>
<div class="admin-page">

    <header class="admin-nav">
        <a href="index.php" class="admin-logo">SESH</a>
        <nav class="admin-nav-links">
            <a href="index.php">DASHBOARD</a>
            <a href="../logout.php" class="logout">LOGOUT</a>
        </nav>
    </header>

    <main class="admin-content">

        <p class="page-label">ANALYTICS</p>
        <h1>Utilization Reports</h1>

        <form method="GET" action="reports.php" class="filter-bar">
            <div>
                <label for="from">From</label>
                <input type="date" id="from" name="from" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div>
                <label for="to">To</label>
                <input type="date" id="to" name="to" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <button type="submit">APPLY</button>
        </form>

        <section class="stat-grid">
            <div class="stat-card"><small>TOTAL BOOKINGS</small><div class="stat-value"><?= $totalBookings ?></div></div>
            <div class="stat-card"><small>PENDING</small><div class="stat-value"><?= $statusCounts['Pending'] ?></div></div>
            <div class="stat-card"><small>APPROVED</small><div class="stat-value"><?= $statusCounts['Approved'] ?></div></div>
            <div class="stat-card"><small>COMPLETED</small><div class="stat-value"><?= $statusCounts['Completed'] ?></div></div>
            <div class="stat-card"><small>REJECTED</small><div class="stat-value"><?= $statusCounts['Rejected'] ?></div></div>
            <div class="stat-card"><small>CANCELLED</small><div class="stat-value"><?= $statusCounts['Cancelled'] ?></div></div>
        </section>

        <h2 class="section-title">Room Utilization</h2>
        <p style="font-size:12px;color:#777;margin-top:10px;">
            Estimated against an assumed <?= $assumedDailyHours ?>-hour operating day over
            <?= $daysInRange ?> day(s) in range. Counts only Approved/Completed bookings.
        </p>

        <table class="data-table">
            <thead>
                <tr><th>Room</th><th>Type</th><th>Bookings</th><th>Booked Hours</th><th>Utilization</th></tr>
            </thead>
            <tbody>
                <?php if (empty($roomStats)): ?>
                    <tr><td colspan="5">No rooms configured.</td></tr>
                <?php else: ?>
                    <?php foreach ($roomStats as $r): ?>
                        <?php
                            $bookedHours = (float) $r['booked_hours'];
                            $utilPct = min(100, round(($bookedHours / $capacityHours) * 100));
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                            <td><?= htmlspecialchars($r['type']) ?></td>
                            <td><?= (int) $r['booking_count'] ?></td>
                            <td><?= round($bookedHours, 1) ?>h</td>
                            <td>
                                <div class="util-bar-track"><div class="util-bar-fill" style="width: <?= $utilPct ?>%;"></div></div>
                                <div class="util-pct"><?= $utilPct ?>%</div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <h2 class="section-title">Reported Room Condition Issues</h2>

        <?php if (!$feedbackAvailable): ?>
            <div class="empty-note">
                Feedback data isn't available yet — the <code>room_feedback</code> table
                hasn't been created on this database. Run the migration from Section 11
                of the audit, then reload this page.
            </div>
        <?php elseif (empty($feedbackStats)): ?>
            <div class="empty-note">No feedback submitted for bookings in this date range.</div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr><th>Room</th><th>Avg. Cleanliness</th><th>Avg. Equipment</th><th>Issues Reported</th><th>Total Feedback</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($feedbackStats as $f): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($f['room_name']) ?></strong></td>
                            <td><?= htmlspecialchars((string) $f['avg_cleanliness']) ?> / 5</td>
                            <td><?= htmlspecialchars((string) $f['avg_equipment']) ?> / 5</td>
                            <td><?= (int) $f['issues_reported'] ?></td>
                            <td><?= (int) $f['feedback_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </main>

</div>
</body>
</html>