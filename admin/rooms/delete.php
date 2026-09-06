<?php

/**
 * SESH - Admin: Delete Room
 *
 * Purpose:
 *   Handles room deletion requests from the room list.
 *   POST-only. No view of its own — always redirects back to index.php.
 *
 * Requires tables:
 *   - rooms
 *   - bookings (checked for FK safety)
 *
 * Access:
 *   Admin only.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireWritePermission(FEATURE_ROOMS_MANAGE);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Your session expired. Please try again.');
    header('Location: index.php');
    exit;
}

$roomId = (int) ($_POST['id'] ?? 0);

if ($roomId < 1) {
    setFlash('error', 'Invalid room.');
    header('Location: index.php');
    exit;
}

// Block deletion if the room has any bookings tied to it — protects
// booking history integrity rather than silently orphaning records.
$bookingCheck = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE room_id = :id");
$bookingCheck->execute([':id' => $roomId]);

if ((int) $bookingCheck->fetchColumn() > 0) {
    setFlash('error', 'This room cannot be deleted because it has existing bookings. Set its status to "Inactive" instead.');
    header('Location: index.php');
    exit;
}

try {

    $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = :id");
    $stmt->execute([':id' => $roomId]);

    if ($stmt->rowCount() > 0) {
        setFlash('success', 'Room deleted successfully.');
    } else {
        setFlash('error', 'Room not found.');
    }

} catch (PDOException $e) {
    setFlash('error', 'Unable to delete room. It may still be referenced elsewhere in the system.');
}

header('Location: index.php');
exit;