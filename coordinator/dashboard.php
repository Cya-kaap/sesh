<?php // filename: coordinator/dashboard.php

/*
 * Authentication runs before layout output so an unauthenticated request
 * can be redirected cleanly without sending a partial HTML document.
 */
require "../includes/auth_check.php";

/*
 * This page states the role it requires. The separate role guard prevents a
 * logged-in faculty or admin user from viewing coordinator-only content.
 */
$required_role = ROLE_COORDINATOR;
require "../includes/role_check.php";

require "../config/database.php";
global $conn;
require "../config/constants.php";
require "../includes/functions.php";

/*
 * These are read-only COUNT queries. Keeping them here avoids mixing the
 * dashboard summary with approval mutations, which belong to the processor.
 */
$pending_status = STATUS_PENDING;
$pending_sql = db_escape($conn, $pending_status);
$pending_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM booking WHERE status = '$pending_sql'");
$pending_count = mysqli_fetch_assoc($pending_result)['total'];

$approved_sql = db_escape($conn, STATUS_APPROVED);
$conflict_query = "SELECT COUNT(*) AS total
	 FROM booking AS pending
	 WHERE pending.status = '$pending_sql'
	 AND (
		EXISTS (
			SELECT 1 FROM booking AS approved
			WHERE approved.room_id = pending.room_id
			AND approved.booking_date = pending.booking_date
			AND approved.slot_id = pending.slot_id
			AND approved.status = '$approved_sql'
		)
		OR EXISTS (
			SELECT 1 FROM booking AS other_pending
			WHERE other_pending.booking_id <> pending.booking_id
			AND other_pending.room_id = pending.room_id
			AND other_pending.booking_date = pending.booking_date
			AND other_pending.slot_id = pending.slot_id
			AND other_pending.status = '$pending_sql'
		)
	 )";
$conflict_result = mysqli_query($conn, $conflict_query);
$conflict_count = mysqli_fetch_assoc($conflict_result)['total'];

$approved_today_result = mysqli_query(
	$conn,
	"SELECT COUNT(*) AS total FROM booking WHERE status = '$approved_sql' AND booking_date = CURDATE()"
);
$approved_today_count = mysqli_fetch_assoc($approved_today_result)['total'];

$page_title = 'Coordinator Dashboard';
require "../includes/header.php";
?>
<section>
	<h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
	<div class="grid">
		<div class="card">
			<div class="muted">Pending Requests</div>
			<div class="stat"><?php echo (int) $pending_count; ?></div>
			<a class="button light" href="pending_requests.php">Review</a>
		</div>
		<div class="card">
			<div class="muted">Conflicts</div>
			<div class="stat"><?php echo (int) $conflict_count; ?></div>
			<a class="button light" href="conflicts.php">Inspect</a>
		</div>
		<div class="card">
			<div class="muted">Approved Today</div>
			<div class="stat"><?php echo (int) $approved_today_count; ?></div>
		</div>
	</div>
</section>
<?php require "../includes/footer.php"; ?>
