<?php
/**
 * SESH - Admin Dashboard Index
 * Location: admin/index.php
 * Access: Admin only
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requirePermission(FEATURE_USERS_MANAGE);
$currentUser = currentUser();
$flash = getFlash();
$todayBookings = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE date = CURDATE() AND status IN ('Approved', 'Completed')")->fetchColumn();
$pendingApprovals = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'Pending'")->fetchColumn();
$activeRooms = (int) $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'Active'")->fetchColumn();
$roomsInUseToday = (int) $pdo->query("SELECT COUNT(DISTINCT room_id) FROM bookings WHERE date = CURDATE() AND status = 'Approved'")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SESH</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-dashboard { min-height: 100vh; background: #f4f3ef; color: #111; }
        .admin-nav { min-height: 80px; padding: 0 6vw; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #d5d5d0; background: #f4f3ef; }
        .admin-logo { font-size: 32px; font-weight: 900; letter-spacing: -0.08em; color: #111; text-decoration: none; }
        .admin-nav-links { display: flex; align-items: center; gap: 30px; }
        .admin-nav-links a { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: #555; text-decoration: none; }
        .admin-nav-links a:hover { color: #111; }
        .admin-nav-links .logout { padding: 12px 18px; background: #111; color: #fff; }
        .admin-main { width: 88%; max-width: 1400px; margin: 0 auto; padding: 70px 0; }
        .admin-header { display: flex; justify-content: space-between; align-items: flex-end; padding-bottom: 35px; border-bottom: 1px solid #ccc; }
        .admin-label { margin-bottom: 15px; font-size: 10px; font-weight: 700; letter-spacing: 0.2em; color: #777; }
        .admin-header h1 { margin: 0; font-size: clamp(40px, 5vw, 65px); line-height: 0.9; letter-spacing: -0.06em; }
        .flash { margin-top: 30px; padding: 14px 18px; font-size: 13px; }
        .flash-success { background: #111; color: #fff; }
        .flash-error { background: #a33; color: #fff; }
        .stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin:35px 0; }
        .stat-card { padding:22px; background:#fff; border:1px solid #ddd; text-decoration:none; color:#111; }
        .stat-card small { display:block; color:#777; font-size:9px; letter-spacing:.12em; font-weight:700; }
        .stat-value { margin-top:12px; font-size:34px; font-weight:800; }
        
        /* Grid Layout for Module Cards */
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 40px;
        }
        .module-card {
            display: flex;
            flex-direction: column;
            padding: 30px;
            background: #eae8e1;
            border: 1px solid #d5d5d0;
            text-decoration: none;
            color: #111;
            transition: transform 0.2s ease, background-color 0.2s ease;
        }
        .module-card:hover {
            background: #e0ded6;
            transform: translateY(-2px);
        }
        .module-card small {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.15em;
            color: #777;
            margin-bottom: 12px;
        }
        .module-card h3 {
            margin: 0 0 10px 0;
            font-size: 20px;
            letter-spacing: -0.02em;
        }
        .module-card p {
            margin: 0;
            font-size: 13px;
            color: #555;
            line-height: 1.5;
        }
    </style>
</head>
<body>
<div class="admin-dashboard">
    <header class="admin-nav">
        <a href="index.php" class="admin-logo">SESH</a>
        <nav class="admin-nav-links">
            <a href="index.php">DASHBOARD</a>
            <a href="../logout.php" class="logout">LOGOUT</a>
        </nav>
    </header>

    <main class="admin-main">
        <section class="admin-header">
            <div>
                <p class="admin-label">ADMINISTRATION CONTROL CENTER</p>
                <h1>Dashboard</h1>
            </div>
        </section>

        <?php if (!empty($flash['success'])): ?>
            <div class="flash flash-success"><?= htmlspecialchars($flash['success'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if (!empty($flash['error'])): ?>
            <div class="flash flash-error"><?= htmlspecialchars($flash['error'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <section class="stat-grid">
            <div class="stat-card"><small>TODAY'S BOOKINGS</small><div class="stat-value"><?= $todayBookings ?></div></div>
            <a class="stat-card" href="bookings/index.php"><small>PENDING APPROVALS</small><div class="stat-value"><?= $pendingApprovals ?></div></a>
            <div class="stat-card"><small>ACTIVE ROOMS</small><div class="stat-value"><?= $activeRooms ?></div></div>
            <div class="stat-card"><small>ROOMS IN USE TODAY</small><div class="stat-value"><?= $roomsInUseToday ?></div></div>
        </section>

        <section class="module-grid">

            <a href="rooms/index.php" class="module-card">
                <small>01 / ROOMS</small>
                <h3>Room &amp; Lab Management</h3>
                <p>Create, edit and manage rooms, labs and their resource attributes.</p>
            </a>

            <a href="users/index.php" class="module-card">
                <small>02 / USERS</small>
                <h3>User Management</h3>
                <p>Register and manage Faculty and Coordinator accounts.</p>
            </a>

            <a href="bookings/index.php" class="module-card">
                <small>03 / BOOKINGS</small>
                <h3>Booking Overrides</h3>
                <p>View, cancel or adjust any booking in the system.</p>
            </a>

            <a href="academic/index.php" class="module-card">
                <small>04 / ACADEMIC</small>
                <h3>Academic Setup</h3>
                <p>Manage Programmes and their Semesters.</p>
            </a>

            <a href="reports.php" class="module-card">
                <small>05 / REPORTS</small>
                <h3>Utilization Reports</h3>
                <p>Room utilization, booking status breakdown, and reported condition issues.</p>
            </a>

            <a href="../modules/timetable/index.php" class="module-card">
                <small>06 / TIMETABLE</small>
                <h3>Weekly Timetable</h3>
                <p>View approved classes and examinations by week, room and programme.</p>
            </a>

            <a href="settings/booking.php" class="module-card">
                <small>07 / POLICY</small>
                <h3>Booking Rules</h3>
                <p>Configure duration limits, advance booking, approvals, and blackout periods.</p>
            </a>

        </section>
    </main>
</div>
</body>
</html>