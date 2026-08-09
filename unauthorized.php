<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Resolve the user's dashboard based on session role.
 */
function getReturnUrl(): string
{
    $role = $_SESSION['role'] ?? '';

    switch ($role) {
        case 'Admin':
            return '/sesh/admin/';
        case 'Coordinator':
            return '/sesh/coordinator/';
        case 'Faculty':
            return '/sesh/faculty/';
        default:
            return isset($_SESSION['user_id']) ? '/sesh/dashboard.php' : '/sesh/login.php';
    }
}

$returnUrl = getReturnUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - SESH</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin-top: 100px;
            background: #f8f9fa;
            color: #333;
        }
        h1 {
            color: #e74c3c;
            margin-bottom: 10px;
        }
        p {
            color: #666;
            margin-bottom: 25px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2c3e50;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background 0.2s ease;
        }
        .btn:hover {
            background: #1a252f;
        }
    </style>
</head>
<body>

    <h1>Access Denied</h1>
    <p>You don't have permission to access this page.</p>

    <a href="<?= htmlspecialchars($returnUrl) ?>" class="btn">Return to Dashboard</a>

</body>
</html>