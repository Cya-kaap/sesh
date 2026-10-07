<?php // filename: faculty/book_room.php
require "../config/constants.php";
$required_role = ROLE_FACULTY;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../includes/functions.php";
require "../includes/csrf.php";

$room_id = isset($_GET['room_id']) ? (int) $_GET['room_id'] : 0;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$slot_id = isset($_GET['slot_id']) ? (int) $_GET['slot_id'] : 0;

// These lists fill the room and timeslot dropdowns in the request form.
$rooms = mysqli_query(
	$conn,
	"SELECT room_id, room_number FROM room WHERE room_status = 'Available' ORDER BY room_number"
);
$slots = mysqli_query(
	$conn,
	'SELECT slot_id, slot_name, start_time, end_time FROM timeslot ORDER BY slot_id'
);
$page_title = 'Book Room';
require "../includes/header.php";
?>
<section>
	<div class="page-head">
		<div>
			<h1>Request a room.</h1>
			<p class="muted">A coordinator will review your request.</p>
		</div>
	</div>
	<form class="panel" method="post" action="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/process/booking_process.php">
		<label>
			Room
			<select name="room_id" required>
				<?php if (mysqli_num_rows($rooms) > 0): ?>
				<?php while ($room = mysqli_fetch_assoc($rooms)): ?>
					<option value="<?php echo (int) $room['room_id']; ?>" <?php echo $room_id === (int) $room['room_id'] ? 'selected' : ''; ?>>
						<?php echo htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8'); ?>
					</option>
				<?php endwhile; ?>
				<?php else: ?>
				<option disabled>No available rooms</option>
				<?php endif; ?>
			</select>
		</label>
		<label>
			Date
			<input type="date" name="booking_date" value="<?php echo htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?>" min="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" required>
		</label>
		<label>
			Timeslot
			<select name="slot_id" required>
				<?php if (mysqli_num_rows($slots) > 0): ?>
				<?php while ($slot = mysqli_fetch_assoc($slots)): ?>
					<option value="<?php echo (int) $slot['slot_id']; ?>" <?php echo $slot_id === (int) $slot['slot_id'] ? 'selected' : ''; ?>>
						<?php echo htmlspecialchars($slot['slot_name'], ENT_QUOTES, 'UTF-8'); ?>
					</option>
				<?php endwhile; ?>
				<?php else: ?>
				<option disabled>No timeslots found</option>
				<?php endif; ?>
			</select>
		</label>
		<label>
			Purpose
			<textarea name="purpose" required maxlength="255"></textarea>
		</label>
		<?php csrf_field(); ?>
		<button type="submit">Submit request</button>
	</form>
</section>
<?php require "../includes/footer.php"; ?>
