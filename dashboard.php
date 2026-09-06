<?php

/**
 * SESH - User Dashboard
 *
 * Used by Faculty and Coordinator (Admin is redirected to /admin/).
 * Now matrix-aware: action cards are shown only when the current role
 * has the corresponding permission.
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$user = currentUser();
$role = $user['role'];

// Matrix lookups – used only to decide which cards to render
$canBook   = getPermissionLevel(FEATURE_BOOK_REGULAR, $role) !== ACCESS_NONE
          || getPermissionLevel(FEATURE_BOOK_EXAM, $role) !== ACCESS_NONE;

$canViewBookings = getPermissionLevel(FEATURE_BOOKINGS_CANCEL, $role) !== ACCESS_NONE;

$canViewRooms = getPermissionLevel(FEATURE_ROOMS_CRUD, $role) !== ACCESS_NONE;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | SESH</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .user-dashboard { min-height: 100vh; background: #f4f3ef; color: #111; }

        .dashboard-nav {
            min-height: 80px; padding: 0 6vw;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid #d5d5d0; background: #f4f3ef;
        }
        .dashboard-logo {
            font-size: 32px; font-weight: 900; letter-spacing: -0.08em;
            color: #111; text-decoration: none;
        }
        .dashboard-nav-links { display: flex; align-items: center; gap: 30px; }
        .dashboard-nav-links a {
            font-size: 10px; font-weight: 700; letter-spacing: 0.12em;
            color: #555; text-decoration: none;
        }
        .dashboard-nav-links a:hover { color: #111; }
        .dashboard-nav-links .logout {
            padding: 12px 18px; background: #111; color: #fff;
        }
        .dashboard-nav-links .logout:hover { background: #333; color: #fff; }

        .dashboard-main {
            width: 88%; max-width: 1400px; margin: 0 auto; padding: 70px 0;
        }

        .dashboard-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            padding-bottom: 35px; border-bottom: 1px solid #ccc;
        }
        .dashboard-label {
            margin-bottom: 15px; font-size: 10px; font-weight: 700;
            letter-spacing: 0.2em; color: #777;
        }
        .dashboard-header h1 {
            margin: 0; font-size: clamp(45px, 6vw, 75px);
            line-height: 0.9; letter-spacing: -0.06em;
        }
        .user-info { text-align: right; }
        .user-info span {
            display: block; margin-bottom: 8px; font-size: 9px;
            letter-spacing: 0.15em; color: #777;
        }
        .user-info strong { font-size: 15px; }

        .welcome-section { padding: 65px 0; }
        .welcome-section p {
            margin: 0; font-size: 11px; letter-spacing: 0.08em; color: #777;
        }
        .welcome-section h2 {
            margin: 12px 0; font-size: 42px; letter-spacing: -0.04em;
        }
        .welcome-section span {
            font-size: 13px; line-height: 1.7; color: #777;
        }

        .dashboard-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;
        }
        .dashboard-card {
            min-height: 260px; padding: 30px; background: #fff;
            border: 1px solid #ddd; display: flex; flex-direction: column;
            transition: 0.25s ease;
        }
        .dashboard-card:hover { background: #111; color: #fff; }
        .dashboard-card small {
            font-size: 9px; font-weight: 700; letter-spacing: 0.15em; color: #888;
        }
        .dashboard-card h3 {
            margin-top: 55px; font-size: 25px; letter-spacing: -0.03em;
        }
        .dashboard-card p {
            margin-top: 12px; max-width: 360px; font-size: 12px;
            line-height: 1.7; color: #777;
        }
        .dashboard-card:hover p { color: #aaa; }
        .dashboard-card .card-link {
            margin-top: auto; padding-top: 25px; font-size: 9px;
            font-weight: 800; letter-spacing: 0.12em; color: #111;
            text-decoration: none;
        }
        .dashboard-card:hover .card-link { color: #fff; }

        .role-section {
            margin-top: 100px; padding-top: 50px; border-top: 1px solid #ccc;
        }
        .role-section-label {
            font-size: 9px; font-weight: 700; letter-spacing: 0.2em; color: #777;
        }
        .role-section h2 {
            margin-top: 12px; font-size: 36px; letter-spacing: -0.04em;
        }
        .role-section p {
            max-width: 700px; margin-top: 15px; font-size: 13px;
            line-height: 1.8; color: #777;
        }

        .dashboard-footer {
            margin-top: 100px; padding: 30px 0; border-top: 1px solid #ccc;
            display: flex; justify-content: space-between; font-size: 9px;
            letter-spacing: 0.1em; color: #777;
        }

        @media (max-width: 900px) {
            .dashboard-grid { grid-template-columns: repeat(2, 1fr); }
            .dashboard-header { display: block; }
            .user-info { margin-top: 25px; text-align: left; }
        }
        @media (max-width: 600px) {
            .dashboard-nav {
                padding: 20px; flex-direction: column; align-items: flex-start; gap: 20px;
            }
            .dashboard-nav-links {
                width: 100%; justify-content: space-between; gap: 10px;
            }
            .dashboard-main { width: 90%; padding: 45px 0; }
            .dashboard-header h1 { font-size: 48px; }
            .dashboard-grid { grid-template-columns: 1fr; }
            .dashboard-card { min-height: 230px; }
            .dashboard-footer { display: block; }
        }
    </style>
</head>
<body>
<div class="user-dashboard">

    <header class="dashboard-nav">
        <a href="index.php" class="dashboard-logo">SESH</a>
        <nav class="dashboard-nav-links">
            <a href="index.php">WEBSITE</a>
            <a href="logout.php" class="logout">LOGOUT</a>
        </nav>
    </header>

    <main class="dashboard-main">

        <section class="dashboard-header">
            <div>
                <p class="dashboard-label">SESSION MANAGEMENT SYSTEM</p>
                <h1>Dashboard</h1>
            </div>
            <div class="user-info">
                <span>LOGGED IN AS</span>
                <strong><?= htmlspecialchars($user['full_name']) ?></strong>
            </div>
        </section>

        <section class="welcome-section">
            <p>WELCOME BACK</p>
            <h2><?= htmlspecialchars($user['full_name']) ?></h2>
            <span>
                Manage your room bookings and session requests
                from one centralized dashboard.
            </span>
        </section>

        <section class="dashboard-grid">

            <?php if ($canBook): ?>
            <div class="dashboard-card">
                <small>01 / BOOKING</small>
                <h3>Book a Room</h3>
                <p>
                    Submit a new room booking request for a class,
                    laboratory, seminar, meeting, or other session.
                </p>
                <a href="modules/bookings/create.php" class="card-link">
                    REQUEST A ROOM →
                </a>
            </div>
            <?php endif; ?>

            <?php if ($canViewBookings): ?>
            <div class="dashboard-card">
                <small>02 / BOOKINGS</small>
                <h3>My Bookings</h3>
                <p>
                    View your submitted booking requests and check
                    their current approval status.
                </p>
                <a href="modules/bookings/list.php" class="card-link">
                    VIEW BOOKINGS →
                </a>
            </div>
            <?php endif; ?>

            <?php if ($canViewRooms): ?>
            <div class="dashboard-card">
                <small>03 / ROOMS</small>
                <h3>Available Rooms</h3>
                <p>
                    Browse rooms available for sessions and review
                    their capacity and current status.
                </p>
                <a href="modules/rooms/view.php" class="card-link">
                    VIEW ROOMS →
                </a>
            </div>
            <?php endif; ?>

        </section>

        <section class="role-section">
            <p class="role-section-label">ACCOUNT INFORMATION</p>
            <h2><?= htmlspecialchars($role) ?></h2>
            <p>
                You are logged into SESH as a
                <strong><?= htmlspecialchars($role) ?></strong>.
                Your access to system features is controlled using
                role-based access control.
            </p>
        </section>

        <footer class="dashboard-footer">
            <span>SESH — SESSION MANAGEMENT SYSTEM</span>
            <span>&copy; <?= date('Y') ?> BCA PROJECT</span>
        </footer>

    </main>
</div>
</body>
</html>