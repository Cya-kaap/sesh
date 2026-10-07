<?php // filename: includes/functions.php
// Some shared functions used by the pages.
if (!function_exists('db_escape')) {
function profile_photo_url($user_id)
{
	$directory = '../images/profiles/';
	$base_url = BASE_URL . '/images/profiles/';

	$jpg_file = $directory . $user_id . '.jpg';
	if (is_file($jpg_file)) {
		return $base_url . $user_id . '.jpg';
	} else if (is_file($directory . $user_id . '.jpeg')) {
		return $base_url . $user_id . '.jpeg';
	} else if (is_file($directory . $user_id . '.png')) {
		return $base_url . $user_id . '.png';
	} else if (is_file($directory . $user_id . '.webp')) {
		return $base_url . $user_id . '.webp';
	}

	return null;
}

// Send the browser to another page.
function redirect($path)
{
	header('Location: /sesh/' . $path);
	exit;
}

// Save a message for the next page.
function flash($type, $message)
{
	$_SESSION['flash'] = [
		'type' => $type,
		'message' => $message,
	];
}

// Get the saved message and remove it.
function get_flash()
{
	if (!isset($_SESSION['flash'])) {
		return null;
	}

	$message = $_SESSION['flash'];
	unset($_SESSION['flash']);
	return $message;
}

// Check if this request uses POST.
function is_post()
{
	return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function post_value($key)
{
	return trim($_POST[$key]);
}

// Check the date parts.
function is_valid_date($value)
{
	$date_parts = explode('-', $value);
	if (count($date_parts) !== 3) {
		return false;
	}

	return checkdate($date_parts[1], $date_parts[2], $date_parts[0]);
}

// Escape text before putting it into a SQL string.
function db_escape($conn, $value)
{
	return mysqli_real_escape_string($conn, $value);
}

// Add an action to the log table.
function log_action($conn, $user_id, $action, $description)
{
	if ($user_id === null) {
		$query = "INSERT INTO systemlog (user_id, action, description) VALUES (NULL, '" . db_escape($conn, $action) . "', '" . db_escape($conn, $description) . "')";
	} else {
		$query = "INSERT INTO systemlog (user_id, action, description) VALUES ($user_id, '" . db_escape($conn, $action) . "', '" . db_escape($conn, $description) . "')";
	}

	return mysqli_query($conn, $query);
}
}
