<?php
// filename: includes/sidebar.php
?>
<a class="brand" href="../index.php">SESH</a>
<nav>
	<?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
		<a href="../admin/dashboard.php">Dashboard</a>
		<a href="../admin/users.php">Users</a>
		<a href="../admin/rooms.php">Rooms</a>
		<a href="../admin/assets.php">Assets</a>
		<a href="../admin/departments.php">Departments</a>
		<a href="../admin/timeslots.php">Timeslots</a>
		<a href="../admin/bookings_report.php">Reports</a>
		<a href="../admin/feedback_track.php">Feedback</a>
		<a href="../admin/logs.php">Logs</a>
	<?php elseif ($_SESSION['role'] === ROLE_COORDINATOR): ?>
		<a href="../coordinator/dashboard.php">Dashboard</a>
		<a href="../coordinator/pending_requests.php">Pending Requests</a>
		<a href="../coordinator/conflicts.php">Conflicts</a>
		<a href="../coordinator/timetable.php">Timetable</a>
		<a href="../coordinator/utilization.php">Utilization</a>
	<?php else: ?>
		<a href="../faculty/dashboard.php">Dashboard</a>
		<a href="../faculty/search_availability.php">Find a Room</a>
		<a href="../faculty/book_room.php">Book Room</a>
		<a href="../faculty/my_bookings.php">My Bookings</a>
		<a href="../faculty/timetable.php">Timetable</a>
		<a href="../faculty/feedback.php">Feedback</a>
	<?php endif; ?>
</nav>
