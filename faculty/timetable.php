<?php // filename: faculty/timetable.php

/*
 * This page is a faculty read-only view. Authentication and role checks run
 * before the shared layout so unauthorized users never receive page chrome.
 */
require "../includes/auth_check.php";
$required_role = ROLE_FACULTY;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../config/constants.php";
require "../includes/functions.php";

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
if (!is_valid_date($date)) {
	$date = date('Y-m-d');
}

// Load the rooms and periods that form the timetable grid.
$rooms_result = mysqli_query($conn, 'SELECT room_id, room_number FROM room ORDER BY room_number');
$rooms = [];
$has_rooms = mysqli_num_rows($rooms_result) > 0;
if ($has_rooms) {
	while ($room_row = mysqli_fetch_assoc($rooms_result)) {
		$rooms[] = $room_row;
	}
}

$slots_result = mysqli_query(
	$conn,
	'SELECT slot_id, slot_name, start_time, end_time FROM timeslot ORDER BY slot_id'
);
$slots = [];
$has_slots = mysqli_num_rows($slots_result) > 0;
if ($has_slots) {
	while ($slot_row = mysqli_fetch_assoc($slots_result)) {
		$slots[] = $slot_row;
	}
}

/*
 * Only approved rows enter the map. Pending rows must never look confirmed
 * merely because they occupy the same room and period in the database.
 */
$date_sql = db_escape($conn, $date);
$approved_sql = db_escape($conn, STATUS_APPROVED);
$faculty_id = (int) $_SESSION['user_id'];
$booking_query = "SELECT b.room_id, b.slot_id, u.full_name, b.purpose
	 FROM booking AS b
	 INNER JOIN user AS u ON u.user_id = b.faculty_id
	 WHERE b.booking_date = '$date_sql'
	 AND b.status = '$approved_sql'
	 AND b.faculty_id = $faculty_id";
// Faculty members see only their own approved bookings.
$booking_result = mysqli_query($conn, $booking_query);
$bookings = [];

if (mysqli_num_rows($booking_result) > 0) {
	while ($booking = mysqli_fetch_assoc($booking_result)) {
		$bookings[$booking['room_id']][$booking['slot_id']] = $booking;
	}
}

$page_title = 'Faculty Timetable';
require "../includes/header.php";
?>
<section>
	<div class="page-head">
		<div>
			<h1>Approved timetable.</h1>
			<p class="muted">Only confirmed room bookings appear on this schedule.</p>
		</div>
		<form method="get">
			<label>
				Date
				<input type="date" name="date" value="<?php echo htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?>" required>
			</label>
			<button type="submit">View date</button>
		</form>
	</div>

	<div class="table-wrap">
		<table class="timetable" border="1" cellpadding="5" cellspacing="0">
			<tr>
				<th>Room</th>
				<?php if ($has_slots): ?>
				<?php foreach ($slots as $slot): ?>
					<th>
						<?php echo htmlspecialchars($slot['slot_name'], ENT_QUOTES, 'UTF-8'); ?><br>
						<?php echo htmlspecialchars(substr($slot['start_time'], 0, 5), ENT_QUOTES, 'UTF-8'); ?> -
						<?php echo htmlspecialchars(substr($slot['end_time'], 0, 5), ENT_QUOTES, 'UTF-8'); ?>
					</th>
				<?php endforeach; ?>
				<?php else: ?>
					<th>No timeslots found.</th>
				<?php endif; ?>
			</tr>

			<?php if ($has_rooms): ?>
			<?php foreach ($rooms as $room): ?>
				<tr>
					<th><?php echo htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8'); ?></th>
					<?php if ($has_slots): ?>
					<?php foreach ($slots as $slot): ?>
						<?php $booking = isset($bookings[$room['room_id']][$slot['slot_id']]) ? $bookings[$room['room_id']][$slot['slot_id']] : null; ?>
						<td>
							<?php if ($booking !== null): ?>
								<div class="slot">
									<strong><?php echo htmlspecialchars($booking['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
									<?php echo htmlspecialchars($booking['purpose'], ENT_QUOTES, 'UTF-8'); ?>
								</div>
							<?php else: ?>
								<span class="muted">Available</span>
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
					<?php else: ?>
						<td>No timeslots found.</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
			<?php else: ?>
				<tr><td colspan="<?php echo count($slots) + 1; ?>">No rooms found.</td></tr>
			<?php endif; ?>
		</table>
	</div>
</section>
<?php require "../includes/footer.php"; ?>
