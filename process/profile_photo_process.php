<?php // filename: process/profile_photo_process.php
require "../config/constants.php";
require "../includes/auth_check.php";
require "../includes/functions.php";
require "../includes/csrf.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ' . BASE_URL . '/index.php');
	exit;
}

verify_csrf();
$profile_page = 'faculty/profile.php';
if ($_SESSION['role'] === ROLE_ADMIN) {
	$profile_page = 'admin/profile.php';
} elseif ($_SESSION['role'] === ROLE_COORDINATOR) {
	$profile_page = 'coordinator/profile.php';
}
$profile_url = BASE_URL . '/' . $profile_page;

if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
	flash('error', 'Please choose a profile photo to upload.');
	header('Location: ' . $profile_url);
	exit;
}

$file = $_FILES['profile_photo'];

// Check the uploaded file before inspecting or moving it.
if (!is_uploaded_file($file['tmp_name'])) {
	flash('error', 'The uploaded photo is invalid. Please try again.');
	header('Location: ' . $profile_url);
	exit;
}

if ($file['size'] > 2 * 1024 * 1024) {
	flash('error', 'Profile photos must be 2 MB or smaller.');
	header('Location: ' . $profile_url);
	exit;
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$extension_mime_types = [
	'jpg' => 'image/jpeg',
	'jpeg' => 'image/jpeg',
	'png' => 'image/png',
	'webp' => 'image/webp',
];
$image_info = getimagesize($file['tmp_name']);

if (!isset($extension_mime_types[$extension]) || $image_info === false || $image_info['mime'] !== $extension_mime_types[$extension]) {
	flash('error', 'Use a JPG, PNG, or WebP image.');
	header('Location: ' . $profile_url);
	exit;
}

$directory = '../images/profiles/';
if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
	flash('error', 'The profile photo directory could not be created.');
	header('Location: ' . $profile_url);
	exit;
}

$user_id = (int) $_SESSION['user_id'];
foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
	$old_file = $directory . $user_id . '.' . $extension;
	if (is_file($old_file)) {
		unlink($old_file);
	}
}

$target = $directory . $user_id . '.' . $extension;
if (!move_uploaded_file($file['tmp_name'], $target)) {
	flash('error', 'The profile photo could not be saved.');
	header('Location: ' . $profile_url);
	exit;
}

flash('success', 'Profile photo updated.');
header('Location: ' . $profile_url);
exit;
