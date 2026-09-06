<?php

/**
 * SESH - Admin: Edit Room
 *
 * Purpose:
 *   Form + handler to update an existing room/lab record.
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
require_once __DIR__ . '/../../includes/room_helpers.php';

requireLogin();
requireWritePermission(FEATURE_ROOMS_MANAGE);

$user = currentUser();

$roomId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($roomId < 1) {
    setFlash('error', 'Invalid room.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $roomId]);
$room = $stmt->fetch();

if (!$room) {
    setFlash('error', 'Room not found.');
    header('Location: index.php');
    exit;
}

$typeOptions = ROOM_TYPES;
$statusOptions = ROOM_STATUSES;

$errors = [];

$values = [
    'room_code'      => $room['room_code'],
    'name'           => $room['name'],
    'building'       => $room['building'],
    'floor'          => $room['floor'],
    'wing_block'     => $room['wing_block'],
    'notes'          => $room['notes'],
    'type'           => $room['type'],
    'capacity'       => $room['capacity'],
    'exam_capacity'  => $room['exam_capacity'],
    'primary_department' => $room['primary_department'],
    'amenities'      => roomDecodeValues($room['amenities']),
    'accessibility'  => roomDecodeValues($room['accessibility']),
    'has_projector'  => (bool) $room['has_projector'],
    'has_whiteboard' => (bool) $room['has_whiteboard'],
    'has_ac'         => (bool) $room['has_ac'],
    'status'         => $room['status'],
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
        $values['building']       = trim($_POST['building'] ?? '');
        $values['floor']          = trim($_POST['floor'] ?? '');
        $values['wing_block']     = trim($_POST['wing_block'] ?? '');
        $values['notes']          = trim($_POST['notes'] ?? '');
        $values['room_code']      = strtoupper(trim($_POST['room_code'] ?? ''));
        $values['type']           = $_POST['type'] ?? '';
        $values['capacity']       = trim($_POST['capacity'] ?? '');
        $values['exam_capacity']  = trim($_POST['exam_capacity'] ?? '');
        $values['primary_department'] = trim($_POST['primary_department'] ?? 'Common / Shared');
        $values['amenities']      = array_values(array_intersect((array) ($_POST['amenities'] ?? []), ROOM_AMENITIES));
        $values['accessibility']  = array_values(array_intersect((array) ($_POST['accessibility'] ?? []), ROOM_ACCESSIBILITY));
        $values['has_projector']  = isset($_POST['has_projector']);
        $values['has_whiteboard'] = isset($_POST['has_whiteboard']);
        $values['has_ac']         = isset($_POST['has_ac']);
        $values['status']         = $_POST['status'] ?? '';

        // Validation

        if ($values['room_code'] === '' || !preg_match('/^[A-Z0-9][A-Z0-9._-]{0,29}$/', $values['room_code'])) {
            $errors[] = 'Room number/code is required and may contain only letters, numbers, dot, underscore, or hyphen.';
        }
        if (mb_strlen($values['name']) > 100) {
            $errors[] = 'Room name must be 100 characters or fewer.';
        }

        if ($values['exam_capacity'] !== '' && (!ctype_digit((string) $values['exam_capacity']) || (int) $values['exam_capacity'] < 1 || (int) $values['exam_capacity'] > (int) $values['capacity'])) {
            $errors[] = 'Examination capacity must be a positive number no greater than seating capacity.';
        }
        if ($values['primary_department'] === '' || mb_strlen($values['primary_department']) > 100) {
            $errors[] = 'Primary department is required and must be 100 characters or fewer.';
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

        // Duplicate name check (excluding this room)
        if (empty($errors)) {
            $dupCheck = $pdo->prepare("SELECT id FROM rooms WHERE room_code = :room_code AND id != :id LIMIT 1");
            $dupCheck->execute([':room_code' => $values['room_code'], ':id' => $roomId]);

            if ($dupCheck->fetch()) {
                $errors[] = 'A room with this code already exists.';
            }
        }

        // Update
        if (empty($errors)) {

            try {
            $photos = roomUploadImages($_FILES['photos'] ?? [], $values['room_code'], 4, 'photos');
            $floorPlan = roomUploadImage($_FILES['floor_plan'] ?? null, $values['room_code'], 'floor-plans');
            $update = $pdo->prepare("
                UPDATE rooms
                SET room_code = :room_code,
                    name = :name,
                    building = :building,
                    floor = :floor,
                    wing_block = :wing_block,
                    notes = :notes,
                    type = :type,
                    capacity = :capacity,
                    exam_capacity = :exam_capacity,
                    primary_department = :primary_department,
                    amenities = :amenities,
                    accessibility = :accessibility,
                    has_projector = :has_projector,
                    has_whiteboard = :has_whiteboard,
                    has_ac = :has_ac,
                    status = :status
                WHERE id = :id
            ");

            $update->execute([
                ':room_code'      => $values['room_code'],
                ':name'           => $values['name'],
                ':building'       => $values['building'] !== '' ? $values['building'] : null,
                ':floor'          => $values['floor'] !== '' ? $values['floor'] : null,
                ':wing_block'     => $values['wing_block'] !== '' ? $values['wing_block'] : null,
                ':notes'          => $values['notes'] !== '' ? $values['notes'] : null,
                ':type'           => $values['type'],
                ':capacity'       => (int) $values['capacity'],
                ':exam_capacity'  => $values['exam_capacity'] !== '' ? (int) $values['exam_capacity'] : null,
                ':primary_department' => $values['primary_department'],
                ':amenities'      => roomJsonValues($values['amenities'], ROOM_AMENITIES),
                ':accessibility'  => roomJsonValues($values['accessibility'], ROOM_ACCESSIBILITY),
                ':has_projector'  => $values['has_projector'] ? 1 : 0,
                ':has_whiteboard' => $values['has_whiteboard'] ? 1 : 0,
                ':has_ac'         => $values['has_ac'] ? 1 : 0,
                ':status'         => $values['status'],
                ':id'             => $roomId,
            ]);
            $media = $pdo->prepare('INSERT INTO room_media (room_id, media_type, file_path) VALUES (:room_id, :media_type, :file_path)');
            foreach ($photos as $path) $media->execute([':room_id' => $roomId, ':media_type' => 'photo', ':file_path' => $path]);
            if ($floorPlan) $media->execute([':room_id' => $roomId, ':media_type' => 'floor_plan', ':file_path' => $floorPlan]);
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }

            if (empty($errors)) {
                setFlash('success', 'Room "' . $values['room_code'] . '" was updated successfully.');
                header('Location: index.php');
                exit;
            }
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

    <title>Edit Room | SESH Admin</title>

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
        <h1>Edit Room</h1>

        <?php if (!empty($errors)): ?>
            <div class="form-errors">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="edit.php?id=<?= $roomId ?>" class="room-form" enctype="multipart/form-data" id="room-form">

            <input type="hidden" name="id" value="<?= $roomId ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                 <label for="room_code">Room Number / Code</label>
                 <input type="text" id="room_code" name="room_code" required maxlength="30" pattern="[A-Za-z0-9._-]+"
                     value="<?= htmlspecialchars($values['room_code']) ?>">

                 <label for="name">Display Name <span style="font-weight:400;">(optional)</span></label>
                 <input type="text" id="name" name="name" maxlength="100"
                   value="<?= htmlspecialchars($values['name']) ?>">

                     <label for="building">Building <span style="font-weight:400;">(optional)</span></label>
                     <input type="text" id="building" name="building" maxlength="100" value="<?= htmlspecialchars($values['building']) ?>">

                     <label for="floor">Floor <span style="font-weight:400;">(optional)</span></label>
                     <input type="text" id="floor" name="floor" maxlength="50" value="<?= htmlspecialchars($values['floor']) ?>">

                    <label for="wing_block">Wing / Block <span style="font-weight:400;">(optional)</span></label>
                    <input type="text" id="wing_block" name="wing_block" maxlength="100" value="<?= htmlspecialchars($values['wing_block']) ?>">

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

                 <label for="exam_capacity">Examination Capacity <span style="font-weight:400;">(optional)</span></label>
                 <input type="number" id="exam_capacity" name="exam_capacity" min="1" max="65535"
                     value="<?= htmlspecialchars((string) $values['exam_capacity']) ?>">

                 <label for="primary_department">Primary Department / Ownership</label>
                 <input type="text" id="primary_department" name="primary_department" required maxlength="100"
                     value="<?= htmlspecialchars($values['primary_department']) ?>">

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

            <label>Amenities</label>
            <div class="checkbox-row">
                <?php foreach (ROOM_AMENITIES as $amenity): ?>
                    <label><input type="checkbox" name="amenities[]" value="<?= htmlspecialchars($amenity) ?>" <?= in_array($amenity, $values['amenities'], true) ? 'checked' : '' ?>> <?= htmlspecialchars($amenity) ?></label>
                <?php endforeach; ?>
            </div>

            <label>Accessibility</label>
            <div class="checkbox-row">
                <?php foreach (ROOM_ACCESSIBILITY as $item): ?>
                    <label><input type="checkbox" name="accessibility[]" value="<?= htmlspecialchars($item) ?>" <?= in_array($item, $values['accessibility'], true) ? 'checked' : '' ?>> <?= htmlspecialchars($item) ?></label>
                <?php endforeach; ?>
            </div>

            <label for="photos">Add Room Photos <span style="font-weight:400;">(up to 4, JPG/PNG/WebP, 5 MB each)</span></label>
            <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>

            <label for="floor_plan">Add Floor Plan <span style="font-weight:400;">(optional image)</span></label>
            <input type="file" id="floor_plan" name="floor_plan" accept="image/jpeg,image/png,image/webp">

            <label for="notes">Notes <span style="font-weight:400;">(optional)</span></label>
            <textarea id="notes" name="notes" maxlength="2000"><?= htmlspecialchars($values['notes']) ?></textarea>

            <label for="status">Status</label>
            <select id="status" name="status" required>
                <?php foreach ($statusOptions as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>" <?= $values['status'] === $opt ? 'selected' : '' ?>>
                        <?= htmlspecialchars($opt) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="form-actions">
                <button type="submit" class="btn">SAVE CHANGES</button>
                <a href="index.php" class="btn btn-secondary">CANCEL</a>
            </div>

        </form>

        <script>
            document.getElementById('room-form').addEventListener('submit', function (event) {
                const capacity = Number(document.getElementById('capacity').value);
                const examCapacity = Number(document.getElementById('exam_capacity').value);
                if (examCapacity && examCapacity > capacity) {
                    event.preventDefault();
                    alert('Examination capacity cannot exceed seating capacity.');
                }
                if (document.getElementById('photos').files.length > 4) {
                    event.preventDefault();
                    alert('You may upload at most 4 room photos.');
                }
            });
        </script>

    </main>

</div>

</body>

</html>