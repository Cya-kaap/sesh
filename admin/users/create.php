<?php
/**
 * SESH - Create User Account
 * Access: Admin only
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Admin']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = trim($_POST['role'] ?? 'Faculty');
    $status   = trim($_POST['status'] ?? 'Active');

    if (empty($fullName) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check for existing email
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            $error = 'A user with this email address already exists.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, email, password_hash, role, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            if ($stmt->execute([$fullName, $email, $hashedPassword, $role, $status])) {
                header('Location: index.php?msg=User+created+successfully');
                exit;
            } else {
                $error = 'Failed to create user account.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User | SESH</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .admin-dashboard { min-height: 100vh; background: #f4f3ef; color: #111; }
        .admin-nav { min-height: 80px; padding: 0 6vw; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #d5d5d0; background: #f4f3ef; }
        .admin-logo { font-size: 32px; font-weight: 900; letter-spacing: -0.08em; color: #111; text-decoration: none; }
        .admin-nav-links a { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: #555; text-decoration: none; }
        .admin-main { width: 88%; max-width: 800px; margin: 0 auto; padding: 70px 0; }
        .admin-header h1 { margin: 0; font-size: 40px; letter-spacing: -0.05em; }
        .admin-label { font-size: 10px; font-weight: 700; letter-spacing: 0.2em; color: #777; margin-bottom: 10px; }
        
        .form-card { background: #fff; border: 1px solid #ddd; padding: 40px; margin-top: 40px; }
        .form-group { margin-bottom: 24px; }
        .form-group label { display: block; font-size: 10px; font-weight: 700; letter-spacing: 0.15em; color: #555; margin-bottom: 8px; text-transform: uppercase; }
        .form-control { width: 100%; padding: 14px; background: #f9f9f8; border: 1px solid #ccc; font-size: 14px; box-sizing: border-box; }
        .form-control:focus { border-color: #111; outline: none; background: #fff; }
        
        .error-msg { padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; font-size: 12px; margin-bottom: 20px; }
        .form-actions { display: flex; gap: 15px; margin-top: 30px; }
        .btn-submit { padding: 14px 28px; background: #111; color: #fff; font-size: 10px; font-weight: 700; letter-spacing: 0.12em; border: none; cursor: pointer; }
        .btn-cancel { padding: 14px 28px; background: transparent; color: #555; font-size: 10px; font-weight: 700; letter-spacing: 0.12em; text-decoration: none; border: 1px solid #ccc; }
    </style>
</head>
<body>
<div class="admin-dashboard">
    <header class="admin-nav">
        <a href="../index.php" class="admin-logo">SESH</a>
        <nav class="admin-nav-links">
            <a href="index.php">BACK TO USERS</a>
        </nav>
    </header>

    <main class="admin-main">
        <p class="admin-label">USER REGISTRATION</p>
        <h1>Create Account</h1>

        <div class="form-card">
            <?php if ($error): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="create.php" method="POST">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g., Dr. Jane Smith" required>
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" class="form-control" placeholder="e.g., j.smith@sesh.edu" required>
                </div>

                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="Minimum 8 characters" required>
                </div>

                <div class="form-group">
                    <label>System Role *</label>
                    <select name="role" class="form-control" required>
                        <option value="Faculty">Faculty</option>
                        <option value="Coordinator">Coordinator</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Account Status *</label>
                    <select name="status" class="form-control">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">CREATE ACCOUNT</button>
                    <a href="index.php" class="btn-cancel">CANCEL</a>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>