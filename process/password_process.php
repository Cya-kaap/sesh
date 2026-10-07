<?php // filename: process/password_process.php
require "../config/constants.php";
require "../includes/auth_check.php";
global $conn;
require "../includes/functions.php";
require "../includes/csrf.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ' . BASE_URL . '/index.php');
	exit;
}

verify_csrf();
$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
$current_password = isset($_POST['current_password']) ? (string) $_POST['current_password'] : '';
$new_password = isset($_POST['new_password']) ? (string) $_POST['new_password'] : '';
$user_id = (int) $_SESSION['user_id'];
$profile_page = 'faculty/profile.php';
if ($_SESSION['role'] === ROLE_COORDINATOR) {
	$profile_page = 'coordinator/profile.php';
}
$profile_url = BASE_URL . '/' . $profile_page;

if ($action === 'request_reset') {
	log_action($conn, $user_id, 'Password reset requested', 'User requested administrator assistance.');
	flash('success', 'Your password reset request has been sent to the administrator.');
	header('Location: ' . $profile_url);
	exit;
}

$user_result = mysqli_query($conn, "SELECT password_hash FROM user WHERE user_id = $user_id");
$user = mysqli_fetch_assoc($user_result);

if (!$user || !password_verify($current_password, $user['password_hash'])) {
	flash('error', 'Current password is incorrect. Use Request reset to contact an administrator.');
	header('Location: ' . $profile_url);
	exit;
}

$score = 0;
if (strlen($new_password) >= 8) $score++;
if (preg_match('/[A-Z]/', $new_password)) $score++;
if (preg_match('/[a-z]/', $new_password)) $score++;
if (preg_match('/[0-9]/', $new_password)) $score++;
if (preg_match('/[^A-Za-z0-9]/', $new_password)) $score++;

if ($score < 3) {
	flash('error', 'New password is too weak. Use at least 8 characters and two character types.');
	header('Location: ' . $profile_url);
	exit;
}

$password_hash = password_hash($new_password, PASSWORD_DEFAULT);
$password_hash_sql = mysqli_real_escape_string($conn, $password_hash);
mysqli_query($conn, "UPDATE user SET password_hash = '$password_hash_sql' WHERE user_id = $user_id");
log_action($conn, $user_id, 'Password changed', 'User changed their password.');
flash('success', 'Your password has been updated.');
header('Location: ' . $profile_url);
exit;
