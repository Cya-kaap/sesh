<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/conflict_check.php';

requireRole(['Admin', 'Coordinator']);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: list.php?all=1');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get booking
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        b.*,
        r.name AS room_name,
        u.full_name,
        p.name AS programme_name,
        s.number AS semester_number
    FROM bookings b
    JOIN rooms r
        ON b.room_id = r.id
    JOIN users u
        ON b.user_id = u.id
    JOIN programmes p
        ON b.programme_id = p.id
    JOIN semesters s
        ON b.semester_id = s.id
    WHERE b.id = :id
");

$stmt->execute([
    ':id' => $id
]);

$booking = $stmt->fetch();

if (!$booking) {
    die('Booking not found.');
}

$message = '';
$error = '';


/*
|--------------------------------------------------------------------------
| Approve / Reject
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($booking['status'] !== 'pending') {

        $error = 'This booking has already been processed.';

    } elseif ($action === 'approve') {

        /*
        | Re-check conflict immediately before approval.
        */

        if (
            hasBookingConflict(
                $pdo,
                (int)$booking['room_id'],
                $booking['date'],
                $booking['start_time'],
                $booking['end_time'],
                $booking['id']
            )
        ) {

            $error =
                'Cannot approve this booking because another booking conflicts with this time slot.';

        } else {

            $stmt = $pdo->prepare("
                UPDATE bookings
                SET status = 'approved'
                WHERE id = :id
                  AND status = 'pending'
            ");

            $stmt->execute([
                ':id' => $id
            ]);

            $message = 'Booking approved successfully.';

            $booking['status'] = 'approved';
        }

    } elseif ($action === 'reject') {

        $stmt = $pdo->prepare("
            UPDATE bookings
            SET status = 'rejected'
            WHERE id = :id
              AND status = 'pending'
        ");

        $stmt->execute([
            ':id' => $id
        ]);

        $message = 'Booking rejected successfully.';

        $booking['status'] = 'rejected';

    } else {

        $error = 'Invalid action.';
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Review Booking | SESH</title>

    <link rel="stylesheet" href="../../assets/css/style.css">

</head>

<body>

<div class="container">

    <h1>Review Booking #<?= $id ?></h1>

    <p>
        <a href="list.php?all=1">
            ← Back to All Bookings
        </a>
    </p>


    <?php if ($message): ?>

        <div class="success-message">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="form-error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <div class="card">

        <p>
            <strong>Room:</strong>
            <?= htmlspecialchars($booking['room_name']) ?>
        </p>

        <p>
            <strong>Requested By:</strong>
            <?= htmlspecialchars($booking['full_name']) ?>
        </p>

        <p>
            <strong>Programme:</strong>
            <?= htmlspecialchars($booking['programme_name']) ?>
        </p>

        <p>
            <strong>Semester:</strong>
            <?= (int)$booking['semester_number'] ?>
        </p>

        <p>
            <strong>Date:</strong>
            <?= date('M j, Y', strtotime($booking['date'])) ?>
        </p>

        <p>
            <strong>Time:</strong>
            <?= date('g:i A', strtotime($booking['start_time'])) ?>
            -
            <?= date('g:i A', strtotime($booking['end_time'])) ?>
        </p>

        <p>
            <strong>Type:</strong>
            <?= htmlspecialchars($booking['type']) ?>
        </p>

        <p>
            <strong>Purpose:</strong><br>
            <?= nl2br(htmlspecialchars($booking['purpose'])) ?>
        </p>

        <p>
            <strong>Status:</strong>
            <?= htmlspecialchars(ucfirst($booking['status'])) ?>
        </p>

    </div>


    <?php if ($booking['status'] === 'pending'): ?>

        <form method="POST" action="approve.php">

            <input
                type="hidden"
                name="id"
                value="<?= $id ?>"
            >

            <button
                type="submit"
                name="action"
                value="approve"
            >
                Approve Booking
            </button>

            <button
                type="submit"
                name="action"
                value="reject"
            >
                Reject Booking
            </button>

        </form>

    <?php else: ?>

        <p>
            This booking has already been processed.
        </p>

    <?php endif; ?>

</div>

</body>

</html>