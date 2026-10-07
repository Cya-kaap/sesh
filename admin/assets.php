<?php
// filename: admin/assets.php
require "../config/constants.php";
$required_role = ROLE_ADMIN;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../includes/functions.php";
require "../includes/csrf.php";

$page_title = 'Assets';

// Load rooms separately so every room can be shown, even when it has no assets yet.
$rooms = mysqli_query($conn, 'SELECT room_id, room_number FROM room ORDER BY room_number');
$assets = [];

// Build a lookup table used to pre-fill each room's current quantities.
$asset_result = mysqli_query($conn, 'SELECT room_id, asset_type, quantity FROM asset');
if (mysqli_num_rows($asset_result) > 0) {
    while ($asset = mysqli_fetch_assoc($asset_result)) {
        $assets[(int) $asset['room_id']][$asset['asset_type']] = (int) $asset['quantity'];
    }
}

require "../includes/header.php";
?>

<section>
    <h1>Room assets.</h1>
    <!-- One form keeps the table markup valid and submits all room changes together. -->
    <form method="post" action="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/process/asset_process.php">
        <table border="1" cellpadding="5" cellspacing="0">
            <tr>
                <th>Room</th>
                <th>Projector</th>
                <th>AC</th>
                <th>Whiteboard</th>
            </tr>
            <?php if (mysqli_num_rows($rooms) > 0): ?>
            <?php while ($room = mysqli_fetch_assoc($rooms)): ?>
                <?php $room_id = (int) $room['room_id']; ?>
                <tr>
                    <td><?php echo htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <?php foreach (['Projector', 'AC', 'Whiteboard'] as $type): ?>
                        <td>
                            <input type="number" name="assets[<?php echo $room_id; ?>][<?php echo htmlspecialchars(strtolower($type), ENT_QUOTES, 'UTF-8'); ?>]" min="0" value="<?php echo isset($assets[$room_id][$type]) ? (int) $assets[$room_id][$type] : 0; ?>">
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4">No rooms found.</td></tr>
            <?php endif; ?>
        </table>
        <?php csrf_field(); ?>
        <button type="submit">Save assets</button>
    </form>
</section>
<?php require "../includes/footer.php"; ?>
