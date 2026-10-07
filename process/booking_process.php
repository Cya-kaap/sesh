<?php
// filename: process/booking_process.php
require "../config/constants.php";
require "../includes/auth_check.php";
$required_role = ROLE_FACULTY;
require "../includes/role_check.php";
global $conn;
require "../includes/functions.php";
require "../includes/csrf.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ' . BASE_URL . '/faculty/book_room.php');
	exit;
}

verify_csrf();

// Read submitted values, then validate them again on the server.
$room_id = isset($_POST['room_id']) ? (int) $_POST['room_id'] : 0;
$slot_id = isset($_POST['slot_id']) ? (int) $_POST['slot_id'] : 0;
$booking_date = isset($_POST['booking_date']) ? trim((string) $_POST['booking_date']) : '';
$purpose = isset($_POST['purpose']) ? trim((string) $_POST['purpose']) : '';
$faculty_id = (int) $_SESSION['user_id'];
$booking_page_url = BASE_URL . '/faculty/book_room.php';


// Reject malformed requests and dates that have already passed.
if (
	$room_id < 1
	|| $slot_id < 1
	|| $purpose === ''
	|| !is_valid_date($booking_date)
	|| $booking_date < date('Y-m-d')
) {
	flash('error', 'Please provide a valid future booking request.');
	header('Location: ' . $booking_page_url);
	exit;
}

// Do not trust the room ID from the form; it must refer to an available room.
$room_result = mysqli_query(
	$conn,
	"SELECT room_id FROM room WHERE room_id = $room_id AND room_status = 'Available'"
);
if (mysqli_num_rows($room_result) !== 1) {
	flash('error', 'That room is not available for booking.');
	header('Location: ' . $booking_page_url);
	exit;
}

// Confirm that the selected period exists before checking availability.
$slot_result = mysqli_query($conn, "SELECT slot_id FROM timeslot WHERE slot_id = $slot_id");
if (mysqli_num_rows($slot_result) !== 1) {
	flash('error', 'Please select a valid timeslot.');
	header('Location: ' . $booking_page_url);
	exit;
}

// Pending requests reserve the slot too, so they must block another request.
$booking_date_sql = mysqli_real_escape_string($conn, $booking_date);
$conflict_query = "SELECT booking_id FROM booking WHERE room_id = $room_id AND booking_date = '$booking_date_sql' AND slot_id = $slot_id AND status IN ('Pending', 'Approved')";
$conflict_result = mysqli_query($conn, $conflict_query);
if (mysqli_num_rows($conflict_result) > 0) {
	flash('error', 'That room already has an active booking request for the selected period.');
	header('Location: ' . $booking_page_url);
	exit;
}

// Insert only after all validation and conflict checks have passed.
$purpose_sql = mysqli_real_escape_string($conn, $purpose);
$insert_query = "INSERT INTO booking (faculty_id, room_id, slot_id, booking_date, purpose) VALUES ($faculty_id, $room_id, $slot_id, '$booking_date_sql', '$purpose_sql')";
mysqli_query($conn, $insert_query);

log_action($conn, $faculty_id, 'Booking requested', 'Booking #' . mysqli_insert_id($conn));
flash('success', 'Booking request submitted.');
header('Location: ' . BASE_URL . '/faculty/my_bookings.php');
exit;
