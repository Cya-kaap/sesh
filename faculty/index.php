<?php
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['Faculty']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Faculty Dashboard - SESH</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; }
        ul { list-style: none; padding: 0; }
        li { margin: 12px 0; }
        a { color: #2563eb; text-decoration: none; font-size: 1.1rem; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<h1>Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Faculty') ?>!</h1>
<p>You are logged in as <strong>Faculty</strong>.</p>

<hr>

<ul>
    <li><a href="../modules/bookings/create.php">Request a Room</a></li>
    <li><a href="../modules/bookings/list.php">My Bookings</a></li>
    <li><a href="../logout.php">Logout</a></li>
</ul>

</body>
</html>