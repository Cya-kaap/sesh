<?php

require_once __DIR__ . '/../../includes/auth_check.php';
requireWritePermission(FEATURE_ROOMS_MANAGE);
header('Location: ' . basePath('admin/rooms/create.php'));
exit;

/**
 * SESH - Add Room (modules pathway)
 *
 * Purpose:
 *   Creates a new room record (name, capacity, status).
 *
 * Access (Corrected Unified Permission Matrix):
 *   "Manage Rooms & Fixed Attributes (CRUD)":
 *     Admin only has write access (Full Access). Coordinator, Faculty,
 *     Maintenance are Read Only; Student is No Access. Since adding a
 *     room is a write action, only Admin may reach this page.
 *
 * Security notes (this revision):
 *   - Matrix-driven gate via requireWritePermission(), replacing a prior
 *     requireRole(['Admin']) call that (per auth_check.php's own
 *     docblock) ran before config/db.php was loaded. requireWritePermission()
 *     has no $pdo dependency (see auth_check.php's file-level note), so
 *     it is safe to call before config/db.php regardless — but this file
 *     now loads config/db.php first anyway, for consistency with the
 *     bookings files and because the INSERT below needs $pdo.
 *   - CSRF token required on POST, matching every other mutating page in
 *     modules/.
 *   - Basic validation on capacity (positive integer) and status (must
 *     be one of the schema's actual ENUM values, fetched via
 *     getEnumValues() rather than hardcoded, so this file can't drift
 *     from the schema the way modules/bookings/delete.php's status bug
 *     did before the fix).
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireWritePermission(FEATURE_ROOMS_MANAGE);

$errors = [];
$form = [
    'name'     => '',
    'capacity' => '',
    'status'   => '',
];

$statusOptions = getEnumValues($pdo, 'rooms', 'status');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your session expired. Please try again.');
        header('Location: add.php');
        exit;
    }

    foreach ($form as $key => $_) {
        $form[$key] = trim($_POST[$key] ?? '');
    }

    $name     = $form['name'];
    $capacity = (int) $form['capacity'];
    $status   = $form['status'];

    if ($name === '') {
        $errors[] = 'Please enter a room name.';
    }

    if ($capacity <= 0) {
        $errors[] = 'Capacity must be a positive number.';
    }

    if ($status === '' || !in_array($status, $statusOptions, true)) {
        $errors[] = 'Please select a valid status.';
    }

    // Uniqueness check, since bookings/edit.php and create.php both rely
    // on room name being a meaningful label in <option> dropdowns.
    if (empty($errors)) {
        $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE name = :name");
        $dupStmt->execute([':name' => $name]);
        if ((int) $dupStmt->fetchColumn() > 0) {
            $errors[] = 'A room with this name already exists.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO rooms (name, capacity, status)
            VALUES (:name, :capacity, :status)
        ");
        $stmt->execute([
            ':name'     => $name,
            ':capacity' => $capacity,
            ':status'   => $status,
        ]);

        setFlash('success', 'Room added successfully.');
        header('Location: list.php');
        exit;
    }
}

$csrfToken = generateCsrfToken();
$flash     = getFlash();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Room | SESH</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
<div class="container">

    <h1>Add Room</h1>

    <p><a href="list.php">← Back to Rooms</a></p>

    <?php foreach ($flash as $type => $msg): ?>
        <div class="<?= $type === 'success' ? 'success-message' : 'form-error' ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endforeach; ?>

    <?php foreach ($errors as $error): ?>
        <div class="form-error"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="add.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <label for="name">Room Name</label>
        <input type="text" id="name" name="name" required
               value="<?= htmlspecialchars($form['name']) ?>">

        <label for="capacity">Capacity</label>
        <input type="number" id="capacity" name="capacity" min="1" required
               value="<?= htmlspecialchars($form['capacity']) ?>">

        <label for="status">Status</label>
        <select id="status" name="status" required>
            <option value="">-- Select Status --</option>
            <?php foreach ($statusOptions as $opt): ?>
                <option value="<?= htmlspecialchars($opt) ?>"
                    <?= $form['status'] === $opt ? 'selected' : '' ?>>
                    <?= htmlspecialchars($opt) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Add Room</button>
    </form>

</div>
</body>
</html>