<?php // filename: coordinator/pending_requests.php

/*
 * This page is a read-and-dispatch boundary. It may display pending rows,
 * but it never changes them; every state transition belongs to the processor.
 */
require "../includes/auth_check.php";
$required_role = ROLE_COORDINATOR;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../config/constants.php";
require "../includes/functions.php";
require "../includes/csrf.php";

// Show only requests waiting for a coordinator decision.
$pending_sql = db_escape($conn, STATUS_PENDING);
$query = "SELECT b.booking_id, b.booking_date, b.purpose,
			u.full_name, r.room_number, t.slot_name, t.start_time, t.end_time
	 FROM booking AS b
	 INNER JOIN user AS u ON u.user_id = b.faculty_id
	 INNER JOIN room AS r ON r.room_id = b.room_id
	 INNER JOIN timeslot AS t ON t.slot_id = b.slot_id
	 WHERE b.status = '$pending_sql'
	 ORDER BY b.booking_date, t.slot_id, r.room_number";
$requests = mysqli_query($conn, $query);

$page_title = 'Pending Requests';
require "../includes/header.php";
?>
<section>
	<div class="page-head">
		<div>
			<h1>Pending requests.</h1>
			<p class="muted">Review each request before it enters the approved timetable.</p>
		</div>
	</div>

	<div class="table-wrap">
		<table border="1" cellpadding="5" cellspacing="0">
			<tr>
				<th>Date</th>
				<th>Faculty</th>
				<th>Room</th>
				<th>Period</th>
				<th>Purpose</th>
				<th>Action</th>
			</tr>
			<?php if (mysqli_num_rows($requests) > 0): ?>
			<?php while ($request = mysqli_fetch_assoc($requests)): ?>
				<tr>
					<td><?php echo htmlspecialchars($request['booking_date'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars($request['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars($request['room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td>
						<?php echo htmlspecialchars($request['slot_name'], ENT_QUOTES, 'UTF-8'); ?><br>
						<?php echo htmlspecialchars(substr($request['start_time'], 0, 5), ENT_QUOTES, 'UTF-8'); ?> -
						<?php echo htmlspecialchars(substr($request['end_time'], 0, 5), ENT_QUOTES, 'UTF-8'); ?>
					</td>
					<td><?php echo htmlspecialchars($request['purpose'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td>
						<!-- The form dispatches; process_booking.php performs the mutation. -->
						<form method="post" action="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/coordinator/process_booking.php">
							<input type="hidden" name="booking_id" value="<?php echo (int) $request['booking_id']; ?>">
							<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
							<button type="submit" name="decision" value="<?php echo htmlspecialchars(STATUS_APPROVED, ENT_QUOTES, 'UTF-8'); ?>">Approve</button>
							<button class="button alt" type="submit" name="decision" value="<?php echo htmlspecialchars(STATUS_REJECTED, ENT_QUOTES, 'UTF-8'); ?>" onclick="return confirm('Reject this booking?')">Reject</button>
						</form>
					</td>
				</tr>
			<?php endwhile; ?>
			<?php else: ?>
				<tr><td colspan="6" class="muted">No pending requests found.</td></tr>
			<?php endif; ?>
		</table>
	</div>
</section>
<?php require "../includes/footer.php"; ?>
