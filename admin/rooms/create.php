<?php

/**
 * SESH - Admin: Create Room
 *
 * Purpose:
 *   Form + handler to add a new room/lab record.
 *
 * Requires tables:
 *   - rooms
 *
 * Access:
 *   Admin only.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['Admin']);

$user = currentUser();

$typeOptions   = getEnumValues($pdo, 'rooms', 'type');
$statusOptions = getEnumValues($pdo, 'rooms', 'status');

$errors = [];

// Preserve submitted values on validation failure
$values = [
    'name'           => '',
    'type'           => $typeOptions[0]   ?? '',
    'capacity'       => '',
    'has_projector'  => false,
    'has_whiteboard' => false,
    'has_ac'         => false,
    'status'         => $statusOptions[0] ?? '',
];

/*
|--------------------------------------------------------------------------
| Handle Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        $errors[] = 'Your session expired. Please try again.';

    } else {

        $values['name']           = trim($_POST['name'] ?? '');
        $values['type']           = $_POST['type'] ?? '';
        $values['capacity']       = trim($_POST['capacity'] ?? '');
        $values['has_projector']  = isset($_POST['has_projector']);
        $values['has_whiteboard'] = isset($_POST['has_whiteboard']);
        $values['has_ac']         = isset($_POST['has_ac']);
        $values['status']         = $_POST['status'] ?? '';

        // Validation

        if ($values['name'] === '') {
            $errors[] = 'Room name is required.';
        } elseif (mb_strlen($values['name']) > 100) {
            $errors[] = 'Room name must be 100 characters or fewer.';
        }

        if (!in_array($values['type'], $typeOptions, true)) {
            $errors[] = 'Please select a valid room type.';
        }

        if ($values['capacity'] === '' || !ctype_digit((string) $values['capacity']) || (int) $values['capacity'] < 1) {
            $errors[] = 'Capacity must be a whole number of 1 or more.';
        } elseif ((int) $values['capacity'] > 65535) {
            $errors[] = 'Capacity is too large.';
        }

        if (!in_array($values['status'], $statusOptions, true)) {
            $errors[] = 'Please select a valid status.';
        }

        // Duplicate name check
        if (empty($errors)) {
            $dupCheck = $pdo->prepare("SELECT id FROM rooms WHERE name = :name LIMIT 1");
            $dupCheck->execute([':name' => $values['name']]);

            if ($dupCheck->fetch()) {
                $errors[] = 'A room with this name already exists.';
            }
        }

        // Insert
        if (empty($errors)) {

            $stmt = $pdo->prepare("
                INSERT INTO rooms (name, type, capacity, has_projector, has_whiteboard, has_ac, status)
                VALUES (:name, :type, :capacity, :has_projector, :has_whiteboard, :has_ac, :status)
            ");

            $stmt->execute([
                ':name'           => $values['name'],
                ':type'           => $values['type'],
                ':capacity'       => (int) $values['capacity'],
                ':has_projector'  => $values['has_projector'] ? 1 : 0,
                ':has_whiteboard' => $values['has_whiteboard'] ? 1 : 0,
                ':has_ac'         => $values['has_ac'] ? 1 : 0,
                ':status'         => $values['status'],
            ]);

            setFlash('success', 'Room "' . $values['name'] . '" was created successfully.');
            header('Location: index.php');
            exit;
        }
    }
}

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Room | SESH Admin</title>

    <link rel="stylesheet" href="../../assets/css/style.css">

    <style>

        .admin-page {
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
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -0.08em;
            color: #111;
            text-decoration: none;
        }

        .admin-nav-links a {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: #555;
            text-decoration: none;
        }

        .admin-content {
            width: 88%;
            max-width: 700px;
            margin: 0 auto;
            padding: 60px 0;
        }

        .page-label {
            margin-bottom: 12px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.2em;
            color: #777;
        }

        .admin-content h1 {
            margin: 0 0 40px 0;
            font-size: clamp(32px, 5vw, 50px);
            letter-spacing: -0.05em;
        }

        .form-errors {
            margin-bottom: 25px;
            padding: 16px 18px;
            background: #a33;
            color: #fff;
            font-size: 13px;
        }

        .form-errors ul {
            margin: 0;
            padding-left: 18px;
        }

        .room-form label {
            display: block;
            margin-bottom: 8px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.1em;
        }

        .room-form input[type="text"],
        .room-form input[type="number"],
        .room-form select {
            width: 100%;
            padding: 13px;
            margin-bottom: 22px;
            border: 1px solid #ccc;
            background: #fff;
            font-size: 14px;
        }

        .checkbox-row {
            display: flex;
            gap: 25px;
            margin-bottom: 25px;
        }

        .checkbox-row label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        .checkbox-row input {
            width: auto;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }

        .btn {
            display: inline-block;
            padding: 14px 22px;
            background: #111;
            color: #fff;
            text-decoration: none;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.14em;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: #333;
        }

        .btn-secondary {
            background: #ddd;
            color: #111;
        }

        .btn-secondary:hover {
            background: #ccc;
        }

    </style>

</head>

<body>

<div class="admin-page">

    <header class="admin-nav">
        <a href="../index.php" class="admin-logo">SESH</a>
        <nav class="admin-nav-links">
            <a href="index.php">← BACK TO ROOMS</a>
        </nav>
    </header>

    <main class="admin-content">

        <p class="page-label">ROOM &amp; LAB MANAGEMENT</p>
        <h1>Add Room</h1>

        <?php if (!empty($errors)): ?>
            <div class="form-errors">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="create.php" class="room-form">

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <label for="name">Room Name / Number</label>
            <input type="text" id="name" name="name" required maxlength="100"
                   value="<?= htmlspecialchars($values['name']) ?>" placeholder="e.g. Lab-101">

            <label for="type">Type</label>
            <select id="type" name="type" required>
                <?php foreach ($typeOptions as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>" <?= $values['type'] === $opt ? 'selected' : '' ?>>
                        <?= htmlspecialchars($opt) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="capacity">Capacity</label>
            <input type="number" id="capacity" name="capacity" required min="1" max="65535"
                   value="<?= htmlspecialchars((string) $values['capacity']) ?>">

            <label>Resources</label>
            <div class="checkbox-row">
                <label>
                    <input type="checkbox" name="has_projector" <?= $values['has_projector'] ? 'checked' : '' ?>>
                    Projector
                </label>
                <label>
                    <input type="checkbox" name="has_whiteboard" <?= $values['has_whiteboard'] ? 'checked' : '' ?>>
                    Whiteboard
                </label>
                <label>
                    <input type="checkbox" name="has_ac" <?= $values['has_ac'] ? 'checked' : '' ?>>
                    AC
                </label>
            </div>

            <label for="status">Status</label>
            <select id="status" name="status" required>
                <?php foreach ($statusOptions as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>" <?= $values['status'] === $opt ? 'selected' : '' ?>>
                        <?= htmlspecialchars($opt) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="form-actions">
                <button type="submit" class="btn">CREATE ROOM</button>
                <a href="index.php" class="btn btn-secondary">CANCEL</a>
            </div>

        </form>

    </main>

</div>

</body>

</html>