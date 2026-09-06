<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requirePermission(FEATURE_FEEDBACK_SUBMIT);

$user = currentUser();
$bookingId = (int) ($_GET['booking_id'] ?? 0);

$stmt = $pdo->prepare("\n    SELECT b.date, r.name AS room_name, r.type AS room_type,\n           f.cleanliness_rating, f.equipment_rating, f.issue_text, f.created_at\n    FROM room_feedback f\n    JOIN bookings b ON b.id = f.booking_id\n    JOIN rooms r ON r.id = f.room_id\n    WHERE f.booking_id = :booking_id\n      AND f.user_id = :user_id\n      AND b.status = 'Completed'\n    LIMIT 1\n");
$stmt->execute([':booking_id' => $bookingId, ':user_id' => $user['id']]);
$feedback = $stmt->fetch();

if (!$feedback) {
    setFlash('error', 'Feedback not found.');
    header('Location: my_bookings.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Feedback | SESH</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container">
    <h1>Submitted Feedback</h1>
    <p><a href="my_bookings.php">&larr; Back to My Bookings</a></p>
    <div class="card">
        <p><strong>Room:</strong> <?= htmlspecialchars($feedback['room_name']) ?> (<?= htmlspecialchars($feedback['room_type']) ?>)</p>
        <p><strong>Date:</strong> <?= htmlspecialchars(date('d M Y', strtotime($feedback['date']))) ?></p>
        <p><strong>Cleanliness rating:</strong> <?= (int) $feedback['cleanliness_rating'] ?>/5</p>
        <p><strong>Equipment rating:</strong> <?= (int) $feedback['equipment_rating'] ?>/5</p>
        <p><strong>Issue description:</strong> <?= nl2br(htmlspecialchars($feedback['issue_text'] ?: 'No issue reported.')) ?></p>
        <p><small>Submitted <?= htmlspecialchars(date('d M Y g:i A', strtotime($feedback['created_at']))) ?></small></p>
    </div>
</div>
</body>
</html>