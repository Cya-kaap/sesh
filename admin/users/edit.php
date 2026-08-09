<?php
/**
 * SESH - Edit User Account
 * Access: Admin only
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['Admin']);

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, full_name, email, role, status, programme_id FROM users WHERE id = ?");
$stmt->execute([$id]);
$targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    setFlash('error', 'User not found.');
    header('Location: index.php');
    exit;
}

$programmes = $pdo->query("SELECT id, name FROM programmes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$validProgrammeIds = array_column($programmes, 'id');
$error = '';

$fullName    = $targetUser['full_name'];
$email       = $targetUser['email'];
$role        = $targetUser['role'];
$status      = $targetUser['status'];
$programmeId = $targetUser['programme_id'] !== null ? (string) $targetUser['programme_id'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        $error = 'Your session expired. Please try again.';

    } else {

        $fullName    = trim($_POST['full_name'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $role        = trim($_POST['role'] ?? 'Faculty');
        $status      = trim($_POST['status'] ?? 'Active');
        $programmeId = trim($_POST['programme_id'] ?? '');
        $password    = $_POST['password'] ?? '';

        if (empty($fullName) || empty($email)) {
            $error = 'Name and email are required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password !== '' && strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!in_array($role, ['Faculty', 'Coordinator', 'Admin'], true)) {
            $error = 'Please select a valid role.';
        } elseif ($role === 'Coordinator' && !ctype_digit($programmeId)) {
            $error = 'Coordinator accounts require a Programme assignment.';
        } elseif ($programmeId !== '' && !in_array((int) $programmeId, $validProgrammeIds, true)) {
            $error = 'Please select a valid Programme.';
        } else {

            $dupStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $dupStmt->execute([$email, $id]);

            if ($dupStmt->fetch()) {
                $error = 'Another account already uses that email address.';
            } else {

                try {
                    $programmeIdValue = $programmeId !== '' ? (int) $programmeId : null;

                    $departmentName = null;
                    foreach ($programmes as $p) {
                        if ((int) $p['id'] === $programmeIdValue) {
                            $departmentName = $p['name'];
                            break;
                        }
                    }

                    if (!empty($password)) {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $updateStmt = $pdo->prepare("
                            UPDATE users SET full_name=?, email=?, password_hash=?, role=?, department=?, programme_id=?, status=?, updated_at=NOW() WHERE id=?
                        ");
                        $params = [$fullName, $email, $hashed, $role, $departmentName, $programmeIdValue, $status, $id];
                    } else {
                        $updateStmt = $pdo->prepare("
                            UPDATE users SET full_name=?, email=?, role=?, department=?, programme_id=?, status=?, updated_at=NOW() WHERE id=?
                        ");
                        $params = [$fullName, $email, $role, $departmentName, $programmeIdValue, $status, $id];
                    }

                    $updateStmt->execute($params);
                    setFlash('success', 'User updated successfully.');
                    header('Location: index.php');
                    exit;

                } catch (PDOException $e) {
                    $error = 'Unable to update this account. Please try again.';
                }
            }
        }
    }
}

$csrfToken = generateCsrfToken();
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
        <nav class="admin-nav-links"><a href="index.php">BACK TO USERS</a></nav>
    </header>

    <main class="admin-main">
        <p class="admin-label">ACCOUNT MODIFICATION</p>
        <h1>Edit User Profile</h1>

        <div class="form-card">
            <?php if ($error): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="edit.php?id=<?= (int) $id ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($fullName) ?>" required>
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
                </div>

                <div class="form-group">
                    <label>Reset Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password" minlength="8">
                    <p class="help-text">Only enter a password if you wish to overwrite the existing one (min. 8 characters).</p>
                </div>

                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" class="form-control" required>
                        <?php foreach (['Faculty', 'Coordinator', 'Admin'] as $r): ?>
                            <option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Programme</label>
                    <select name="programme_id" class="form-control">
                        <option value="">— Not applicable —</option>
                        <?php foreach ($programmes as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (string) $p['id'] === $programmeId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="help-text">Required for Coordinator accounts.</p>
                </div>

                <div class="form-group">
                    <label>Account Status *</label>
                    <select name="status" class="form-control">
                        <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
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