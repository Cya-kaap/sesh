<?php

/**
 * SESH - Delete Room (modules pathway)
 *
 * Purpose:
 *   Confirms and performs permanent deletion of a room record. Reached
 *   from modules/rooms/view.php / admin/rooms/index.php's room listing.
 *
 * Requires tables:
 *   - rooms
 *   - bookings (to block deletion while active/pending bookings exist)
 *
 * Access (per the Corrected Unified Permission Matrix, "Manage Rooms &
 * Fixed Attributes"):
 *   Admin                              - Full Access (only role that can write)
 *   Coordinator, Faculty, Maintenance  - Read Only (cannot delete)
 *   Student                            - No Access
 *
 * Security notes (fixed in this revision):
 *   - Previously had no CSRF token on the confirmation form. A DELETE is
 *     not reversible the way cancelling a booking is, so a forged
 *     auto-submitting form on another page could permanently destroy a
 *     room record. Now POST + CSRF, matching every mutating endpoint
 *     already fixed in modules/bookings/.
 *   - Previously redirected every branch (not-found, blocked-by-active-
 *     bookings, and success) to the literal string '/admin/rooms.php'.
 *     That route does not exist anywhere in this codebase — the actual
 *     rooms listing admin/index.php links to is admin/rooms/index.php.
 *     Every prior delete attempt, successful or not, sent the Admin to a
 *     404. Now uses basePath('admin/rooms/index.php'), consistent with
 *     how includes/auth_check.php's basePath() helper is used elsewhere.
 *   - Previously wrote $_SESSION['flash'] = ['type' => ..., 'message' =>
 *     ...] directly, a shape the shared setFlash()/getFlash() pair
 *     (already used throughout modules/bookings/) neither produces nor
 *     reads — getFlash() expects $_SESSION['flash'][$type] = $message.
 *     Now uses setFlash()/getFlash() so the message actually surfaces.
 *   - The coarse gate is now the matrix-driven requireWritePermission()
 *     instead of a hardcoded requireRole(['Admin']) array. This is a
 *     consistency change, not a behavior fix: the matrix's "Manage
 *     Rooms & Fixed Attributes" row already grants write access to
 *     Admin only, identical to what requireRole(['Admin']) enforced.
 *
 * Known open issue — flagged, not fixed here:
 *   admin/rooms/index.php (the redirect target this file now uses) was
 *   found, on inspection, to contain "Admin: Booking Overrides &
 *   Management" content, not a rooms listing, despite being the file
 *   admin/index.php links to as the Rooms module entry point. That
 *   mismatch predates this change and is outside the scope of a
 *   rooms-deletion fix — it needs its own investigation before this
 *   redirect lands an Admin on an actual room list.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
requireWritePermission(FEATURE_ROOMS_MANAGE);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: ' . basePath('admin/rooms/index.php'));
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ?');
$stmt->execute([$id]);
$room = $stmt->fetch();

if (!$room) {
    setFlash('error', 'Room not found.');
    header('Location: ' . basePath('admin/rooms/index.php'));
    exit;
}

$bookingCheck = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE room_id = ? AND status IN ('Pending', 'Approved')");
$bookingCheck->execute([$id]);
$activeBookings = $bookingCheck->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        setFlash('error', 'Your session expired. Please try again.');
        header('Location: ' . basePath('admin/rooms/index.php'));
        exit;

    } elseif ($activeBookings > 0) {

        setFlash('error', 'Cannot delete room with active or pending bookings.');

    } else {

        $delete = $pdo->prepare('DELETE FROM rooms WHERE id = ?');
        $delete->execute([$id]);

        setFlash('success', 'Room deleted successfully.');
        header('Location: ' . basePath('admin/rooms/index.php'));
        exit;
    }
}

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete Room &mdash; SESH</title>
</head>
<body>
    <h1>Delete Room</h1>

    <?php if ($activeBookings > 0): ?>
        <p class="form-error">
            Warning: This room has <?= $activeBookings ?> active/pending booking(s).
            You must resolve them before deletion.
        </p>
    <?php endif; ?>

    <p>Are you sure you want to delete the following room?</p>
    <ul>
        <li><strong>Name:</strong> <?= htmlspecialchars($room['name']) ?></li>
        <li><strong>Type:</strong> <?= htmlspecialchars($room['type']) ?></li>
        <li><strong>Capacity:</strong> <?= (int)$room['capacity'] ?></li>
        <li><strong>Status:</strong> <?= htmlspecialchars($room['status']) ?></li>
    </ul>

    <form method="POST" action="delete.php">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <button type="submit" class="btn-danger"
                <?= $activeBookings > 0 ? 'disabled' : '' ?>>
            Yes, Delete Room
        </button>
        <a href="<?= htmlspecialchars(basePath('admin/rooms/index.php')) ?>">Cancel</a>
    </form>
</body>
</html>