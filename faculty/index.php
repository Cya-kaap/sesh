<?php

/**
 * SESH - Faculty Dashboard
 *
 * Purpose:
 *   Personal upcoming class schedule, quick link to new booking form,
 *   and alerts for completed bookings still awaiting feedback.
 *
 * Requires tables:
 *   - bookings, rooms, room_feedback
 *
 * Access:
 *   Faculty only.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['Faculty']);

$user = currentUser();

autoCompletePastBookings($pdo);

/*
|--------------------------------------------------------------------------
| Upcoming Bookings
|--------------------------------------------------------------------------
*/

$upcomingStmt = $pdo->prepare("
    SELECT b.id, b.date, b.start_time, b.end_time, b.type, b.purpose, b.status,
           r.name AS room_name
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE b.user_id = :uid
      AND b.status IN ('Pending', 'Approved')
      AND b.date >= CURDATE()
    ORDER BY b.date ASC, b.start_time ASC
    LIMIT 8
");
$upcomingStmt->execute([':uid' => $user['id']]);
$upcomingBookings = $upcomingStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Bookings Awaiting Feedback
|--------------------------------------------------------------------------
*/

$pendingFeedbackStmt = $pdo->prepare("
    SELECT b.id, b.date, r.name AS room_name
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    LEFT JOIN room_feedback f ON f.booking_id = b.id
    WHERE b.user_id = :uid
      AND b.status = 'Completed'
      AND f.id IS NULL
    ORDER BY b.date DESC
");
$pendingFeedbackStmt->execute([':uid' => $user['id']]);
$pendingFeedback = $pendingFeedbackStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard | SESH</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .fac-page { min-height: 100vh; background: #f4f3ef; color: #111; }
        .fac-nav { min-height: 80px; padding: 0 6vw; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #d5d5d0; background: #f4f3ef; }
        .fac-logo { font-size: 28px; font-weight: 900; letter-spacing: -0.08em; color: #111; text-decoration: none; }
        .fac-nav-links { display: flex; align-items: center; gap: 25px; }
        .fac-nav-links a { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: #555; text-decoration: none; }
        .fac-nav-links a:hover { color: #111; }
        .fac-nav-links .logout { padding: 10px 16px; background: #111; color: #fff; }

        .fac-content { width: 88%; max-width: 1300px; margin: 0 auto; padding: 60px 0; }
        .page-label { margin-bottom: 12px; font-size: 10px; font-weight: 700; letter-spacing: 0.2em; color: #777; }
        .fac-content h1 { margin: 0; font-size: clamp(40px, 5vw, 60px); letter-spacing: -0.05em; }

        .alert-box { margin-top: 30px; padding: 18px 20px; background: #fff3cd; color: #856404; }
        .alert-box strong { display: block; margin-bottom: 6px; font-size: 13px; }
        .alert-box a { color: #533f03; font-weight: 700; }
        .alert-box ul { margin: 8px 0 0 0; padding-left: 18px; font-size: 12px; }

        .module-grid { margin-top: 50px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .module-card { min-height: 160px; padding: 30px; background: #fff; border: 1px solid #ddd; display: flex; flex-direction: column; transition: 0.25s ease; text-decoration: none; color: inherit; }
        .module-card:hover { background: #111; color: #fff; }
        .module-card small { font-size: 9px; font-weight: 700; letter-spacing: 0.15em; color: #888; }
        .module-card h3 { margin-top: 25px; font-size: 22px; letter-spacing: -0.03em; }
        .module-card p { margin-top: 10px; font-size: 12px; line-height: 1.7; color: #777; }
        .module-card:hover p { color: #aaa; }

        .schedule-section { margin-top: 70px; padding-top: 40px; border-top: 1px solid #ccc; }
        .schedule-section h2 { font-size: 28px; letter-spacing: -0.03em; margin-bottom: 25px; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 14px 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 13px; }
        .data-table th { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; color: #777; background: #eae8e1; }

        .badge { display: inline-block; padding: 4px 8px; font-size: 9px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }

        @media (max-width: 700px) {
            .module-grid { grid-template-columns: 1fr; }
            .data-table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
</head>
<body>
<div class="fac-page">

    <header class="fac-nav">
        <a href="index.php" class="fac-logo">SESH</a>
        <nav class="fac-nav-links">
            <a href="../index.php">WEBSITE</a>
            <a href="../logout.php" class="logout">LOGOUT</a>
        </nav>
    </header>

    <main class="fac-content">

        <p class="page-label">SESSION MANAGEMENT SYSTEM</p>
        <h1>Welcome, <?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?></h1>

        <?php if (!empty($pendingFeedback)): ?>
            <div class="alert-box">
                <strong>You have <?= count($pendingFeedback) ?> completed booking(s) awaiting feedback</strong>
                <ul>
                    <?php foreach ($pendingFeedback as $pf): ?>
                        <li>
                            <?= htmlspecialchars($pf['room_name']) ?> — <?= htmlspecialchars(date('d M Y', strtotime($pf['date']))) ?>
                            — <a href="room_feedback.php?booking_id=<?= (int) $pf['id'] ?>">Submit feedback →</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <section class="module-grid">

            <a href="new_booking.php" class="module-card">
                <small>01 / BOOKING</small>
                <h3>Book a Room</h3>
                <p>Request a room or lab for your lecture, extra class, or subject exam.</p>
            </a>

            <a href="my_bookings.php" class="module-card">
                <small>02 / HISTORY</small>
                <h3>My Bookings</h3>
                <p>View your booking history, filter by date, and submit feedback for completed sessions.</p>
            </a>

        </section>

        <section class="schedule-section">
            <h2>Upcoming Schedule</h2>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Room</th>
                        <th>Date &amp; Time</th>
                        <th>Type</th>
                        <th>Purpose</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upcomingBookings)): ?>
                        <tr><td colspan="5">No upcoming bookings. <a href="new_booking.php">Book a room →</a></td></tr>
                    <?php else: ?>
                        <?php foreach ($upcomingBookings as $b): ?>
                            <tr>
                                <td><?= htmlspecialchars($b['room_name']) ?></td>
                                <td>
                                    <?= htmlspecialchars(date('d M Y', strtotime($b['date']))) ?>
                                    <br>
                                    <small style="color:#777;">
                                        <?= htmlspecialchars(date('g:i A', strtotime($b['start_time']))) ?>
                                        &ndash;
                                        <?= htmlspecialchars(date('g:i A', strtotime($b['end_time']))) ?>
                                    </small>
                                </td>
                                <td><?= htmlspecialchars($b['type']) ?></td>
                                <td><?= htmlspecialchars($b['purpose'] ?: '—') ?></td>
                                <td>
                                    <span class="badge badge-<?= strtolower($b['status']) ?>"><?= htmlspecialchars($b['status']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

    </main>

</div>
</body>
</html>