<?php // filename: faculty/dashboard.php

/*
 * The authentication check is first so no page content is sent before an
 * unauthenticated visitor can be redirected to login.php.
 */
require "../includes/auth_check.php";

/*
 * The role is declared by this page, not by request data. role_check.php
 * then protects this faculty-only shell before the shared layout is opened.
 */
$required_role = ROLE_FACULTY;
require "../includes/role_check.php";

require "../config/database.php";
global $conn;
require "../config/constants.php";
require "../includes/functions.php";

/*
 * Faculty counts stay in this role module. A shared cross-role stats helper
 * could let a future faculty change silently alter coordinator or admin data.
 */
$faculty_id = (int) $_SESSION['user_id'];
$pending_sql = db_escape($conn, STATUS_PENDING);
$pending_result = mysqli_query(
	$conn,
	"SELECT COUNT(*) AS total FROM booking WHERE faculty_id = $faculty_id AND status = '$pending_sql'"
);
$pending_count = mysqli_fetch_assoc($pending_result)['total'];

$approved_sql = db_escape($conn, STATUS_APPROVED);
$approved_week_query = "SELECT COUNT(*) AS total
	 FROM booking
	WHERE faculty_id = $faculty_id
	 AND status = '$approved_sql'
	 AND booking_date BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
	 AND DATE_ADD(CURDATE(), INTERVAL 6 - WEEKDAY(CURDATE()) DAY)";
$approved_week_result = mysqli_query($conn, $approved_week_query);
$approved_week_count = mysqli_fetch_assoc($approved_week_result)['total'];

$page_title = 'Faculty Dashboard';
require "../includes/header.php";
?>
<section>
	<h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
	<div class="grid">
		<div class="card">
			<div class="muted">My Pending Bookings</div>
			<div class="stat"><?php echo (int) $pending_count; ?></div>
			<a class="button light" href="my_bookings.php">View bookings</a>
		</div>
		<div class="card">
			<div class="muted">My Approved This Week</div>
			<div class="stat"><?php echo (int) $approved_week_count; ?></div>
		</div>
	</div>
</section>
<?php require "../includes/footer.php"; ?>
