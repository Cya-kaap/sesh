<?php

/**
 * SESH - Edit Room (modules pathway)
 *
 * Purpose:
 *   Edits an existing room's name, capacity, and status.
 *
 * Access:
 *   Same reasoning as add.php: "Manage Rooms & Fixed Attributes" write
 *   access is Admin-only (Full Access); Coordinator/Faculty/Maintenance
 *   are Read Only and must not reach this page's POST handler.
 *
 *   Note the split from "Manage Room Operational Status" (a separate
 *   matrix row where Maintenance also has Full Access, status-only).
 *   This file bundles status into the same Admin-only form as name/
 *   capacity, which the RBAC audit already flagged as a gap: Maintenance
 *   has no way to toggle a room's status here without also being able to
 *   rename it or change its capacity. Not fixed in this pass — flagged
 *   in the docblock so the next migration step is explicit: split status
 *   editing into its own FEATURE_ROOMS_STATUS-gated page/action so
 *   Maintenance can be granted that alone.
 *
 * Security notes (this revision):
 *   - Matrix-driven gate via requireWritePermission(FEATURE_ROOMS_MANAGE).
 *   - CSRF required on POST.
 *   - Reads id from $_GET on GET, $_POST on POST only (same convention
 *     as modules/bookings/edit.php, to avoid the original bookings/
 *     edit.php bug of reading from either superglobal on every request).
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireWritePermission(FEATURE_ROOMS_MANAGE);

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$id     = $isPost ? (int) ($_POST['id'] ?? 0) : (int) ($_GET['id'] ?? 0);

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

$statusOptions = getEnumValues($pdo, 'rooms', 'status');
$errors        = [];

if ($isPost) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your session expired. Please try again.');
        header('Location: list.php');
        exit;
    }

    $name     = trim($_POST['name'] ?? '');
    $capacity = (int) ($_POST['capacity'] ?? 0);
    $status   = trim($_POST['status'] ?? '');

    if ($name === '') {
        $errors[] = 'Please enter a room name.';
    }

    if ($capacity <= 0) {
        $errors[] = 'Capacity must be a positive number.';
    }

    if ($status === '' || !in_array($status, $statusOptions, true)) {
        $errors[] = 'Please select a valid status.';
    }

    if (empty($errors)) {
        $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE name = :name AND id != :id");
        $dupStmt->execute([':name' => $name, ':id' => $id]);
        if ((int) $dupStmt->fetchColumn() > 0) {
            $errors[] = 'Another room already uses this name.';
        }
    }

    if (empty($errors)) {
        $update = $pdo->prepare("
            UPDATE rooms
            SET name = :name, capacity = :capacity, status = :status
            WHERE id = :id
        ");
        $update->execute([
            ':name'     => $name,
            ':capacity' => $capacity,
            ':status'   => $status,
            ':id'       => $id,
        ]);

        setFlash('success', 'Room updated successfully.');
        header('Location: list.php');
        exit;
    }

    $room['name']     = $name;
    $room['capacity'] = $capacity;
    $room['status']   = $status;
}

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Room | SESH</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
<div class="container">

    <h1>Edit Room</h1>

    <p><a href="list.php">← Back to Rooms</a></p>

    <?php foreach ($errors as $error): ?>
        <div class="form-error"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="edit.php">
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <label for="name">Room Name</label>
        <input type="text" id="name" name="name" required
               value="<?= htmlspecialchars($room['name']) ?>">

        <label for="capacity">Capacity</label>
        <input type="number" id="capacity" name="capacity" min="1" required
               value="<?= (int) $room['capacity'] ?>">

        <label for="status">Status</label>
        <select id="status" name="status" required>
            <?php foreach ($statusOptions as $opt): ?>
                <option value="<?= htmlspecialchars($opt) ?>"
                    <?= $room['status'] === $opt ? 'selected' : '' ?>>
                    <?= htmlspecialchars($opt) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Update Room</button>
    </form>

</div>
</body>
</html>