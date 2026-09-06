<?php

/**
 * SESH - Admin: Cancel Booking (Override)
 *
 * Purpose:
 *   Lets Admin cancel an already-Approved booking. POST-only,
 *   no view of its own — always redirects back to index.php.
 *
 * Requires tables:
 *   - bookings
 *
 * Access:
 *   Admin only.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireWritePermission(FEATURE_BOOKINGS_CANCEL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Your session expired. Please try again.');
    header('Location: index.php');
    exit;
}

$bookingId = (int) ($_POST['id'] ?? 0);

if ($bookingId < 1) {
    setFlash('error', 'Invalid booking.');
    header('Location: index.php');
    exit;
}

$statusOptions = getEnumValues($pdo, 'bookings', 'status');

if (!in_array('Cancelled', $statusOptions, true)) {
    setFlash('error', '"Cancelled" is not a valid booking status in this system.');
    header('Location: index.php');
    exit;
}

$check = $pdo->prepare("SELECT status FROM bookings WHERE id = :id");
$check->execute([':id' => $bookingId]);
$current = $check->fetchColumn();

if ($current === false) {
    setFlash('error', 'Booking not found.');
} elseif ($current !== 'Approved') {
    setFlash('error', 'Only approved bookings can be cancelled.');
} else {
    $update = $pdo->prepare("
        UPDATE bookings SET status = 'Cancelled', updated_at = NOW() WHERE id = :id
    ");
    $update->execute([':id' => $bookingId]);
    setFlash('success', 'Booking cancelled successfully.');
}

header('Location: index.php');
exit;