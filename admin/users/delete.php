<?php
/**
 * SESH - Delete User Account
 * Access: Admin only
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Admin']);

$currentUser = currentUser();
$id = (int) ($_GET['id'] ?? 0);

// Prevent user from deleting their own account
if ($id > 0 && $id !== $currentUser['id']) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt->execute([$id])) {
        header('Location: index.php?msg=User+deleted+successfully');
        exit;
    } else {
        header('Location: index.php?msg=Failed+to+delete+user');
        exit;
    }
}

header('Location: index.php');
exit;