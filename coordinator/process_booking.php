<?php // filename: coordinator/process_booking.php

/*
 * This endpoint is process-only. It receives one decision, validates it,
 * performs the allowed transition, and redirects without rendering HTML.
 */
require "../includes/auth_check.php";
$required_role = ROLE_COORDINATOR;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../config/constants.php";
require "../includes/functions.php";
require "../includes/csrf.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	redirect('coordinator/pending_requests.php');
}

if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
	flash('error', 'Invalid request token.');
	redirect('coordinator/pending_requests.php');
}

$booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
$decision = isset($_POST['decision']) ? (string) $_POST['decision'] : '';

if ($decision != STATUS_APPROVED && $decision != STATUS_REJECTED) {
	flash('error', 'Invalid booking decision.');
	redirect('coordinator/pending_requests.php');
}

$pending_sql = db_escape($conn, STATUS_PENDING);
$booking_query = 'SELECT room_id, booking_date, slot_id FROM booking WHERE booking_id = '
	. $booking_id . " AND status = '$pending_sql'";
$booking_result = mysqli_query($conn, $booking_query);
$booking = mysqli_fetch_assoc($booking_result);

if ($booking === null) {
	flash('error', 'That booking is no longer pending.');
	redirect('coordinator/pending_requests.php');
}

if ($decision === STATUS_APPROVED) {
	/*
	 * The UI cannot guarantee that two coordinators will not approve at once.
	 * This request repeats the conflict check immediately before UPDATE so the
	 * server, rather than a stale screen, enforces the scheduling rule.
	 */
	$approved_sql = db_escape($conn, STATUS_APPROVED);
	$room_id = (int) $booking['room_id'];
	$booking_date = $booking['booking_date'];
	$slot_id = (int) $booking['slot_id'];
	$conflict_query = 'SELECT booking_id FROM booking WHERE booking_id <> '
		. $booking_id . ' AND room_id = ' . $room_id
		. " AND booking_date = '" . db_escape($conn, $booking_date) . "'"
		. ' AND slot_id = ' . $slot_id . " AND status = '$approved_sql'";
	$conflict_result = mysqli_query($conn, $conflict_query);

	if (mysqli_num_rows($conflict_result) > 0) {
		flash('error', 'Cannot approve: that room and period is already approved.');
		redirect('coordinator/conflicts.php');
	}
}

$decision_sql = db_escape($conn, $decision);
$update_query = "UPDATE booking SET status = '$decision_sql' WHERE booking_id = "
	. $booking_id . " AND status = '$pending_sql'";
mysqli_query($conn, $update_query);

log_action(
	$conn,
	$_SESSION['user_id'],
	'Booking ' . $decision,
	'Booking #' . $booking_id
);
flash('success', 'Booking ' . strtolower($decision) . '.');
redirect('coordinator/pending_requests.php');
