<?php
/**
 * SESH - Edit User Account
 * Access: Admin only
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Admin']);

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, full_name, email, role, status FROM users WHERE id = ?");
$stmt->execute([$id]);
$targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    header('Location: index.php?msg=User+not+found');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = trim($_POST['role'] ?? 'Faculty');
    $status   = trim($_POST['status'] ?? 'Active');
    $password = $_POST['password'] ?? '';

    if (empty($fullName) || empty($email)) {
        $error = 'Name and email are required fields.';
    } else {
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("
                UPDATE users SET full_name = ?, email = ?, password_hash = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?
            ");
            $params = [$fullName, $email, $hashed, $role, $status, $id];
        } else {
            $updateStmt = $pdo->prepare("
                UPDATE users SET full_name = ?, email = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?
            ");
            $params = [$fullName, $email, $role, $status, $id];
        }

        if ($updateStmt->execute($params)) {
            header('Location: index.php?msg=User+updated+successfully');
            exit;
        } else {
            $error = 'Failed to update user account.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User | SESH</title>
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
        .help-text { font-size: 11px; color: #777; margin-top: 5px; }
        
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
        <p class="admin-label">ACCOUNT MODIFICATION</p>
        <h1>Edit User Profile</h1>

        <div class="form-card">
            <?php if ($error): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="edit.php?id=<?= $id ?>" method="POST">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($targetUser['full_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($targetUser['email']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Reset Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                    <p class="help-text">Only enter a password if you wish to overwrite the existing one.</p>
                </div>

                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" class="form-control" required>
                        <?php foreach (['Faculty', 'Coordinator', 'Admin'] as $r): ?>
                            <option value="<?= $r ?>" <?= ($targetUser['role'] === $r) ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Account Status *</label>
                    <select name="status" class="form-control">
                        <option value="Active" <?= ($targetUser['status'] === 'Active') ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($targetUser['status'] === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">SAVE CHANGES</button>
                    <a href="index.php" class="btn-cancel">CANCEL</a>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>