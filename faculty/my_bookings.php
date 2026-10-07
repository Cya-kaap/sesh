<?php
// filename: faculty/my_bookings.php
require "../config/constants.php";
$required_role = ROLE_FACULTY;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../includes/functions.php";
require "../includes/csrf.php";

// Load only the current faculty member's bookings.
$faculty_id = (int) $_SESSION['user_id'];
$query = "SELECT b.booking_id, b.booking_date, b.purpose, b.status,
			r.room_number, t.slot_name
	 FROM booking AS b
	 INNER JOIN room AS r ON r.room_id = b.room_id
	 INNER JOIN timeslot AS t ON t.slot_id = b.slot_id
	 WHERE b.faculty_id = $faculty_id
	 ORDER BY b.booking_date DESC, t.slot_id";
$bookings = mysqli_query($conn, $query);

$page_title = 'My Bookings';
require "../includes/header.php";
?>
<section>
	<div class="page-head">
		<h1>My bookings.</h1>
	</div>

	<div class="table-wrap">
		<table border="1" cellpadding="5" cellspacing="0">
			<tr>
				<th>Date</th>
				<th>Room</th>
				<th>Period</th>
				<th>Purpose</th>
				<th>Status</th>
				<th></th>
			</tr>
			<?php if (mysqli_num_rows($bookings) > 0): ?>
				<?php while ($booking = mysqli_fetch_assoc($bookings)): ?>
					<tr>
						<td><?php echo htmlspecialchars($booking['booking_date'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars($booking['room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars($booking['slot_name'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars($booking['purpose'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><span class="badge <?php echo htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
						<td>
							<?php if ($booking['status'] === STATUS_PENDING): ?>
								<form class="inline" method="post" action="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/process/booking_cancel.php">
									<?php csrf_field(); ?>
									<input type="hidden" name="booking_id" value="<?php echo (int) $booking['booking_id']; ?>">
									<button class="button light" onclick="return confirm('Cancel this request?')">Cancel</button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endwhile; ?>
			<?php else: ?>
				<tr><td colspan="6" class="muted">You have no bookings.</td></tr>
			<?php endif; ?>
		</table>
	</div>
</section>
<?php require "../includes/footer.php"; ?>
