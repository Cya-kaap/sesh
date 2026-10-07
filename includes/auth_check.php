<?php
// Include database configuration and helper files
include 'functions.php'; 
include '../config/constants.php'; 
include '../config/database.php'; 

// Start the session if it is not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in by checking the session variable
if (!isset($_SESSION['user_id'])) {
    
    // Store an error message in the session to show on the login page
    $_SESSION['error'] = "Please log in first.";
    
    // Redirect the user back to the login page
    header("Location: login.php");
    exit(); // Always use exit() after header redirection
}
?>
