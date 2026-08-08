<?php

/**
 * SESH - Coordinator Dashboard
 *
 * Purpose:
 *   Departmental schedule overview and quick links to booking engines.
 *
 * Requires tables:
 *   - bookings, rooms, programmes, semesters
 *
 * Access:
 *   Coordinator only.
 *
 * Scope note:
 *   A Coordinator's "managed programme" is derived by matching
 *   users.department to programmes.name (e.g. department "BCA" maps
 *   to the programme named "BCA"). There is no dedicated link table
 *   for this yet — flag if a Coordinator should manage more than one
 *   programme at once.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['Coordinator']);

$user = currentUser();

// Pull department fresh from the DB on every load - see getCurrentDepartment()
// in functions.php for why this can't come from the session.
$department = getCurrentDepartment($pdo, (int) $user['id']);

// Resolve the coordinator's programme scope from their department string.
$programmeStmt = $pdo->prepare("SELECT id, name FROM programmes WHERE name = :dept LIMIT 1");
$programmeStmt->execute([':dept' => $department ?? '']);
$programme = $programmeStmt->fetch();

$scopeWarning = null;
if (!$programme) {
    $scopeWarning = 'Your account department ("' . htmlspecialchars($department ?? '') . '") does not match any configured programme. Contact an Admin to align your department with a programme name.';
}

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$pendingCount = 0;
$upcomingExams = 0;
$upcomingClasses = 0;

if ($programme) {

    $pendingStmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings
        WHERE programme_id = :pid AND status = 'Pending'
    ");
    $pendingStmt->execute([':pid' => $programme['id']]);
    $pendingCount = (int) $pendingStmt->fetchColumn();

    $examStmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings
        WHERE programme_id = :pid AND type = 'Exam'
          AND date >= CURDATE() AND status IN ('Pending', 'Approved')
    ");
    $examStmt->execute([':pid' => $programme['id']]);
    $upcomingExams = (int) $examStmt->fetchColumn();

    $classStmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings
        WHERE programme_id = :pid AND type = 'Class'
          AND date >= CURDATE() AND status IN ('Pending', 'Approved')
    ");
    $classStmt->execute([':pid' => $programme['id']]);
    $upcomingClasses = (int) $classStmt->fetchColumn();
}

$availableRoomsList = $pdo->query("
    SELECT id, name, type, capacity FROM rooms
    WHERE status = 'Available'
    ORDER BY name ASC
")->fetchAll();

$availableRooms = count($availableRoomsList);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coordinator Dashboard | SESH</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .coord-page { min-height: 100vh; background: #f4f3ef; color: #111; }
        .coord-nav { min-height: 80px; padding: 0 6vw; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #d5d5d0; background: #f4f3ef; }
        .coord-logo { font-size: 28px; font-weight: 900; letter-spacing: -0.08em; color: #111; text-decoration: none; }
        .coord-nav-links { display: flex; align-items: center; gap: 25px; }
        .coord-nav-links a { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: #555; text-decoration: none; }
        .coord-nav-links a:hover { color: #111; }
        .coord-nav-links .logout { padding: 10px 16px; background: #111; color: #fff; }

        .coord-content { width: 88%; max-width: 1300px; margin: 0 auto; padding: 60px 0; }
        .page-label { margin-bottom: 12px; font-size: 10px; font-weight: 700; letter-spacing: 0.2em; color: #777; }
        .coord-content h1 { margin: 0; font-size: clamp(40px, 5vw, 60px); letter-spacing: -0.05em; }

        .warning-box { margin-top: 30px; padding: 16px 18px; background: #fff3cd; color: #856404; font-size: 13px; }

        .stat-grid { margin-top: 50px; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; }
        .stat-card { padding: 30px; background: #fff; border: 1px solid #ddd; }
        .stat-card small { display: block; font-size: 9px; font-weight: 700; letter-spacing: 0.15em; color: #888; }
        .stat-card .stat-value { margin-top: 20px; font-size: 42px; font-weight: 800; letter-spacing: -0.03em; }

        .module-grid { margin-top: 60px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .module-card { min-height: 180px; padding: 30px; background: #fff; border: 1px solid #ddd; display: flex; flex-direction: column; transition: 0.25s ease; text-decoration: none; color: inherit; }
        .module-card:hover { background: #111; color: #fff; }
        .module-card small { font-size: 9px; font-weight: 700; letter-spacing: 0.15em; color: #888; }
        .module-card h3 { margin-top: 30px; font-size: 22px; letter-spacing: -0.03em; }
        .module-card p { margin-top: 10px; font-size: 12px; line-height: 1.7; color: #777; }
        .module-card:hover p { color: #aaa; }

        @media (max-width: 700px) {
            .module-grid { grid-template-columns: 1fr; }
        }

        .rooms-section { margin-top: 60px; }
        .rooms-section h2 { margin: 0 0 20px 0; font-size: 22px; letter-spacing: -0.03em; }
        .rooms-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
        .room-card { padding: 20px; background: #fff; border: 1px solid #ddd; }
        .room-card .room-name { font-size: 16px; font-weight: 700; letter-spacing: -0.02em; }
        .room-card .room-meta { margin-top: 8px; font-size: 11px; color: #777; }
        .rooms-empty { padding: 20px; background: #fff; border: 1px solid #ddd; font-size: 13px; color: #777; }
    </style>
</head>
<body>
<div class="coord-page">

    <header class="coord-nav">
        <a href="index.php" class="coord-logo">SESH</a>
        <nav class="coord-nav-links">
            <a href="../index.php">WEBSITE</a>
            <a href="../logout.php" class="logout">LOGOUT</a>
        </nav>
    </header>

    <main class="coord-content">

        <p class="page-label">SESSION MANAGEMENT SYSTEM</p>
        <h1>Coordinator</h1>

        <?php if ($scopeWarning): ?>
            <div class="warning-box"><?= $scopeWarning ?></div>
        <?php endif; ?>

        <section class="stat-grid">

            <div class="stat-card">
                <small>PENDING APPROVALS</small>
                <div class="stat-value"><?= $pendingCount ?></div>
            </div>

            <div class="stat-card">
                <small>UPCOMING EXAMS</small>
                <div class="stat-value"><?= $upcomingExams ?></div>
            </div>

            <div class="stat-card">
                <small>UPCOMING CLASSES</small>
                <div class="stat-value"><?= $upcomingClasses ?></div>
            </div>

            <div class="stat-card">
                <small>ROOMS AVAILABLE</small>
                <div class="stat-value"><?= $availableRooms ?></div>
            </div>

        </section>

        <section class="module-grid">

            <a href="book_exam.php" class="module-card">
                <small>01 / EXAM BOOKING</small>
                <h3>Book an Examination</h3>
                <p>Schedule an exam with invigilator, subject and capacity validation.</p>
            </a>

            <a href="bookings.php" class="module-card">
                <small>02 / SCHEDULE</small>
                <h3>Department Bookings</h3>
                <p>View, edit or cancel bookings within your programme scope.</p>
            </a>

        </section>

        <section class="rooms-section">
            <h2>Available Rooms</h2>

            <?php if (empty($availableRoomsList)): ?>
                <div class="rooms-empty">No rooms are currently marked "Available".</div>
            <?php else: ?>
                <div class="rooms-grid">
                    <?php foreach ($availableRoomsList as $r): ?>
                        <div class="room-card">
                            <div class="room-name"><?= htmlspecialchars($r['name']) ?></div>
                            <div class="room-meta"><?= htmlspecialchars($r['type']) ?> &middot; capacity <?= (int) $r['capacity'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </main>

</div>
</body>
</html>