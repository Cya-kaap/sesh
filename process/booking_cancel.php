<?php
// filename: process/booking_cancel.php
require "../config/constants.php";
require "../includes/auth_check.php";
global $conn;
require "../includes/functions.php";
require "../includes/csrf.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ' . BASE_URL . '/faculty/my_bookings.php');
	exit;
}

verify_csrf();

// The booking ID comes from the form, but ownership is checked in the UPDATE.
$booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
$faculty_id = (int) $_SESSION['user_id'];

$cancelled_sql = mysqli_real_escape_string($conn, STATUS_CANCELLED);
$pending_sql = mysqli_real_escape_string($conn, STATUS_PENDING);
$query = "UPDATE booking SET status = '$cancelled_sql' WHERE booking_id = $booking_id AND faculty_id = $faculty_id AND status = '$pending_sql'";
mysqli_query($conn, $query);

// A faculty member can cancel only their own pending booking.
if (mysqli_affected_rows($conn) !== 1) {
	flash('error', 'That booking could not be cancelled.');
	header('Location: ' . BASE_URL . '/faculty/my_bookings.php');
	exit;
}

flash('success', 'Booking cancelled.');
header('Location: ' . BASE_URL . '/faculty/my_bookings.php');
exit;
