<?php // filename: coordinator/utilization.php

/*
 * Utilization is a coordinator-owned report. It reads approved usage only;
 * approval and rejection remain isolated inside process_booking.php.
 */
require "../includes/auth_check.php";
$required_role = ROLE_COORDINATOR;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../config/constants.php";
require "../includes/functions.php";

$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

if (!is_valid_date($from)) {
	$from = date('Y-m-01');
}

if (!is_valid_date($to) || $from > $to) {
	$to = date('Y-m-d');
}

// Count all requests and approved bookings for each room in the selected range.
$approved_sql = db_escape($conn, STATUS_APPROVED);
$from_sql = db_escape($conn, $from);
$to_sql = db_escape($conn, $to);
$query = "SELECT r.room_number,
			COUNT(b.booking_id) AS total_requests,
			SUM(CASE WHEN b.status = '$approved_sql' THEN 1 ELSE 0 END) AS approved_bookings
	 FROM room AS r
	 LEFT JOIN booking AS b
		ON b.room_id = r.room_id
		AND b.booking_date BETWEEN '$from_sql' AND '$to_sql'
	 GROUP BY r.room_id, r.room_number
	 ORDER BY r.room_number";
$utilization = mysqli_query($conn, $query);

$page_title = 'Room Utilization';
require "../includes/header.php";
?>
<section>
	<div class="page-head">
		<div>
			<h1>Room utilization.</h1>
			<p class="muted">Read-only usage reporting for the selected date range.</p>
		</div>
	</div>

	<form class="searchbar" method="get">
		<label>From<input type="date" name="from" value="<?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?>" required></label>
		<label>To<input type="date" name="to" value="<?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?>" required></label>
		<button type="submit">Calculate</button>
	</form>

	<div class="table-wrap">
		<table border="1" cellpadding="5" cellspacing="0">
			<tr>
				<th>Room</th>
				<th>Total requests</th>
				<th>Approved bookings</th>
				<th>Approval rate</th>
			</tr>

			<?php if (mysqli_num_rows($utilization) > 0): ?>
			<?php while ($room = mysqli_fetch_assoc($utilization)): ?>
				<?php
				$total_requests = (int) $room['total_requests'];
				$approved_bookings = (int) $room['approved_bookings'];
				// Avoid division by zero when a room has no requests.
				$approval_rate = $total_requests > 0
					? round(($approved_bookings / $total_requests) * 100, 1)
					: 0;
				?>
				<tr>
					<td><?php echo htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo $total_requests; ?></td>
					<td><?php echo $approved_bookings; ?></td>
					<td><?php echo $approval_rate; ?>%</td>
				</tr>
			<?php endwhile; ?>
			<?php else: ?>
				<tr><td colspan="4">No utilization records found.</td></tr>
			<?php endif; ?>
		</table>
	</div>
</section>
<?php require "../includes/footer.php"; ?>
