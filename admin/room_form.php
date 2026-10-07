<?php // filename: admin/room_form.php
require "../config/constants.php";
$required_role = ROLE_ADMIN;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../includes/functions.php";
require "../includes/csrf.php";

$room_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$room = [
    'room_number' => '',
    'capacity' => 0,
    'floor' => '',
    'building' => '',
    'room_type' => 'Classroom',
    'room_status' => ROOM_AVAILABLE,
];

if ($room_id > 0) {
    // Load the existing room values when editing a room.
    $room_result = mysqli_query(
        $conn,
        "SELECT room_number, capacity, floor, building, room_type, room_status FROM room WHERE room_id = $room_id"
    );
    $room = mysqli_fetch_assoc($room_result);
}

$page_title = $room_id > 0 ? 'Edit Room' : 'Add Room';
require "../includes/header.php";
?>
<section>
    <h1><?php echo $room_id > 0 ? 'Edit' : 'Add'; ?> room.</h1>
    <!-- The hidden ID tells the processor whether to insert or update. -->
    <form class="panel" method="post" action="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/process/room_process.php">
        <input type="hidden" name="room_id" value="<?php echo (int) $room_id; ?>">
        <label>
            Room number
            <input name="room_number" value="<?php echo htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>
        <label>
            Building
            <input name="building" value="<?php echo htmlspecialchars($room['building'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Floor
            <input name="floor" value="<?php echo htmlspecialchars($room['floor'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Capacity
            <input type="number" name="capacity" value="<?php echo (int) $room['capacity']; ?>" min="0" required>
        </label>
        <label>
            Room type
            <select name="room_type">
                <?php foreach (['Classroom', 'Lab', 'Seminar', 'Auditorium'] as $type): ?>
                    <option <?php echo $room['room_type'] === $type ? 'selected' : ''; ?>><?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Status
            <select name="room_status">
                <?php foreach ([ROOM_AVAILABLE, ROOM_MAINTENANCE, ROOM_UNAVAILABLE] as $status): ?>
                    <option <?php echo $room['room_status'] === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php csrf_field(); ?>
        <button type="submit">Save room</button>
    </form>
</section>
<?php require "../includes/footer.php"; ?>
