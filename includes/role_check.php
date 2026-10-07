<?php // filename: includes/role_check.php

/*
 * This boundary handles authorization after authentication has succeeded.
 * The page declares its required role before including this file; no role
 * is accepted from a request parameter, form field, or browser cookie.
 */
require "auth_check.php";

if (!isset($required_role) || !isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
	if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
		header('Location: /sesh/admin/dashboard.php');
		exit;
	}

	if (isset($_SESSION['role']) && $_SESSION['role'] === 'Coordinator') {
		header('Location: /sesh/coordinator/dashboard.php');
		exit;
	}

	if (isset($_SESSION['role']) && $_SESSION['role'] === 'Faculty') {
		header('Location: /sesh/faculty/dashboard.php');
		exit;
	}

	header('Location: /sesh/login.php');
	exit;
}
