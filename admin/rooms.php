<?php
// filename: admin/rooms.php
require "../config/constants.php";
$required_role = ROLE_ADMIN;
$allowed_roles = [ROLE_ADMIN];
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
$page_title = 'Rooms';
// Show the room details needed by the administrator.
$rooms = mysqli_query($conn, 'SELECT room_id, room_number, floor, building, capacity, room_status FROM room ORDER BY room_number');
include "../includes/header.php";
?>
<section>
    <div class="page-head">
        <h1>Rooms.</h1>
        <a class="button" href="room_form.php">Add room</a>
    </div>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>Room</th>
            <th>Location</th>
            <th>Capacity</th>
            <th>Status</th>
            <th></th>
        </tr>
        <?php if (mysqli_num_rows($rooms) > 0): ?>
        <?php while ($r = mysqli_fetch_assoc($rooms)): ?>
            <tr>
                <td><?php echo htmlspecialchars($r['room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($r['building'], ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars($r['floor'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int) $r['capacity']; ?></td>
                <td><?php echo htmlspecialchars($r['room_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><a class="button light" href="room_form.php?id=<?php echo (int) $r['room_id']; ?>">Edit</a></td>
            </tr>
        <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5">No rooms found.</td></tr>
        <?php endif; ?>
    </table>
</section>
<?php include "../includes/footer.php"; ?>

