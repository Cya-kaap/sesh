<?php // filename: config/database.php
if (defined('SESH_DATABASE_LOADED')) {
	return;
}

$host = '127.0.0.1';
$username = 'root';
$password = '';
$database = 'sesh2';

mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
	die('Database connection failed.');
}
if (!mysqli_set_charset($conn, 'utf8mb4')) {
	die('Database connection failed.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
define('SESH_DATABASE_LOADED', true);
