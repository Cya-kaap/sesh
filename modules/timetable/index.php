<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requirePermission(FEATURE_TIMETABLE_VIEW);

$weekInput = $_GET['week'] ?? date('Y-m-d');
$weekDate = DateTime::createFromFormat('!Y-m-d', $weekInput);

if (!$weekDate || $weekDate->format('Y-m-d') !== $weekInput) {
    $weekDate = new DateTime('today');
}

$weekDate->modify('monday this week');
$weekStart = $weekDate->format('Y-m-d');
$weekEndDate = (clone $weekDate)->modify('+6 days');
$weekEnd = $weekEndDate->format('Y-m-d');

$programmeFilter = trim($_GET['programme_id'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');
$roomFilter = trim($_GET['room_id'] ?? '');

$programmes = $pdo->query('SELECT id, name FROM programmes ORDER BY name')->fetchAll();
$rooms = $pdo->query('SELECT id, name FROM rooms ORDER BY name')->fetchAll();

$where = [
    'b.date BETWEEN :week_start AND :week_end',
    "b.status IN ('Approved', 'Completed')",
];
$params = [
    ':week_start' => $weekStart,
    ':week_end' => $weekEnd,
];

if ($programmeFilter !== '' && ctype_digit($programmeFilter)) {
    $where[] = 'b.programme_id = :programme_id';
    $params[':programme_id'] = (int) $programmeFilter;
}

if (in_array($typeFilter, ['Class', 'Exam'], true)) {
    $where[] = 'b.type = :booking_type';
    $params[':booking_type'] = $typeFilter;
}

if ($roomFilter !== '' && ctype_digit($roomFilter)) {
    $where[] = 'b.room_id = :room_id';
    $params[':room_id'] = (int) $roomFilter;
}

$stmt = $pdo->prepare("\n    SELECT\n        b.id, b.date, b.start_time, b.end_time, b.type, b.purpose,\n        COALESCE(ed.exam_name, b.exam_name) AS exam_name,\n        COALESCE(ed.subject, b.subject) AS subject,\n        r.name AS room_name, p.name AS programme_name\n    FROM bookings b\n    LEFT JOIN exam_details ed ON ed.booking_id = b.id\n    JOIN rooms r ON r.id = b.room_id\n    JOIN programmes p ON p.id = b.programme_id\n    WHERE " . implode(' AND ', $where) . "\n    ORDER BY b.date, b.start_time, r.name\n");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$days = [];
for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
    $day = (clone $weekDate)->modify("+{$dayOffset} days");
    $days[$day->format('Y-m-d')] = $day;
}

$bookingsByDate = [];
foreach ($bookings as $booking) {
    $bookingsByDate[$booking['date']][] = $booking;
}

function timetableSlug(string $value): string
{
    $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $value));
    return trim($slug, '-') ?: 'other';
}

function timetableTime(string $time): string
{
    return date('g:i A', strtotime($time));
}

$previousWeek = (clone $weekDate)->modify('-7 days')->format('Y-m-d');
$nextWeek = (clone $weekDate)->modify('+7 days')->format('Y-m-d');
$todayWeek = (new DateTime('today'))->modify('monday this week')->format('Y-m-d');

function timetableQuery(array $overrides = []): string
{
    $query = array_merge($_GET, $overrides);
    return http_build_query($query);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Timetable | SESH</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .timetable-page { min-height: 100vh; background: #f4f3ef; color: #111; }
        .timetable-nav { min-height: 80px; padding: 0 6vw; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #d5d5d0; }
        .timetable-logo { font-size: 28px; font-weight: 900; letter-spacing: -0.08em; color: #111; text-decoration: none; }
        .timetable-nav a { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: #555; text-decoration: none; }
        .timetable-content { width: 94%; max-width: 1500px; margin: 0 auto; padding: 55px 0; }
        .page-label { margin-bottom: 12px; font-size: 10px; font-weight: 700; letter-spacing: 0.2em; color: #777; }
        h1 { margin: 0; font-size: clamp(36px, 5vw, 58px); letter-spacing: -0.05em; }
        .toolbar { display: flex; gap: 12px; align-items: end; flex-wrap: wrap; margin-top: 30px; }
        .toolbar label { display: block; margin-bottom: 7px; font-size: 9px; font-weight: 700; letter-spacing: .12em; }
        .toolbar input, .toolbar select, .toolbar button, .week-links a { padding: 11px 13px; border: 1px solid #ccc; background: #fff; color: #111; font: inherit; font-size: 12px; text-decoration: none; }
        .toolbar button, .week-links a { background: #111; color: #fff; border-color: #111; cursor: pointer; }
        .week-links { display: flex; gap: 8px; margin-top: 18px; }
        .week-links a.current { background: #ddd; color: #111; border-color: #ddd; }
        .week-range { margin-top: 22px; color: #666; font-size: 13px; }
        .legend { display: flex; gap: 18px; flex-wrap: wrap; margin: 24px 0 16px; font-size: 11px; }
        .legend span { display: inline-flex; align-items: center; gap: 6px; }
        .swatch { width: 11px; height: 11px; display: inline-block; border-radius: 50%; }
        .swatch-class { background: #2463a6; } .swatch-exam { background: #b23a48; }
        .grid-wrap { overflow-x: auto; border: 1px solid #d7d5ce; background: #fff; }
        .week-grid { min-width: 980px; display: grid; grid-template-columns: repeat(7, minmax(130px, 1fr)); }
        .day-column { min-height: 560px; border-right: 1px solid #e2e0da; }
        .day-column:last-child { border-right: 0; }
        .day-header { padding: 14px 12px; border-bottom: 1px solid #d7d5ce; background: #ebe9e2; }
        .day-header strong { display: block; font-size: 12px; }
        .day-header small { color: #777; font-size: 10px; }
        .day-body { padding: 10px; }
        .booking { margin-bottom: 10px; padding: 11px; border-left: 4px solid #2463a6; background: #eaf2fb; font-size: 11px; }
        .booking.exam { border-left-color: #b23a48; background: #fbecef; }
        .booking.programme-bca { box-shadow: inset 0 -3px 0 #2d8a57; }
        .booking.programme-bbm { box-shadow: inset 0 -3px 0 #d47c24; }
        .booking.programme-bba { box-shadow: inset 0 -3px 0 #188b8b; }
        .booking-time { font-weight: 800; }
        .booking-title { margin-top: 5px; font-weight: 700; }
        .booking-meta { margin-top: 6px; color: #555; line-height: 1.45; }
        .empty-day { color: #aaa; font-size: 11px; padding: 8px 2px; }
        .empty-state { padding: 50px 20px; text-align: center; color: #777; }
    </style>
</head>
<body>
<div class="timetable-page">
    <header class="timetable-nav">
        <a class="timetable-logo" href="../../dashboard.php">SESH</a>
        <a href="../../logout.php">LOGOUT</a>
    </header>
    <main class="timetable-content">
        <p class="page-label">LIVE SCHEDULE</p>
        <h1>Weekly Timetable</h1>

        <form class="toolbar" method="GET">
            <div><label for="week">WEEK OF</label><input id="week" type="date" name="week" value="<?= htmlspecialchars($weekStart) ?>"></div>
            <div><label for="programme_id">PROGRAMME</label><select id="programme_id" name="programme_id"><option value="">All programmes</option><?php foreach ($programmes as $programme): ?><option value="<?= (int) $programme['id'] ?>" <?= $programmeFilter === (string) $programme['id'] ? 'selected' : '' ?>><?= htmlspecialchars($programme['name']) ?></option><?php endforeach; ?></select></div>
            <div><label for="type">TYPE</label><select id="type" name="type"><option value="">All types</option><option value="Class" <?= $typeFilter === 'Class' ? 'selected' : '' ?>>Class</option><option value="Exam" <?= $typeFilter === 'Exam' ? 'selected' : '' ?>>Examination</option></select></div>
            <div><label for="room_id">ROOM</label><select id="room_id" name="room_id"><option value="">All rooms</option><?php foreach ($rooms as $room): ?><option value="<?= (int) $room['id'] ?>" <?= $roomFilter === (string) $room['id'] ? 'selected' : '' ?>><?= htmlspecialchars($room['name']) ?></option><?php endforeach; ?></select></div>
            <button type="submit">APPLY</button>
        </form>

        <div class="week-links">
            <a href="?<?= htmlspecialchars(timetableQuery(['week' => $previousWeek])) ?>">PREVIOUS WEEK</a>
            <a class="current" href="?<?= htmlspecialchars(timetableQuery(['week' => $todayWeek])) ?>">CURRENT WEEK</a>
            <a href="?<?= htmlspecialchars(timetableQuery(['week' => $nextWeek])) ?>">NEXT WEEK</a>
        </div>

        <p class="week-range"><?= htmlspecialchars($weekDate->format('d M Y')) ?> - <?= htmlspecialchars($weekEndDate->format('d M Y')) ?> · Approved and completed bookings</p>
        <div class="legend"><span><i class="swatch swatch-class"></i> Class</span><span><i class="swatch swatch-exam"></i> Examination</span><span>Bottom stripe: programme</span></div>

        <div class="grid-wrap">
            <div class="week-grid">
                <?php foreach ($days as $date => $day): ?>
                    <section class="day-column">
                        <header class="day-header"><strong><?= htmlspecialchars($day->format('l')) ?></strong><small><?= htmlspecialchars($day->format('d M')) ?></small></header>
                        <div class="day-body">
                            <?php if (empty($bookingsByDate[$date])): ?>
                                <div class="empty-day">No bookings</div>
                            <?php else: ?>
                                <?php foreach ($bookingsByDate[$date] as $booking): ?>
                                    <?php $typeClass = strtolower($booking['type']) === 'exam' ? 'exam' : 'class'; ?>
                                    <article class="booking <?= $typeClass ?> programme-<?= htmlspecialchars(timetableSlug($booking['programme_name'])) ?>">
                                        <div class="booking-time"><?= htmlspecialchars(timetableTime($booking['start_time'])) ?> - <?= htmlspecialchars(timetableTime($booking['end_time'])) ?></div>
                                        <div class="booking-title"><?= htmlspecialchars($booking['type'] === 'Exam' ? ($booking['exam_name'] ?: 'Examination') : ($booking['purpose'] ?: 'Class')) ?></div>
                                        <div class="booking-meta"><?= htmlspecialchars($booking['programme_name']) ?><br><?= htmlspecialchars($booking['room_name']) ?><?php if (!empty($booking['subject'])): ?><br><?= htmlspecialchars($booking['subject']) ?><?php endif; ?></div>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>
