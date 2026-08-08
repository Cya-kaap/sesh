<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/db.php';

requireRole(['Faculty', 'Coordinator', 'Admin']);

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: list.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Admin/Coordinator can cancel any pending booking.
| Faculty can cancel only their own pending booking.
|--------------------------------------------------------------------------
*/

if (in_array($role, ['Admin', 'Coordinator'], true)) {

    $stmt = $pdo->prepare("
        UPDATE bookings
        SET status = 'cancelled'
        WHERE id = :id
          AND status = 'pending'
    ");

    $stmt->execute([
        ':id' => $id
    ]);

} else {

    $stmt = $pdo->prepare("
        UPDATE bookings
        SET status = 'cancelled'
        WHERE id = :id
          AND user_id = :user_id
          AND status = 'pending'
    ");

    $stmt->execute([
        ':id'      => $id,
        ':user_id' => $user_id
    ]);
}

header('Location: list.php');
exit;