<?php
/**
 * SESH - Delete User Account
 * Access: Admin only
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Your session expired. Please try again.');
    header('Location: index.php');
    exit;
}

$currentUser = currentUser();
$id = (int) ($_POST['id'] ?? 0);

if ($id < 1 || $id === (int) $currentUser['id']) {
    setFlash('error', $id === (int) $currentUser['id']
        ? 'You cannot delete your own account while logged in.'
        : 'Invalid user.');
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        setFlash('success', 'User deleted successfully.');
    } else {
        setFlash('error', 'User not found.');
    }
} catch (PDOException $e) {
    // Most likely the FK constraint on bookings.user_id (users who have
    // ever made a booking cannot be hard-deleted — set them Inactive instead).
    setFlash('error', 'This user cannot be deleted because they have existing bookings on record. Set their status to "Inactive" instead.');
}

header('Location: index.php');
exit;