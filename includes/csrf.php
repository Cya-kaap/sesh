<?php
/*
============================================================
PROJECT NAME : SESH ROOM BOOKING SYSTEM
COURSE       : BCA SEMESTER IV
SUBJECT      : WEB TECHNOLOGY LAB
ROLL NO      :
FILE         : includes/csrf.php
============================================================
This file contains functions used for form token generation.
The token helps check that a submitted form came from this site.
============================================================
*/

// Check whether the main token function has already been declared.
// This check prevents a fatal error when this file is included more than once.
if (!function_exists('generate_csrf_token')) {

	// Function to create or return the token stored in the current session.
	function generate_csrf_token()
	{
		// Step 1: Check whether the session already has a token value.
		if (empty($_SESSION['csrf_token'])) {
			// Step 2: Create 32 random bytes using the built-in secure function.
			// Convert the random bytes to hexadecimal text for use in an HTML field.
			$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		}

		// Step 3: Return the token so the form can include it.
		return $_SESSION['csrf_token'];
	}

	// Function to compare the submitted token with the saved session token.
	function verify_csrf_token($token)
	{
		// Use the built-in timing-safe comparison function for the two token strings.
		return hash_equals(
			(string) (isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''),
			(string) $token
		);
	}

	// Function to print a hidden token field inside an HTML form.
	function csrf_field()
	{
		// Start writing the hidden input element to the page.
		echo '<input type="hidden" name="csrf_token" value="'
			// Escape the token so it is safe to place inside an HTML attribute.
			. htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8')
			// Finish the value and close the input element.
			. '">';
	}

	// Function to check the token submitted through a POST form.
	function verify_csrf()
	{
		// Read the submitted field when present, or use an empty value when missing.
		if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
			// Return the HTTP status used for a request with an invalid token.
			http_response_code(419);
			// Stop processing and display error message.
			exit('ERROR: CSRF Verification Failed! Access Denied.');
		}
	}
}
