<?php // filename: faculty/feedback.php

/*
 * This page belongs to the faculty module and only displays eligible rows.
 * The processor repeats the checks because a form page cannot be trusted as
 * the final security boundary after the browser submits its hidden ID.
 */
require "../includes/auth_check.php";
$required_role = ROLE_FACULTY;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../config/constants.php";
require "../includes/functions.php";
require "../includes/csrf.php";

/*
 * A room is never selected freely. Every card comes from this faculty
 * member's own approved booking, and only bookings before today are eligible.
 */
$faculty_id = (int) $_SESSION['user_id'];
$approved_sql = db_escape($conn, STATUS_APPROVED);
$query = "SELECT b.booking_id, b.booking_date, r.room_number
	 FROM booking AS b
	 INNER JOIN room AS r ON r.room_id = b.room_id
	 INNER JOIN timeslot AS t ON t.slot_id = b.slot_id
	 LEFT JOIN feedback AS f
		ON f.booking_id = b.booking_id
	 WHERE b.faculty_id = $faculty_id
	 AND b.status = '$approved_sql'
	 AND (
		b.booking_date < CURDATE()
		OR (b.booking_date = CURDATE() AND t.end_time <= CURTIME())
	 )
	 AND f.feedback_id IS NULL
	 ORDER BY b.booking_date DESC";
// Only completed sessions without previous feedback are shown.
$bookings = mysqli_query($conn, $query);

$page_title = 'Feedback';
require "../includes/header.php";
?>
<section>
	<div class="page-head">
		<div>
			<h1>Room feedback.</h1>
			<p class="muted">Share feedback for rooms used in completed sessions.</p>
		</div>
	</div>

	<div class="grid">
		<?php if (mysqli_num_rows($bookings) > 0): ?>
		<?php while ($booking = mysqli_fetch_assoc($bookings)): ?>
			<form class="card" method="post" action="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/process/feedback_process.php">
				<h3><?php echo htmlspecialchars($booking['room_number'], ENT_QUOTES, 'UTF-8'); ?></h3>
				<p class="muted">Used on <?php echo htmlspecialchars($booking['booking_date'], ENT_QUOTES, 'UTF-8'); ?></p>

				<!-- The hidden value identifies a real eligible booking, not a free room. -->
				<input type="hidden" name="booking_id" value="<?php echo (int) $booking['booking_id']; ?>">

				<label>
					Rating
					<select name="rating" required>
						<option value="5">5 - Excellent</option>
						<option value="4">4 - Good</option>
						<option value="3">3 - Fine</option>
						<option value="2">2 - Poor</option>
						<option value="1">1 - Bad</option>
					</select>
				</label>

				<label>Equipment issues<textarea name="equipment_issues" maxlength="255"></textarea></label>
				<label>Overall condition<textarea name="overall_condition" maxlength="255"></textarea></label>

				<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
				<button type="submit">Send feedback</button>
			</form>
		<?php endwhile; ?>
		<?php else: ?>
			<p class="muted">No completed bookings are eligible for feedback.</p>
		<?php endif; ?>
	</div>
</section>
<?php require "../includes/footer.php"; ?>
