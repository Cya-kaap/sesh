<?php
// filename: admin/timeslots.php
require "../config/constants.php";
$required_role = ROLE_ADMIN;
$allowed_roles = [ROLE_ADMIN];
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
$page_title = 'Timeslots';
// Timeslots are managed as fixed schedule periods and are read-only here.
	$slots = mysqli_query(
	$conn,
    'SELECT slot_id, slot_name, start_time, end_time
     FROM timeslot
     ORDER BY slot_id'
);
include "../includes/header.php";
?>
<section>
    <h1>Fixed timeslots.</h1>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>Period</th>
            <th>Start</th>
            <th>End</th>
        </tr>
        <?php if (mysqli_num_rows($slots) > 0): ?>
        <?php while ($s = mysqli_fetch_assoc($slots)): ?>
            <tr>
                <td><?php echo htmlspecialchars($s['slot_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(substr($s['start_time'], 0, 5), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(substr($s['end_time'], 0, 5), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
        <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="3">No timeslots found.</td></tr>
        <?php endif; ?>
    </table>
</section>
<?php include "../includes/footer.php"; ?>

