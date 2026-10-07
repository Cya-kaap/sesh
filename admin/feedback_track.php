<?php // filename: admin/feedback_track.php

/*
 * This admin page is a read-only report. Feedback submission belongs to the
 * faculty process endpoint, so this module never performs an INSERT.
 */
require "../includes/auth_check.php";
$required_role = ROLE_ADMIN;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../includes/functions.php";

$room_id = isset($_GET['room_id']) ? (int) $_GET['room_id'] : 0;
$rating = isset($_GET['rating']) ? (int) $_GET['rating'] : 0;

// Load rooms for the optional room filter.
$rooms = mysqli_query($conn, 'SELECT room_id, room_number FROM room ORDER BY room_number');

	$query = "SELECT f.submitted_at, f.rating, f.equipment_issues, f.overall_condition,
			u.full_name, r.room_number, b.booking_date
	 FROM feedback AS f
	 INNER JOIN booking AS b ON b.booking_id = f.booking_id
	 INNER JOIN user AS u ON u.user_id = b.faculty_id
	 INNER JOIN room AS r ON r.room_id = b.room_id
	 WHERE 1=1";
if ($room_id > 0) {
	$query .= ' AND b.room_id = ' . $room_id;
}
if ($rating > 0) {
	$query .= ' AND f.rating = ' . $rating;
}
$query .= ' ORDER BY f.submitted_at DESC';

// The report is read-only; it never changes feedback records.
$feedback = mysqli_query($conn, $query);

$page_title = 'Feedback Tracker';
require "../includes/header.php";
?>
<section>
	<div class="page-head">
		<div>
			<h1>Feedback tracker.</h1>
			<p class="muted">Review faculty feedback and decide whether admin action is needed.</p>
		</div>
	</div>

	<form class="searchbar" method="get">
		<label>
			Room
			<select name="room_id">
				<option value="0">All rooms</option>
				<?php if (mysqli_num_rows($rooms) > 0): ?>
				<?php while ($room = mysqli_fetch_assoc($rooms)): ?>
					<option value="<?php echo (int) $room['room_id']; ?>" <?php echo $room_id === (int) $room['room_id'] ? 'selected' : ''; ?>>
						<?php echo htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8'); ?>
					</option>
				<?php endwhile; ?>
				<?php else: ?>
				<option disabled>No rooms found</option>
				<?php endif; ?>
			</select>
		</label>

		<label>
			Rating
			<select name="rating">
				<option value="0">All ratings</option>
				<?php for ($option = 1; $option <= 5; $option++): ?>
					<option value="<?php echo $option; ?>" <?php echo $rating === $option ? 'selected' : ''; ?>>
						<?php echo $option; ?>/5
					</option>
				<?php endfor; ?>
			</select>
		</label>

		<button type="submit">Filter</button>
	</form>

	<div class="table-wrap">
		<table border="1" cellpadding="5" cellspacing="0">
			<tr>
				<th>Submitted</th>
				<th>Booking Date</th>
				<th>Faculty</th>
				<th>Room</th>
				<th>Rating</th>
				<th>Equipment Issues</th>
				<th>Overall Condition</th>
			</tr>

			<?php if (mysqli_num_rows($feedback) > 0): ?>
			<?php while ($item = mysqli_fetch_assoc($feedback)): ?>
				<tr>
					<td><?php echo htmlspecialchars($item['submitted_at'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars($item['booking_date'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars($item['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars($item['room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo (int) $item['rating']; ?>/5</td>
					<td><?php echo htmlspecialchars(isset($item['equipment_issues']) ? $item['equipment_issues'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars(isset($item['overall_condition']) ? $item['overall_condition'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
				</tr>
			<?php endwhile; ?>
			<?php else: ?>
				<tr><td colspan="7">No feedback records found.</td></tr>
			<?php endif; ?>
		</table>
	</div>
</section>
<?php require "../includes/footer.php"; ?>
