<?php // filename: process/login_process.php

/*
 * This file is a POST processor, not a page. Keeping database lookup and
 * password verification here prevents the public login form from knowing
 * anything about users or the database connection.
 */
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
require "../config/database.php";
global $conn;
require "../config/constants.php";
require "../includes/functions.php";
require "../includes/csrf.php";

/* A GET request must never reach credential or database processing. */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ' . BASE_URL . '/login.php');
	exit;
}

/*
 * A failed CSRF check is handled like every other failed login. This keeps
 * the response simple and prevents attackers from learning request details.
 */
if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
	flash('error', 'Invalid email or password.');
	header('Location: ' . BASE_URL . '/login.php');
	exit;
}

$email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';

/* Look up the user first, then resolve the role from its stored role ID. */
$email_sql = mysqli_real_escape_string($conn, $email);
$query = "SELECT user_id, full_name, email, password_hash, role_id FROM user WHERE email = '$email_sql'";
$user_result = mysqli_query($conn, $query);
$user = null;
$role = null;
if (mysqli_num_rows($user_result) > 0) {
	$user = mysqli_fetch_assoc($user_result);
	$role_id = (int) $user['role_id'];
	$role_result = mysqli_query($conn, "SELECT role_name FROM role WHERE role_id = $role_id");
	if (mysqli_num_rows($role_result) > 0) {
		$role = mysqli_fetch_assoc($role_result);
	}
}

/*
 * One generic message avoids revealing whether an email exists. That makes
 * account enumeration harder and is safer than separate error messages.
 */
if ($user === null || $role === null || !password_verify($password, $user['password_hash'])) {
	flash('error', 'Invalid email or password.');
	header('Location: ' . BASE_URL . '/login.php');
	exit;
}

/* Store the identity before redirecting so the next request can authenticate. */
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $role['role_name'];

log_action($conn, $user['user_id'], 'Login', 'Successful login');

/* index.php is the single role dispatcher for successful authentication. */
header('Location: ' . BASE_URL . '/index.php');
exit;
