<?php

/**
 * SESH - Admin Dashboard
 *
 * Purpose:
 *   High-level system statistics overview for Admin users.
 *
 * Requires tables:
 *   - users
 *   - rooms
 *   - bookings
 *
 * Access:
 *   Admin only (enforced via requireRole()).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['Admin']);

$user = currentUser();

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

// Total rooms
$totalRooms = (int) $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();

// Total users (excluding the logged-in admin is not necessary, count all)
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Pending bookings awaiting approval
$pendingBookings = (int) $pdo->query(
    "SELECT COUNT(*) FROM bookings WHERE status = 'Pending'"
)->fetchColumn();

// Bookings approved today
$approvedToday = (int) $pdo->query("
    SELECT COUNT(*) FROM bookings
    WHERE status = 'Approved' AND DATE(updated_at) = CURDATE()
")->fetchColumn();

// Rooms currently under maintenance / unavailable
$roomsUnavailable = (int) $pdo->query(
    "SELECT COUNT(*) FROM rooms WHERE status != 'Available'"
)->fetchColumn();

// Active users by role (for the breakdown table)
$usersByRole = $pdo->query("
    SELECT role, COUNT(*) AS total
    FROM users
    WHERE status = 'Active'
    GROUP BY role
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | SESH</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        /* =========================================================
           ADMIN DASHBOARD
        ========================================================= */

        .admin-dashboard {
            min-height: 100vh;
            background: #f4f3ef;
            color: #111;
        }

        .admin-nav {
            min-height: 80px;
            padding: 0 6vw;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #d5d5d0;
            background: #f4f3ef;
        }

        .admin-logo {
            font-size: 32px;
            font-weight: 900;
            letter-spacing: -0.08em;
            color: #111;
            text-decoration: none;
        }

        .admin-nav-links {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .admin-nav-links a {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: #555;
            text-decoration: none;
        }

        .admin-nav-links a:hover {
            color: #111;
        }

        .admin-nav-links .logout {
            padding: 12px 18px;
            background: #111;
            color: #fff;
        }

        .admin-nav-links .logout:hover {
            background: #333;
            color: #fff;
        }

        .admin-main {
            width: 88%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 70px 0;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-bottom: 35px;
            border-bottom: 1px solid #ccc;
        }

        .admin-label {
            margin-bottom: 15px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.2em;
            color: #777;
        }

        .admin-header h1 {
            margin: 0;
            font-size: clamp(45px, 6vw, 75px);
            line-height: 0.9;
            letter-spacing: -0.06em;
        }

        .admin-user-info {
            text-align: right;
        }

        .admin-user-info span {
            display: block;
            margin-bottom: 8px;
            font-size: 9px;
            letter-spacing: 0.15em;
            color: #777;
        }

        .admin-user-info strong {
            font-size: 15px;
        }

        /* STAT CARDS */

        .stat-grid {
            margin-top: 60px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
        }

        .stat-card {
            padding: 30px;
            background: #fff;
            border: 1px solid #ddd;
        }

        .stat-card small {
            display: block;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.15em;
            color: #888;
        }

        .stat-card .stat-value {
            margin-top: 20px;
            font-size: 46px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        /* MODULE LINKS */

        .module-grid {
            margin-top: 70px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .module-card {
            min-height: 200px;
            padding: 30px;
            background: #fff;
            border: 1px solid #ddd;
            display: flex;
            flex-direction: column;
            transition: 0.25s ease;
            text-decoration: none;
            color: inherit;
        }

        .module-card:hover {
            background: #111;
            color: #fff;
        }

        .module-card small {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.15em;
            color: #888;
        }

        .module-card h3 {
            margin-top: 40px;
            font-size: 22px;
            letter-spacing: -0.03em;
        }

        .module-card p {
            margin-top: 10px;
            font-size: 12px;
            line-height: 1.7;
            color: #777;
        }

        .module-card:hover p {
            color: #aaa;
        }

        /* ROLE BREAKDOWN */

        .role-breakdown {
            margin-top: 100px;
            padding-top: 50px;
            border-top: 1px solid #ccc;
        }

        .role-breakdown-label {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.2em;
            color: #777;
        }

        .role-breakdown h2 {
            margin-top: 12px;
            font-size: 36px;
            letter-spacing: -0.04em;
        }

        .role-table {
            margin-top: 30px;
            width: 100%;
            border-collapse: collapse;
        }

        .role-table th,
        .role-table td {
            padding: 14px 0;
            text-align: left;
            border-bottom: 1px solid #ddd;
            font-size: 13px;
        }

        .role-table th {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.1em;
            color: #777;
        }

        /* TABLET */

        @media (max-width: 900px) {
            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .module-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .admin-header {
                display: block;
            }
            .admin-user-info {
                margin-top: 25px;
                text-align: left;
            }
        }

        /* MOBILE */

        @media (max-width: 600px) {
            .admin-nav {
                padding: 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            .admin-nav-links {
                width: 100%;
                justify-content: space-between;
            }
            .admin-main {
                width: 90%;
                padding: 45px 0;
            }
            .stat-grid,
            .module-grid {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>

<body>

<div class="admin-dashboard">

    <!-- NAVIGATION -->

    <header class="admin-nav">

        <a href="index.php" class="admin-logo">SESH</a>

        <nav class="admin-nav-links">
            <a href="../index.php">WEBSITE</a>
            <a href="../logout.php" class="logout">LOGOUT</a>
        </nav>

    </header>


    <main class="admin-main">

        <!-- HEADER -->

        <section class="admin-header">

            <div>
                <p class="admin-label">SESSION MANAGEMENT SYSTEM</p>
                <h1>Admin</h1>
            </div>

            <div class="admin-user-info">
                <span>LOGGED IN AS</span>
                <strong><?= htmlspecialchars($user['full_name']) ?></strong>
            </div>

        </section>


        <!-- STATISTICS -->

        <section class="stat-grid">

            <div class="stat-card">
                <small>TOTAL ROOMS</small>
                <div class="stat-value"><?= $totalRooms ?></div>
            </div>

            <div class="stat-card">
                <small>TOTAL USERS</small>
                <div class="stat-value"><?= $totalUsers ?></div>
            </div>

            <div class="stat-card">
                <small>PENDING BOOKINGS</small>
                <div class="stat-value"><?= $pendingBookings ?></div>
            </div>

            <div class="stat-card">
                <small>ROOMS UNAVAILABLE</small>
                <div class="stat-value"><?= $roomsUnavailable ?></div>
            </div>

            <div class="stat-card">
                <small>APPROVED TODAY</small>
                <div class="stat-value"><?= $approvedToday ?></div>
            </div>

        </section>


        <!-- MODULES -->

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

        </section>


        <!-- ROLE BREAKDOWN -->

        <section class="role-breakdown">

            <p class="role-breakdown-label">ACCOUNT BREAKDOWN</p>
            <h2>Active Users by Role</h2>

            <table class="role-table">

                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Active Users</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($usersByRole)): ?>
                        <tr>
                            <td colspan="2">No active users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usersByRole as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['role']) ?></td>
                                <td><?= (int) $row['total'] ?></td>
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