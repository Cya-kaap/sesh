<?php

/**
 * SESH - Rooms List (modules pathway)
 *
 * Purpose:
 *   Central listing page for rooms. Admin sees full CRUD actions;
 *   Coordinator/Faculty/Maintenance see the list read-only (per their
 *   matrix tier on "Manage Rooms & Fixed Attributes"); Student has no
 *   access to this page at all.
 *
 * Access:
 *   "Manage Rooms & Fixed Attributes (CRUD)":
 *     Admin       - Full Access  (sees Add / Edit / Delete)
 *     Coordinator - Read Only    (sees the table only)
 *     Faculty     - Read Only    (sees the table only)
 *     Maintenance - Read Only    (sees the table; plus operational status
 *                  dropdown control per FEATURE_ROOMS_STATUS rights)
 *     Student     - No Access
 *
 * Security notes:
 *   - Matrix-driven gate via requirePermission(FEATURE_ROOMS_MANAGE),
 *     which admits anyone with *any* access to the feature (Admin,
 *     Coordinator, Faculty, Maintenance) and denies Student.
 *   - Row-level actions are additionally gated by canWrite() checks,
 *     supporting both full room management and status-only controls.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requirePermission(FEATURE_ROOMS_MANAGE);

$user   = currentUser();
$role   = $user['role'];
$canWriteRooms = canWrite(FEATURE_ROOMS_MANAGE);
$canWriteStatus = canWrite(FEATURE_ROOMS_STATUS);

$rooms = $pdo->query("
    SELECT id, room_code, name, type, capacity, exam_capacity, primary_department, amenities, accessibility, status
    FROM rooms
    ORDER BY name
")->fetchAll();

$statusOptions = getEnumValues($pdo, 'rooms', 'status');

$flash = getFlash();

// Only needed if a delete/status action is rendered inline below.
$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rooms | SESH</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
<div class="container">

    <h1>Rooms</h1>

    <p>

        <?php if ($canWriteRooms): ?>
            <a href="add.php">+ Add Room</a>
            |
        <?php endif; ?>

        <?php if ($role === ROLE_ADMIN): ?>
            <a href="../../admin/">← Admin Dashboard</a>
        <?php elseif ($role === ROLE_COORDINATOR): ?>
            <a href="../../coordinator/">← Coordinator Dashboard</a>
        <?php elseif ($role === ROLE_FACULTY): ?>
            <a href="../../faculty/">← Faculty Dashboard</a>
        <?php else: ?>
            <a href="../../dashboard.php">← Dashboard</a>
        <?php endif; ?>

    </p>

    <?php foreach ($flash as $type => $message): ?>
        <div class="<?= $type === 'success' ? 'success-message' : 'error-message' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endforeach; ?>

    <?php if (empty($rooms)): ?>

        <p>No rooms found.</p>

    <?php else: ?>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code / Name</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <?php if ($canWriteRooms || $canWriteStatus): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $room): ?>
                        <tr>
                            <td><?= (int) $room['id'] ?></td>
                            <td><a href="view.php?id=<?= (int) $room['id'] ?>"><?= htmlspecialchars($room['room_code']) ?></a><br><small><?= htmlspecialchars($room['name'] ?: '') ?></small></td>
                            <td><?= htmlspecialchars($room['type']) ?></td>
                            <td><?= (int) $room['capacity'] ?><?php if ($room['exam_capacity'] !== null): ?><br><small>Exam: <?= (int) $room['exam_capacity'] ?></small><?php endif; ?></td>
                            <td><?= htmlspecialchars($room['status']) ?></td>

                            <?php if ($canWriteRooms || $canWriteStatus): ?>
                                <td>
                                    <?php if ($canWriteRooms): ?>
                                        <a href="edit.php?id=<?= (int) $room['id'] ?>">Edit</a>
                                        |
                                        <form
                                            method="POST"
                                            action="delete.php"
                                            style="display:inline"
                                            onsubmit="return confirm('Delete this room? This cannot be undone.');"
                                        >
                                            <input type="hidden" name="id" value="<?= (int) $room['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <button type="submit" class="btn-reject">Delete</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($canWriteStatus): ?>
                                        <?php if ($canWriteRooms): ?> | <?php endif; ?>
                                        <form method="POST" action="status.php" style="display:inline">
                                            <input type="hidden" name="id" value="<?= (int) $room['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <select name="status" onchange="this.form.submit()">
                                                <?php foreach ($statusOptions as $opt): ?>
                                                    <option value="<?= htmlspecialchars($opt) ?>"
                                                        <?= $room['status'] === $opt ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($opt) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>

                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>
</body>
</html>