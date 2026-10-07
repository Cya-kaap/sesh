<?php // filename: admin/bookings_report.php

/*
 * This admin module reports across the system but never approves bookings.
 * Approval belongs to the coordinator workflow, so no mutation control is
 * allowed to cross into this read-only oversight page.
 */
require "../includes/auth_check.php";
$required_role = ROLE_ADMIN;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../config/constants.php";
require "../includes/functions.php";

$status = isset($_GET['status']) ? (string) $_GET['status'] : '';
$from = isset($_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) ? $_GET['to'] : '';
$department_id = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$booking_statuses = [
	STATUS_PENDING,
	STATUS_APPROVED,
	STATUS_REJECTED,
	STATUS_CANCELLED,
];

if (!is_valid_date($from)) {
	$from = '';
}

if (!is_valid_date($to)) {
	$to = '';
}

// The department list fills the report filter dropdown.
$departments = mysqli_query(
	$conn,
	'SELECT department_id, department_name FROM department ORDER BY department_name'
);

	$query = "SELECT b.booking_date, b.purpose, b.status,
			u.full_name, d.department_name, r.room_number, t.slot_name
	 FROM booking AS b
	 INNER JOIN user AS u ON u.user_id = b.faculty_id
	 LEFT JOIN department AS d ON d.department_id = u.department_id
	 INNER JOIN room AS r ON r.room_id = b.room_id
	 INNER JOIN timeslot AS t ON t.slot_id = b.slot_id
	 WHERE 1=1";

if ($status !== '') {
	$query .= " AND b.status = '" . db_escape($conn, $status) . "'";
}
if ($from !== '') {
	$query .= " AND b.booking_date >= '" . db_escape($conn, $from) . "'";
}
if ($to !== '') {
	$query .= " AND b.booking_date <= '" . db_escape($conn, $to) . "'";
}
if ($department_id > 0) {
	$query .= ' AND u.department_id = ' . $department_id;
}
$query .= ' ORDER BY b.booking_date DESC, t.slot_id';

$bookings = mysqli_query($conn, $query);

$page_title = 'Bookings Report';
require "../includes/header.php";
?>
<section>
	<div class="page-head">
		<div>
			<h1>Bookings report.</h1>
			<p class="muted">Cross-cutting oversight for administrators.</p>
		</div>
	</div>

	<form class="searchbar" method="get">
		<label>Status
			<select name="status">
				<option value="">All statuses</option>
				<?php foreach ($booking_statuses as $booking_status): ?>
					<option value="<?php echo htmlspecialchars($booking_status, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $status === $booking_status ? 'selected' : ''; ?>>
						<?php echo htmlspecialchars($booking_status, ENT_QUOTES, 'UTF-8'); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>

		<label>From<input type="date" name="from" value="<?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?>"></label>
		<label>To<input type="date" name="to" value="<?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?>"></label>

		<label>Department
			<select name="department_id">
				<option value="0">All departments</option>
				<?php while ($department = mysqli_fetch_assoc($departments)): ?>
					<option value="<?php echo (int) $department['department_id']; ?>" <?php echo $department_id === (int) $department['department_id'] ? 'selected' : ''; ?>>
						<?php echo htmlspecialchars($department['department_name'], ENT_QUOTES, 'UTF-8'); ?>
					</option>
				<?php endwhile; ?>
			</select>
		</label>

		<button type="submit">Filter</button>
	</form>

	<div class="table-wrap">
		<table border="1" cellpadding="5" cellspacing="0">
			<tr>
				<th>Date</th>
				<th>Faculty</th>
				<th>Department</th>
				<th>Room</th>
				<th>Period</th>
				<th>Purpose</th>
				<th>Status</th>
			</tr>

			<?php if (mysqli_num_rows($bookings) > 0): ?>
			<?php while ($booking = mysqli_fetch_assoc($bookings)): ?>
				<tr>
					<td><?php echo htmlspecialchars($booking['booking_date'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars($booking['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars(isset($booking['department_name']) ? $booking['department_name'] : 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars($booking['room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars($booking['slot_name'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars($booking['purpose'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td><span class="badge <?php echo htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
				</tr>
			<?php endwhile; ?>
			<?php else: ?>
				<tr><td colspan="7">No bookings found.</td></tr>
			<?php endif; ?>
		</table>
	</div>
</section>
<?php require "../includes/footer.php"; ?>
