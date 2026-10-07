<?php // filename: logout.php

/*
 * Logout is a silent action rather than a page. The session is started so
 * the same code works whether another entry point started it or not.
 */
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
require "includes/functions.php";

/* Removing all session data before destruction prevents identity reuse. */
session_unset();
session_destroy();

redirect('login.php');
