<?php

/**
 * SESH - Update Room Operational Status (modules pathway)
 *
 * Purpose:
 *   A narrow, status-only mutation endpoint separate from edit.php's
 *   full attribute form. Lets a role toggle a room between 'Available',
 *   'Under Maintenance', etc. without granting rename/capacity rights.
 *
 * Why this file exists (see RBAC audit, Section D and F):
 *   "Manage Rooms & Fixed Attributes (CRUD)" and "Manage Room Operational
 *   Status (Active / Maintenance)" are two separate rows in the matrix
 *   with different role grants:
 *
 *     Feature                  Admin   Coordinator  Faculty  Student  Maintenance
 *     Rooms & Fixed Attributes Full    Read Only    Read Only  None   Read Only
 *     Room Operational Status  Full    Read Only    Read Only  None   Full
 *
 *   edit.php bundles both concerns into one Admin-only form gated on
 *   FEATURE_ROOMS_MANAGE. That's correct for Admin, but it means
 *   Maintenance's Full Access on the *status* row has never had a page
 *   to exercise it through — the only status changes possible today
 *   go through the Admin-only path. This file closes that gap without
 *   touching edit.php, by gating on FEATURE_ROOMS_STATUS instead and
 *   only ever writing the `status` column — never `name` or `capacity`.
 *
 * Access:
 *   requireWritePermission(FEATURE_ROOMS_STATUS) admits Admin and
 *   Maintenance only (both Full Access on this row); Coordinator and
 *   Faculty (Read Only) and Student (No Access) are denied.
 *
 * Security notes:
 *   - POST-only, CSRF-checked, same convention as every other mutating
 *     modules/ page.
 *   - Status value validated against getEnumValues() — same defensive
 *     pattern used in add.php/edit.php/bookings/delete.php — so an
 *     invalid or stale status string can't be written even by a role
 *     that passes the permission gate.
 *   - Deliberately does NOT accept name or capacity fields at all, even
 *     if present in the POST body — only `status` is read and written,
 *     so this endpoint cannot be used to bypass FEATURE_ROOMS_MANAGE's
 *     stricter gate on the other two attributes.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireWritePermission(FEATURE_ROOMS_STATUS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('error', 'Invalid request method.');
    header('Location: list.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Your session expired. Please try again.');
    header('Location: list.php');
    exit;
}

$id     = (int) ($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');

if ($id <= 0) {
    setFlash('error', 'Invalid room.');
    header('Location: list.php');
    exit;
}

$statusOptions = getEnumValues($pdo, 'rooms', 'status');

if ($status === '' || !in_array($status, $statusOptions, true)) {
    setFlash('error', 'Please select a valid status.');
    header('Location: list.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM rooms WHERE id = :id");
$stmt->execute([':id' => $id]);

if (!$stmt->fetch()) {
    setFlash('error', 'Room not found.');
    header('Location: list.php');
    exit;
}

$update = $pdo->prepare("
    UPDATE rooms
    SET status = :status
    WHERE id = :id
");

$update->execute([
    ':status' => $status,
    ':id'     => $id,
]);

setFlash('success', 'Room status updated successfully.');
header('Location: list.php');
exit;