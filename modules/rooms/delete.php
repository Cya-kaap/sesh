<?php
/**
 * modules/rooms/delete.php
 * Delete a room — Admin only.
 */
require_once __DIR__ . '/../../includes/auth_check.php';
requireRole(['Admin']);
require_once __DIR__ . '/../../config/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: /admin/rooms.php');
    exit;
}

// Fetch room to confirm it exists and show details in confirmation
$stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ?');
$stmt->execute([$id]);
$room = $stmt->fetch();

if (!$room) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Room not found.'];
    header('Location: /admin/rooms.php');
    exit;
}

// Optional: Check for existing bookings (prevent deletion if active bookings exist)
$bookingCheck = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE room_id = ? AND status IN ('Pending', 'Approved')");
$bookingCheck->execute([$id]);
$activeBookings = $bookingCheck->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($activeBookings > 0) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Cannot delete room with active or pending bookings.'];
    } else {
        $delete = $pdo->prepare('DELETE FROM rooms WHERE id = ?');
        $delete->execute([$id]);
        
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Room deleted successfully.'];
        header('Location: /admin/rooms.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete Room — SESH</title>
</head>
<body>
    <h1>Delete Room</h1>

    <?php if (isset($activeBookings) && $activeBookings > 0): ?>
        <p class="form-error">
            Warning: This room has <?= $activeBookings ?> active/pending booking(s). 
            You must resolve them before deletion.
        </p>
    <?php endif; ?>

    <p>Are you sure you want to delete the following room?</p>
    <ul>
        <li><strong>Name:</strong> <?= htmlspecialchars($room['name']) ?></li>
        <li><strong>Type:</strong> <?= htmlspecialchars($room['type']) ?></li>
        <li><strong>Capacity:</strong> <?= (int)$room['capacity'] ?></li>
        <li><strong>Status:</strong> <?= htmlspecialchars($room['status']) ?></li>
    </ul>

    <form method="POST" action="delete.php">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <button type="submit" class="btn-danger" 
                <?= $activeBookings > 0 ? 'disabled' : '' ?>>
            Yes, Delete Room
        </button>
        <a href="/admin/rooms.php">Cancel</a>
    </form>
</body>
</html>