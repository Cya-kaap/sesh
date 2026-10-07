<?php // filename: process/user_process.php
require "../config/constants.php";
$required_role = ROLE_ADMIN;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../includes/functions.php";
require "../includes/csrf.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ' . BASE_URL . '/admin/users.php');
	exit;
}
verify_csrf();
$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
$user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

if ($action === 'delete') {
	if ($user_id === (int) $_SESSION['user_id']) {
		flash('error', 'You cannot delete your own account.');
		header('Location: ' . BASE_URL . '/admin/users.php');
		exit;
	}
	mysqli_query($conn, "DELETE FROM user WHERE user_id = $user_id");
	flash('success', 'User deleted.');
	header('Location: ' . BASE_URL . '/admin/users.php');
	exit;
}

if ($action === 'reset_password') {
	if ($user_id === (int) $_SESSION['user_id']) {
		flash('error', 'Use your own profile to change your password.');
		header('Location: ' . BASE_URL . '/admin/users.php');
		exit;
	}

	$temporary_password = 'Sesh' . random_int(1000, 9999) . '!';
	$password_hash = password_hash($temporary_password, PASSWORD_DEFAULT);
	$password_hash_sql = mysqli_real_escape_string($conn, $password_hash);
	mysqli_query($conn, "UPDATE user SET password_hash = '$password_hash_sql' WHERE user_id = $user_id");
	log_action($conn, $_SESSION['user_id'], 'Password reset by admin', 'User #' . $user_id . ' password was reset.');
	flash('success', 'Password reset. Temporary password: ' . $temporary_password);
	header('Location: ' . BASE_URL . '/admin/users.php');
	exit;
}

$full_name = isset($_POST['full_name']) ? trim((string) $_POST['full_name']) : '';
$email_username = isset($_POST['email_username']) ? trim((string) $_POST['email_username']) : '';
$email = strtolower($email_username) . '@sesh.local';
$role_id = isset($_POST['role_id']) ? (int) $_POST['role_id'] : 0;
$department_raw = isset($_POST['department_id']) ? trim((string) $_POST['department_id']) : '';
if ($department_raw === '' || $department_raw === '0') {
	$department_id = null;
} else {
	$department_id = (int) $department_raw;
}
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';

/* Browser checks improve feedback; this server check remains the real gatekeeper. */
if ($password !== '') {
	$password_score = 0;
	if (strlen($password) >= 8) $password_score++;
	if (preg_match('/[A-Z]/', $password)) $password_score++;
	if (preg_match('/[a-z]/', $password)) $password_score++;
	if (preg_match('/[0-9]/', $password)) $password_score++;
	if (preg_match('/[^A-Za-z0-9]/', $password)) $password_score++;
	if ($password_score < 3) {
		flash('error', 'Password is too weak. Use at least 8 characters and two character types.');
		$destination = $user_id > 0
			? BASE_URL . '/admin/user_form.php?id=' . $user_id
			: BASE_URL . '/admin/user_form.php';
		header('Location: ' . $destination);
		exit;
	}
}

if ($user_id > 0) {
	$full_name_sql = mysqli_real_escape_string($conn, $full_name);
	$email_sql = mysqli_real_escape_string($conn, $email);
	if ($department_id === null) {
		$department_sql = 'NULL';
	} else {
		$department_sql = (string) (int) $department_id;
	}
	if ($password !== '') {
		$password_hash = password_hash($password, PASSWORD_DEFAULT);
		$password_hash_sql = mysqli_real_escape_string($conn, $password_hash);
		$query = "UPDATE user SET full_name = '$full_name_sql', email = '$email_sql', role_id = $role_id, department_id = $department_sql, password_hash = '$password_hash_sql' WHERE user_id = $user_id";
	} else {
		$query = "UPDATE user SET full_name = '$full_name_sql', email = '$email_sql', role_id = $role_id, department_id = $department_sql WHERE user_id = $user_id";
	}
} else {
	$password_hash = password_hash($password, PASSWORD_DEFAULT);
	$full_name_sql = mysqli_real_escape_string($conn, $full_name);
	$email_sql = mysqli_real_escape_string($conn, $email);
	if ($department_id === null) {
		$department_sql = 'NULL';
	} else {
		$department_sql = (string) (int) $department_id;
	}
	$password_hash_sql = mysqli_real_escape_string($conn, $password_hash);
	$query = "INSERT INTO user (full_name, email, role_id, department_id, password_hash) VALUES ('$full_name_sql', '$email_sql', $role_id, $department_sql, '$password_hash_sql')";
}

mysqli_query($conn, $query);
flash('success', 'User saved.');
header('Location: ' . BASE_URL . '/admin/users.php');
exit;