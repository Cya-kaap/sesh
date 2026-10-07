<?php
// filename: faculty/search_availability.php
require "../config/constants.php";
$required_role = ROLE_FACULTY;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../includes/functions.php";

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$slot_id = isset($_GET['slot_id']) ? (int) $_GET['slot_id'] : 0;
	$slots = mysqli_query(
	$conn,
	'SELECT slot_id, slot_name, start_time
	 FROM timeslot
	 ORDER BY slot_id'
);
$rooms = null;

if ($slot_id > 0) {
	// A room is available only when it is operational and has no active booking.
	$date_sql = db_escape($conn, $date);
	$query = "SELECT r.room_id, r.room_number, r.building, r.floor, r.capacity,
				r.room_type,
				GROUP_CONCAT(
					CONCAT(a.asset_type, ' (', a.quantity, ')')
					ORDER BY a.asset_type SEPARATOR ', '
				) AS assets
		 FROM room AS r
		 LEFT JOIN asset AS a ON a.room_id = r.room_id
		 WHERE r.room_status = 'Available'
		 AND NOT EXISTS (
			SELECT 1 FROM booking AS b
			WHERE b.room_id = r.room_id
			AND b.booking_date = '$date_sql'
			AND b.slot_id = $slot_id
			AND b.status IN ('Pending', 'Approved')
		 )
		 GROUP BY r.room_id, r.room_number, r.building, r.floor, r.capacity, r.room_type
		 ORDER BY r.room_number";
	$rooms = mysqli_query($conn, $query);
}

$page_title = 'Search Availability';
require "../includes/header.php";
?>
<section>
	<div class="page-head">
		<div>
			<h1>Find an open room.</h1>
			<p class="muted">Availability excludes pending and approved bookings.</p>
		</div>
	</div>
	<form class="searchbar" method="get">
		<label>
			Date
			<input type="date" name="date" value="<?php echo htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?>" min="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" required>
		</label>
		<label>
			Timeslot
			<select name="slot_id" required>
				<option value="">Choose period</option>
				<?php if (mysqli_num_rows($slots) > 0): ?>
				<?php while ($slot = mysqli_fetch_assoc($slots)): ?>
					<option value="<?php echo (int) $slot['slot_id']; ?>" <?php echo $slot_id === (int) $slot['slot_id'] ? 'selected' : ''; ?>>
						<?php echo htmlspecialchars($slot['slot_name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars(substr($slot['start_time'], 0, 5), ENT_QUOTES, 'UTF-8'); ?>)
					</option>
				<?php endwhile; ?>
				<?php else: ?>
				<option disabled>No timeslots found</option>
				<?php endif; ?>
			</select>
		</label>
		<button type="submit">Search</button>
	</form>
	<?php if ($rooms !== null): ?>
		<div class="panel">
			<h2>Available rooms</h2>
			<table border="1" cellpadding="5" cellspacing="0">
				<tr>
					<th>Room</th>
					<th>Building / Floor</th>
					<th>Capacity</th>
					<th>Type</th>
					<th>Assets</th>
					<th></th>
				</tr>
				<?php if (mysqli_num_rows($rooms) > 0): ?>
				<?php while ($room = mysqli_fetch_assoc($rooms)): ?>
					<tr>
						<td><?php echo htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars($room['building'], ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars($room['floor'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo (int) $room['capacity']; ?></td>
						<td><?php echo htmlspecialchars($room['room_type'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars(isset($room['assets']) ? $room['assets'] : 'No assets listed', ENT_QUOTES, 'UTF-8'); ?></td>
						<td>
							<a class="button light" href="book_room.php?room_id=<?php echo (int) $room['room_id']; ?>&date=<?php echo rawurlencode($date); ?>&slot_id=<?php echo (int) $slot_id; ?>">Request</a>
						</td>
					</tr>
				<?php endwhile; ?>
				<?php else: ?>
					<tr><td colspan="6">No available rooms found.</td></tr>
				<?php endif; ?>
			</table>
		</div>
	<?php endif; ?>
</section>
<?php require "../includes/footer.php"; ?>
