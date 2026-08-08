<?php

/**
 * SESH - Rooms: Browse Available Rooms
 *
 * Purpose:
 *   Lets any logged-in user (Faculty, Coordinator, etc.) browse all
 *   rooms and see their type, capacity, and current status, with
 *   optional filtering. Linked from the "Available Rooms" card on
 *   the main dashboard.
 *
 * Requires tables:
 *   - rooms
 *
 * Access:
 *   Any authenticated user (read-only - no booking actions here).
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();

$statusOptions = getEnumValues($pdo, 'rooms', 'status');
$typeOptions   = getEnumValues($pdo, 'rooms', 'type');

$filterStatus = $_GET['status'] ?? '';
$filterType   = $_GET['type']   ?? '';

$where  = [];
$params = [];

if ($filterStatus !== '' && in_array($filterStatus, $statusOptions, true)) {
    $where[] = 'status = :status';
    $params[':status'] = $filterStatus;
}

if ($filterType !== '' && in_array($filterType, $typeOptions, true)) {
    $where[] = 'type = :type';
    $params[':type'] = $filterType;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("
    SELECT id, name, type, capacity, status
    FROM rooms
    $whereSql
    ORDER BY name ASC
");
$stmt->execute($params);
$rooms = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Rooms | SESH</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .rooms-page { min-height: 100vh; background: #f4f3ef; color: #111; }

        .rooms-nav {
            min-height: 80px; padding: 0 6vw; display: flex; align-items: center;
            justify-content: space-between; border-bottom: 1px solid #d5d5d0; background: #f4f3ef;
        }
        .rooms-logo { font-size: 28px; font-weight: 900; letter-spacing: -0.08em; color: #111; text-decoration: none; }
        .rooms-nav-links a { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: #555; text-decoration: none; }

        .rooms-content { width: 90%; max-width: 1300px; margin: 0 auto; padding: 60px 0; }
        .page-label { margin-bottom: 12px; font-size: 10px; font-weight: 700; letter-spacing: 0.2em; color: #777; }
        .rooms-content h1 { margin: 0 0 10px 0; font-size: clamp(40px, 5vw, 60px); letter-spacing: -0.05em; }
        .rooms-content > p.intro { margin: 0; font-size: 13px; color: #777; max-width: 600px; }

        .filter-bar { margin-top: 35px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-bar select {
            padding: 10px 12px; border: 1px solid #ccc; background: #fff; font-size: 13px;
        }
        .filter-bar a.clear {
            padding: 10px 16px; font-size: 10px; font-weight: 700; letter-spacing: 0.08em;
            background: #ddd; color: #111; text-decoration: none;
        }

        .rooms-grid {
            margin-top: 35px; display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 15px;
        }
        .room-card {
            padding: 26px; background: #fff; border: 1px solid #ddd; display: flex; flex-direction: column; gap: 10px;
        }
        .room-card .room-name { font-size: 19px; font-weight: 800; letter-spacing: -0.03em; }
        .room-card .room-type { font-size: 10px; font-weight: 700; letter-spacing: 0.1em; color: #888; text-transform: uppercase; }
        .room-card .room-capacity { font-size: 13px; color: #555; }

        .badge {
            align-self: flex-start; display: inline-block; padding: 5px 10px; font-size: 9px;
            font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;
            background: #eee; color: #555; /* default fallback for any unmapped status */
        }
        .badge-available { background: #d4edda; color: #155724; }
        .badge-booked, .badge-occupied { background: #f8d7da; color: #721c24; }
        .badge-maintenance, .badge-unavailable { background: #e2e3e5; color: #383d41; }

        .rooms-empty { margin-top: 35px; padding: 24px; background: #fff; border: 1px solid #ddd; font-size: 13px; color: #777; }

        .sr-only {
            position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
            overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
        }

        @media (max-width: 600px) {
            .rooms-content { width: 90%; padding: 45px 0; }
        }
    </style>
</head>
<body>
<div class="rooms-page">

    <header class="rooms-nav">
        <a href="../../dashboard.php" class="rooms-logo">SESH</a>
        <nav class="rooms-nav-links">
            <a href="../../dashboard.php">← BACK TO DASHBOARD</a>
        </nav>
    </header>

    <main class="rooms-content">

        <p class="page-label">ROOM DIRECTORY</p>
        <h1>Available Rooms</h1>
        <p class="intro">Browse all rooms, their type, capacity, and current status. This is a read-only view — to reserve a room, use the booking form from your dashboard.</p>

        <form method="GET" action="view.php" class="filter-bar">

            <label for="type" class="sr-only">Filter by room type</label>
            <select id="type" name="type" onchange="this.form.submit()" aria-label="Filter by room type">
                <option value="">All Types</option>
                <?php foreach ($typeOptions as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>" <?= $filterType === $opt ? 'selected' : '' ?>>
                        <?= htmlspecialchars($opt) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="status" class="sr-only">Filter by room status</label>
            <select id="status" name="status" onchange="this.form.submit()" aria-label="Filter by room status">
                <option value="">All Statuses</option>
                <?php foreach ($statusOptions as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>" <?= $filterStatus === $opt ? 'selected' : '' ?>>
                        <?= htmlspecialchars($opt) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <noscript><button type="submit">FILTER</button></noscript>

            <?php if ($filterStatus !== '' || $filterType !== ''): ?>
                <a href="view.php" class="clear">CLEAR FILTERS</a>
            <?php endif; ?>

        </form>

        <?php if (empty($rooms)): ?>

            <div class="rooms-empty">No rooms match these filters.</div>

        <?php else: ?>

            <div class="rooms-grid">
                <?php foreach ($rooms as $r): ?>
                    <?php
                        // Build a safe CSS class from the status text: lowercase,
                        // and collapse anything that isn't a-z/0-9 into a single
                        // hyphen. This avoids emitting a malformed class name if
                        // a status value ever contains punctuation or is renamed
                        // in the database (e.g. "Under Maintenance" -> under-maintenance).
                        $badgeClass = 'badge-' . strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $r['status']), '-'));
                    ?>
                    <div class="room-card">
                        <span class="badge <?= htmlspecialchars($badgeClass) ?>"><?= htmlspecialchars($r['status']) ?></span>
                        <div class="room-name"><?= htmlspecialchars($r['name']) ?></div>
                        <div class="room-type"><?= htmlspecialchars($r['type']) ?></div>
                        <div class="room-capacity">Capacity: <?= (int) $r['capacity'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </main>

</div>
</body>
</html>