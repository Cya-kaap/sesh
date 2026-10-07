<?php // filename: admin/dashboard.php

/*
 * The auth guard must run before the shared header so unauthorized requests
 * are redirected before any HTML or navigation is sent to the browser.
 */
require "../includes/auth_check.php";

/*
 * The required role is fixed by this page. role_check.php handles only this
 * authorization decision and does not perform unrelated admin queries.
 */
$required_role = ROLE_ADMIN;
require "../includes/role_check.php";

require "../config/database.php";
global $conn;
require "../includes/functions.php";

/*
 * Admin statistics remain inside the admin module. A shared stats function
 * would blur ownership and let one role's metric change affect other roles.
 */
$users_result = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM user');
$user_count = mysqli_fetch_assoc($users_result)['total'];

// These counts are displayed as quick links on the admin dashboard.
$rooms_result = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM room');
$room_count = mysqli_fetch_assoc($rooms_result)['total'];

$feedback_result = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM feedback');
$feedback_count = mysqli_fetch_assoc($feedback_result)['total'];

$page_title = 'Admin Dashboard';
require "../includes/header.php";
?>
<section>
	<h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
	<div class="grid">
		<div class="card">
			<div class="muted">Total Users</div>
			<div class="stat"><?php echo (int) $user_count; ?></div>
			<a class="button light" href="users.php">Manage users</a>
		</div>
		<div class="card">
			<div class="muted">Total Rooms</div>
			<div class="stat"><?php echo (int) $room_count; ?></div>
			<a class="button light" href="rooms.php">Manage rooms</a>
		</div>
		<div class="card">
			<div class="muted">Feedback Items</div>
			<div class="stat"><?php echo (int) $feedback_count; ?></div>
			<a class="button light" href="feedback_track.php">Review feedback</a>
		</div>
	</div>
</section>
<?php require "../includes/footer.php"; ?>
