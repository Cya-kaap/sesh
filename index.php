<?php // filename: index.php

/*
 * This file is intentionally not a page. It dispatches a known session role
 * to the correct dashboard, keeping role routing in one small location.
 */
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
require "config/constants.php";
require "includes/functions.php";

if (!isset($_SESSION['user_id'])) {
	redirect('login.php');
}

if ($_SESSION['role'] === ROLE_ADMIN) {
	redirect('admin/dashboard.php');
}

if ($_SESSION['role'] === ROLE_COORDINATOR) {
	redirect('coordinator/dashboard.php');
}

if ($_SESSION['role'] === ROLE_FACULTY) {
	redirect('faculty/dashboard.php');
}

/* An unknown role is not trusted and cannot select a protected destination. */
redirect('login.php');
