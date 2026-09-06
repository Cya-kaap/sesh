<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireLogin();
if (!(hasPermission(FEATURE_BOOKINGS_CLASS_CREATE) || hasPermission(FEATURE_BOOKINGS_EXAM_CREATE))) {
    header('Location: ' . basePath('unauthorized.php'));
    exit;
}
header('Location: ' . basePath('faculty/new_booking.php'));
exit;
