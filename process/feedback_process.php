<?php // filename: process/feedback_process.php

/*
 * This file processes one faculty feedback submission and renders no HTML.
 * Authorization and eligibility are checked again because submitted form
 * values, including booking_id, can be changed by a browser user.
 */
require "../includes/auth_check.php";
$required_role = ROLE_FACULTY;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../config/constants.php";
require "../includes/functions.php";
require "../includes/csrf.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ' . BASE_URL . '/faculty/feedback.php');
	exit;
}

if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
	flash('error', 'Invalid request token.');
	header('Location: ' . BASE_URL . '/faculty/feedback.php');
	exit;
}

$booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
$rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
$equipment_issues = isset($_POST['equipment_issues']) ? trim((string) $_POST['equipment_issues']) : '';
$overall_condition = isset($_POST['overall_condition']) ? trim((string) $_POST['overall_condition']) : '';
$faculty_id = (int) $_SESSION['user_id'];
$feedback_url = BASE_URL . '/faculty/feedback.php';

if ($rating < 1 || $rating > 5) {
	flash('error', 'Please select a rating from 1 to 5.');
	header('Location: ' . $feedback_url);
	exit;
}

/*
 * This ownership/status check prevents a faculty member from submitting
 * feedback for another person's booking or for an uncompleted session.
 */
$approved_sql = mysqli_real_escape_string($conn, STATUS_APPROVED);
$booking_query = "SELECT booking_date, slot_id, status,
		booking_date < CURDATE() AS before_today,
		booking_date = CURDATE() AS is_today
	FROM booking
	WHERE booking_id = $booking_id AND faculty_id = $faculty_id AND status = '$approved_sql'";
$booking_result = mysqli_query($conn, $booking_query);
$booking = null;
if (mysqli_num_rows($booking_result) > 0) {
	$booking = mysqli_fetch_assoc($booking_result);
}

if ($booking === null) {
	flash('error', 'That booking is not eligible for feedback.');
	header('Location: ' . $feedback_url);
	exit;
}

$booking_is_complete = (int) $booking['before_today'] === 1;
if (!$booking_is_complete && (int) $booking['is_today'] === 1) {
	$slot_id = (int) $booking['slot_id'];
	$slot_result = mysqli_query(
		$conn,
		"SELECT slot_id FROM timeslot WHERE slot_id = $slot_id AND end_time <= CURTIME()"
	);
	$booking_is_complete = mysqli_num_rows($slot_result) > 0;
}

if (!$booking_is_complete) {
	flash('error', 'That booking is not eligible for feedback.');
	header('Location: ' . $feedback_url);
	exit;
}

$existing_feedback_result = mysqli_query(
	$conn,
	"SELECT feedback_id FROM feedback WHERE booking_id = $booking_id"
);
if (mysqli_num_rows($existing_feedback_result) > 0) {
	flash('error', 'That booking already has feedback.');
	header('Location: ' . $feedback_url);
	exit;
}

/*
 * Feedback owns feedback data only. Changing room.status here would cross
 * into the admin room-management module, so an administrator must review
 * negative feedback and change operational status through that module.
 */
$equipment_sql = mysqli_real_escape_string($conn, $equipment_issues);
$condition_sql = mysqli_real_escape_string($conn, $overall_condition);
$insert_query = "INSERT INTO feedback (booking_id, rating, equipment_issues, overall_condition) VALUES ("
	. $booking_id . ', ' . $rating . ", '$equipment_sql', '$condition_sql')";
mysqli_query($conn, $insert_query);

log_action(
	$conn,
	$faculty_id,
	'Feedback submitted',
	'Feedback for booking #' . $booking_id
);

flash('success', 'Thanks for your feedback.');
header('Location: ' . $feedback_url);
exit;
