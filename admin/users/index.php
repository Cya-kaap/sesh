<?php
/**
 * SESH - User Management (Index)
 * Access: Admin only
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Admin']);
$currentUser = currentUser();

// Fetch users
$stmt = $pdo->query("SELECT id, full_name, email, role, status, created_at FROM users ORDER BY id DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | SESH</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .admin-dashboard { min-height: 100vh; background: #f4f3ef; color: #111; }
        .admin-nav { min-height: 80px; padding: 0 6vw; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #d5d5d0; background: #f4f3ef; }
        .admin-logo { font-size: 32px; font-weight: 900; letter-spacing: -0.08em; color: #111; text-decoration: none; }
        .admin-nav-links { display: flex; align-items: center; gap: 30px; }
        .admin-nav-links a { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: #555; text-decoration: none; }
        .admin-nav-links a:hover { color: #111; }
        .admin-nav-links .logout { padding: 12px 18px; background: #111; color: #fff; }
        .admin-main { width: 88%; max-width: 1400px; margin: 0 auto; padding: 70px 0; }
        .admin-header { display: flex; justify-content: space-between; align-items: flex-end; padding-bottom: 35px; border-bottom: 1px solid #ccc; }
        .admin-label { margin-bottom: 15px; font-size: 10px; font-weight: 700; letter-spacing: 0.2em; color: #777; }
        .admin-header h1 { margin: 0; font-size: clamp(40px, 5vw, 65px); line-height: 0.9; letter-spacing: -0.06em; }
        
        .btn-action { display: inline-block; padding: 12px 24px; background: #111; color: #fff; font-size: 10px; font-weight: 700; letter-spacing: 0.12em; text-decoration: none; border: none; cursor: pointer; }
        .btn-action:hover { background: #333; }
        .alert-msg { padding: 15px; background: #e2f0d9; border: 1px solid #b2d8a0; color: #2b542c; font-size: 12px; margin-top: 30px; }

        .data-table { width: 100%; border-collapse: collapse; margin-top: 40px; }
        .data-table th, .data-table td { padding: 16px 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 13px; }
        .data-table th { font-size: 10px; font-weight: 700; letter-spacing: 0.1em; color: #777; background: #eae8e1; }
        
        .badge { display: inline-block; padding: 4px 8px; font-size: 9px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }

        .table-actions { display: flex; gap: 10px; }
        .table-actions a { font-size: 11px; font-weight: 700; text-decoration: none; color: #111; border-bottom: 1px solid #111; }
    </style>
</head>
<body>
<div class="admin-dashboard">
    <header class="admin-nav">
        <a href="../index.php" class="admin-logo">SESH</a>
        <nav class="admin-nav-links">
            <a href="../index.php">DASHBOARD</a>
            <a href="../../logout.php" class="logout">LOGOUT</a>
        </nav>
    </header>

    <main class="admin-main">
        <section class="admin-header">
            <div>
                <p class="admin-label">02 / ACCOUNT CONTROL</p>
                <h1>User Management</h1>
            </div>
            <div>
                <a href="create.php" class="btn-action">+ CREATE NEW USER</a>
            </div>
        </section>

        <?php if (!empty($message)): ?>
            <div class="alert-msg"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>NAME</th>
                    <th>EMAIL ADDRESS</th>
                    <th>ROLE</th>
                    <th>STATUS</th>
                    <th>CREATED AT</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="6">No users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['role']) ?></td>
                            <td>
                                <span class="badge <?= ($u['status'] === 'Active') ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= htmlspecialchars($u['status']) ?>
                                </span>
                            </td>
                            <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                            <td class="table-actions">
                                <a href="edit.php?id=<?= $u['id'] ?>">EDIT</a>
                                <?php if ($u['id'] !== $currentUser['id']): ?>
                                    <a href="delete.php?id=<?= $u['id'] ?>" onclick="return confirm('Are you sure you want to delete this user account?');" style="color: #c9302c; border-color: #c9302c;">DELETE</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>
</body>
</html>