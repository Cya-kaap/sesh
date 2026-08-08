<?php
/**
 * modules/rooms/edit.php
 * Edit an existing room — Admin only.
 */
require_once __DIR__ . '/../../includes/auth_check.php';
requireRole(['Admin']);
require_once __DIR__ . '/../../config/db.php';

$validTypes  = ['Classroom', 'Laboratory', 'Seminar Hall', 'Conference Room', 'Other'];
$validStatus = ['Available', 'Under Maintenance', 'Unavailable'];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: /admin/rooms.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ?');
$stmt->execute([$id]);
$room = $stmt->fetch();

if (!$room) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Room not found.'];
    header('Location: /admin/rooms.php');
    exit;
}

$errors = [];
$form = [
    'name'           => $room['name'],
    'type'           => $room['type'],
    'capacity'       => $room['capacity'],
    'has_projector'  => (bool)$room['has_projector'],
    'has_whiteboard' => (bool)$room['has_whiteboard'],
    'has_ac'         => (bool)$room['has_ac'],
    'status'         => $room['status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name']           = trim($_POST['name'] ?? '');
    $form['type']           = $_POST['type'] ?? '';
    $form['capacity']       = $_POST['capacity'] ?? '';
    $form['has_projector']  = isset($_POST['has_projector']);
    $form['has_whiteboard'] = isset($_POST['has_whiteboard']);
    $form['has_ac']         = isset($_POST['has_ac']);
    $form['status']         = $_POST['status'] ?? '';

    if ($form['name'] === '') {
        $errors[] = 'Room name is required.';
    }
    if (!in_array($form['type'], $validTypes, true)) {
        $errors[] = 'Invalid room type.';
    }
    if (!ctype_digit((string)$form['capacity']) || (int)$form['capacity'] <= 0) {
        $errors[] = 'Capacity must be a whole number greater than 0.';
    }
    if (!in_array($form['status'], $validStatus, true)) {
        $errors[] = 'Invalid status.';
    }

    // Uniqueness check excluding this room's own row
    if (empty($errors)) {
        $check = $pdo->prepare('SELECT id FROM rooms WHERE name = ? AND id != ?');
        $check->execute([$form['name'], $id]);
        if ($check->fetch()) {
            $errors[] = 'Another room already uses that name.';
        }
    }

    if (empty($errors)) {
        $update = $pdo->prepare(
            'UPDATE rooms
             SET name = ?, type = ?, capacity = ?, has_projector = ?, has_whiteboard = ?, has_ac = ?, status = ?
             WHERE id = ?'
        );
        $update->execute([
            $form['name'],
            $form['type'],
            (int)$form['capacity'],
            $form['has_projector'] ? 1 : 0,
            $form['has_whiteboard'] ? 1 : 0,
            $form['has_ac'] ? 1 : 0,
            $form['status'],
            $id,
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Room updated successfully.'];
        header('Location: /admin/rooms.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Room — SESH</title>
</head>
<body>
    <h1>Edit Room</h1>

    <?php foreach ($errors as $err): ?>
        <p class="form-error"><?= htmlspecialchars($err) ?></p>
    <?php endforeach; ?>

    <form method="POST" action="edit.php?id=<?= (int)$id ?>">
        <input type="hidden" name="id" value="<?= (int)$id ?>">

        <label for="name">Room Name</label>
        <input type="text" id="name" name="name" required
               value="<?= htmlspecialchars($form['name']) ?>">

        <label for="type">Type</label>
        <select id="type" name="type">
            <?php foreach ($validTypes as $t): ?>
                <option value="<?= $t ?>" <?= $form['type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>

        <label for="capacity">Capacity</label>
        <input type="number" id="capacity" name="capacity" min="1" required
               value="<?= htmlspecialchars((string)$form['capacity']) ?>">

        <fieldset>
            <legend>Resources</legend>
            <label>
                <input type="checkbox" name="has_projector" <?= $form['has_projector'] ? 'checked' : '' ?>>
                Projector
            </label>
            <label>
                <input type="checkbox" name="has_whiteboard" <?= $form['has_whiteboard'] ? 'checked' : '' ?>>
                Whiteboard
            </label>
            <label>
                <input type="checkbox" name="has_ac" <?= $form['has_ac'] ? 'checked' : '' ?>>
                AC
            </label>
        </fieldset>

        <label for="status">Status</label>
        <select id="status" name="status">
            <?php foreach ($validStatus as $s): ?>
                <option value="<?= $s ?>" <?= $form['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Save Changes</button>
        <a href="/admin/rooms.php">Cancel</a>
    </form>
</body>
</html>