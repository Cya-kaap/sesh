<?php // filename: process/room_process.php
require "../config/constants.php";
$required_role = ROLE_ADMIN;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../includes/functions.php";
require "../includes/csrf.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ' . BASE_URL . '/admin/rooms.php');
	exit;
}
verify_csrf();
$room_id = isset($_POST['room_id']) ? (int) $_POST['room_id'] : 0;
$room_number = isset($_POST['room_number']) ? trim((string) $_POST['room_number']) : '';
$building = isset($_POST['building']) ? trim((string) $_POST['building']) : '';
$floor = isset($_POST['floor']) ? trim((string) $_POST['floor']) : '';
$capacity = isset($_POST['capacity']) ? (int) $_POST['capacity'] : 0;
$room_type = isset($_POST['room_type']) ? trim((string) $_POST['room_type']) : '';
$room_status = isset($_POST['room_status']) ? trim((string) $_POST['room_status']) : '';

$room_number_sql = mysqli_real_escape_string($conn, $room_number);
$floor_sql = mysqli_real_escape_string($conn, $floor);
$building_sql = mysqli_real_escape_string($conn, $building);
$room_type_sql = mysqli_real_escape_string($conn, $room_type);
$room_status_sql = mysqli_real_escape_string($conn, $room_status);

if ($room_id > 0) {
    $query = "UPDATE room SET room_number = '$room_number_sql', capacity = $capacity, floor = '$floor_sql', building = '$building_sql', room_type = '$room_type_sql', room_status = '$room_status_sql' WHERE room_id = $room_id";
} else {
    $query = "INSERT INTO room (room_number, capacity, floor, building, room_type, room_status) VALUES ('$room_number_sql', $capacity, '$floor_sql', '$building_sql', '$room_type_sql', '$room_status_sql')";
}

mysqli_query($conn, $query);
flash('success', 'Room saved.');
header('Location: ' . BASE_URL . '/admin/rooms.php');
exit;