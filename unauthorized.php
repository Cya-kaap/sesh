<?php
session_start();
session_destroy(); 
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Access Denied - SRFMS</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            text-align: center; 
            margin-top: 100px; 
            background: #f8f9fa;
        }
        h1 { color: #e74c3c; }
        .btn { 
            display: inline-block; 
            padding: 10px 20px; 
            background: #2c3e50; 
            color: white; 
            text-decoration: none; 
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>Access Denied</h1>
    <p>You don't have permission to access this page.</p>
    
    <a href="/sesh/login.php" class="btn">Go to Login</a>
    <br><br>
    <a href="/sesh/logout.php" class="btn" style="background:#e74c3c;">Logout & Try Again</a>
</body>
</html>