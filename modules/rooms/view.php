<?php

/**
 * SESH - View Room (modules pathway)
 *
 * Purpose:
 *   Read-only detail view for a single room (name, capacity, resources, status).
 *
 * Access:
 *   The RBAC audit specifically flagged this file (Section D, "Manage
 *   Rooms & Fixed Attributes" row) as open to ANY logged-in user with no
 *   per-role distinction — meaning Student, who the matrix marks No
 *   Access for this feature, could previously reach it. Fixed here:
 *     Admin, Coordinator, Faculty, Maintenance - can view (their tiers
 *       are Full Access or Read Only, both of which include viewing)
 *     Student - No Access, same as list.php
 *
 * Security notes (this revision):
 *   - Matrix-driven gate via requirePermission(FEATURE_ROOMS_MANAGE),
 *     matching list.php exactly so a user who can see a room in the list
 *     can also open its detail view, and a Student blocked from list.php
 *     is equally blocked here even if they guess a room id directly.
 *   - No write actions on this page regardless of role — Edit/Delete are
 *     reached only from list.php for roles canWrite() admits.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/room_helpers.php';

requirePermission(FEATURE_ROOMS_MANAGE);

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Invalid room.');
    header('Location: list.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = :id");
$stmt->execute([':id' => $id]);
$room = $stmt->fetch();

if (!$room) {
    setFlash('error', 'Room not found.');
    header('Location: list.php');
    exit;
}

$mediaStmt = $pdo->prepare('SELECT media_type, file_path FROM room_media WHERE room_id = :room_id ORDER BY id');
$mediaStmt->execute([':room_id' => $id]);
$media = $mediaStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($room['name']) ?> | SESH</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
<div class="container">

    <h1><?= htmlspecialchars($room['name']) ?></h1>

    <p><a href="list.php">← Back to Rooms</a></p>

    <div class="card">
        <p><strong>Room code:</strong> <?= htmlspecialchars($room['room_code']) ?></p>
        <p><strong>Display name:</strong> <?= htmlspecialchars($room['name'] ?: 'Not set') ?></p>
        <p><strong>Building:</strong> <?= htmlspecialchars($room['building'] ?: 'Not set') ?></p>
        <p><strong>Floor:</strong> <?= htmlspecialchars($room['floor'] ?: 'Not set') ?></p>
        <p><strong>Type:</strong> <?= htmlspecialchars($room['type']) ?></p>
        <p><strong>Capacity:</strong> <?= (int) $room['capacity'] ?></p>
        <p><strong>Examination capacity:</strong> <?= $room['exam_capacity'] !== null ? (int) $room['exam_capacity'] : 'Not set' ?></p>
        <p><strong>Primary department:</strong> <?= htmlspecialchars($room['primary_department']) ?></p>
        <p><strong>Status:</strong> <?= htmlspecialchars($room['status']) ?></p>
        <p><strong>Projector:</strong> <?= (int) $room['has_projector'] === 1 ? 'Available' : 'Not available' ?></p>
        <p><strong>Whiteboard:</strong> <?= (int) $room['has_whiteboard'] === 1 ? 'Available' : 'Not available' ?></p>
        <p><strong>Air-conditioning:</strong> <?= (int) $room['has_ac'] === 1 ? 'Available' : 'Not available' ?></p>
        <p><strong>Amenities:</strong> <?= htmlspecialchars(implode(', ', roomDecodeValues($room['amenities']))) ?: 'None listed' ?></p>
        <p><strong>Accessibility:</strong> <?= htmlspecialchars(implode(', ', roomDecodeValues($room['accessibility']))) ?: 'None listed' ?></p>
        <p><strong>Notes:</strong> <?= nl2br(htmlspecialchars($room['notes'] ?: 'None listed')) ?></p>
    </div>

    <?php if ($media): ?>
        <div class="card">
            <h2>Room Media</h2>
            <?php foreach ($media as $item): ?>
                <figure>
                    <img src="../../<?= htmlspecialchars($item['file_path']) ?>" alt="<?= htmlspecialchars($item['media_type'] === 'floor_plan' ? 'Floor plan' : 'Room photo') ?>" style="max-width:100%;height:auto;">
                    <figcaption><?= htmlspecialchars($item['media_type'] === 'floor_plan' ? 'Floor plan' : 'Room photo') ?></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (canWrite(FEATURE_ROOMS_MANAGE)): ?>
        <p><a href="edit.php?id=<?= (int) $room['id'] ?>">Edit this room</a></p>
    <?php endif; ?>

</div>
</body>
</html>