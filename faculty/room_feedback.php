<?php

/**
 * SESH - Faculty: Room Feedback
 *
 * Purpose:
 *   One-time feedback form (cleanliness rating, equipment rating,
 *   optional issue text) for a Completed booking that doesn't already
 *   have feedback attached.
 *
 * Requires tables:
 *   - bookings, rooms, room_feedback
 *
 * Access:
 *   Faculty only, and only for their own Completed bookings.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['Faculty']);

$user = currentUser();

$bookingId = (int) ($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);

if ($bookingId < 1) {
    setFlash('error', 'Invalid booking.');
    header('Location: my_bookings.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT b.id, b.date, b.room_id, r.name AS room_name, r.type AS room_type,
           f.id AS existing_feedback_id
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    LEFT JOIN room_feedback f ON f.booking_id = b.id
    WHERE b.id = :id AND b.user_id = :uid AND b.status = 'Completed'
    LIMIT 1
");
$stmt->execute([':id' => $bookingId, ':uid' => $user['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlash('error', 'This booking is not eligible for feedback (not found, not yours, or not completed yet).');
    header('Location: my_bookings.php');
    exit;
}

if ($booking['existing_feedback_id']) {
    setFlash('error', 'Feedback has already been submitted for this booking.');
    header('Location: my_bookings.php');
    exit;
}

$errors = [];

$values = [
    'cleanliness_rating' => '',
    'equipment_rating'   => '',
    'issue_text'          => '',
];

/*
|--------------------------------------------------------------------------
| Handle Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        $errors[] = 'Your session expired. Please try again.';

    } else {

        $values['cleanliness_rating'] = trim($_POST['cleanliness_rating'] ?? '');
        $values['equipment_rating']   = trim($_POST['equipment_rating'] ?? '');
        $values['issue_text']         = trim($_POST['issue_text'] ?? '');

        foreach (['cleanliness_rating' => 'Cleanliness rating', 'equipment_rating' => 'Equipment condition rating'] as $key => $label) {
            $val = $values[$key];
            if ($val === '' || !ctype_digit($val) || (int) $val < 1 || (int) $val > 5) {
                $errors[] = "$label must be a whole number from 1 to 5.";
            }
        }

        if (mb_strlen($values['issue_text']) > 1000) {
            $errors[] = 'Issue description must be 1000 characters or fewer.';
        }

        if (empty($errors)) {

            try {

                $insert = $pdo->prepare("
                    INSERT INTO room_feedback (
                        booking_id, user_id, room_id,
                        cleanliness_rating, equipment_rating, issue_text
                    ) VALUES (
                        :booking_id, :user_id, :room_id,
                        :cleanliness_rating, :equipment_rating, :issue_text
                    )
                ");

                $insert->execute([
                    ':booking_id'          => $bookingId,
                    ':user_id'             => $user['id'],
                    ':room_id'             => $booking['room_id'],
                    ':cleanliness_rating'  => (int) $values['cleanliness_rating'],
                    ':equipment_rating'    => (int) $values['equipment_rating'],
                    ':issue_text'          => $values['issue_text'] !== '' ? $values['issue_text'] : null,
                ]);

                setFlash('success', 'Thank you — your feedback has been submitted.');
                header('Location: my_bookings.php');
                exit;

            } catch (PDOException $e) {
                // Most likely a race condition hitting the UNIQUE constraint
                // on booking_id (e.g. double form submit in two tabs).
                setFlash('error', 'Feedback for this booking has already been submitted.');
                header('Location: my_bookings.php');
                exit;
            }
        }
    }
}

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Feedback | SESH Faculty</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .fac-page { min-height: 100vh; background: #f4f3ef; color: #111; }
        .fac-nav { min-height: 80px; padding: 0 6vw; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #d5d5d0; background: #f4f3ef; }
        .fac-logo { font-size: 28px; font-weight: 900; letter-spacing: -0.08em; color: #111; text-decoration: none; }
        .fac-nav-links a { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: #555; text-decoration: none; }

        .fac-content { width: 88%; max-width: 650px; margin: 0 auto; padding: 60px 0; }
        .page-label { margin-bottom: 12px; font-size: 10px; font-weight: 700; letter-spacing: 0.2em; color: #777; }
        .fac-content h1 { margin: 0 0 8px 0; font-size: clamp(30px, 5vw, 46px); letter-spacing: -0.05em; }
        .booking-context { margin-bottom: 35px; font-size: 13px; color: #777; }

        .form-errors { margin-bottom: 25px; padding: 16px 18px; background: #a33; color: #fff; font-size: 13px; }
        .form-errors ul { margin: 0; padding-left: 18px; }

        .feedback-form label { display: block; margin-bottom: 10px; font-size: 10px; font-weight: 700; letter-spacing: 0.1em; }
        .feedback-form textarea {
            width: 100%; padding: 13px; margin-bottom: 25px; border: 1px solid #ccc; background: #fff; font-size: 14px; font-family: inherit; resize: vertical; min-height: 100px;
        }

        .rating-group { display: flex; gap: 10px; margin-bottom: 25px; }
        .rating-group label {
            flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px;
            padding: 14px 0; border: 1px solid #ccc; background: #fff; cursor: pointer; font-size: 16px; font-weight: 700; letter-spacing: 0;
        }
        .rating-group input { width: auto; margin: 0; }
        .rating-group input:checked + span { font-weight: 800; }
        .rating-labels { display: flex; justify-content: space-between; font-size: 10px; color: #777; margin-top: -18px; margin-bottom: 25px; }

        .form-actions { display: flex; gap: 12px; margin-top: 10px; }
        .btn { display: inline-block; padding: 14px 22px; background: #111; color: #fff; text-decoration: none; font-size: 10px; font-weight: 800; letter-spacing: 0.14em; border: none; cursor: pointer; }
        .btn:hover { background: #333; }
        .btn-secondary { background: #ddd; color: #111; }
        .btn-secondary:hover { background: #ccc; }
    </style>
</head>
<body>
<div class="fac-page">

    <header class="fac-nav">
        <a href="index.php" class="fac-logo">SESH</a>
        <nav class="fac-nav-links">
            <a href="my_bookings.php">← BACK TO MY BOOKINGS</a>
        </nav>
    </header>

    <main class="fac-content">

        <p class="page-label">ROOM FEEDBACK</p>
        <h1>Rate Your Session</h1>
        <p class="booking-context">
            <?= htmlspecialchars($booking['room_name']) ?> (<?= htmlspecialchars($booking['room_type']) ?>)
            — <?= htmlspecialchars(date('d M Y', strtotime($booking['date']))) ?>
        </p>

        <?php if (!empty($errors)): ?>
            <div class="form-errors">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="room_feedback.php?booking_id=<?= $bookingId ?>" class="feedback-form">

            <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <label>Cleanliness Rating</label>
            <div class="rating-group">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label>
                        <input type="radio" name="cleanliness_rating" value="<?= $i ?>"
                               <?= (string) $i === $values['cleanliness_rating'] ? 'checked' : '' ?> required>
                        <span><?= $i ?></span>
                    </label>
                <?php endfor; ?>
            </div>
            <div class="rating-labels"><span>Poor</span><span>Excellent</span></div>

            <label>Equipment Condition Rating</label>
            <div class="rating-group">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label>
                        <input type="radio" name="equipment_rating" value="<?= $i ?>"
                               <?= (string) $i === $values['equipment_rating'] ? 'checked' : '' ?> required>
                        <span><?= $i ?></span>
                    </label>
                <?php endfor; ?>
            </div>
            <div class="rating-labels"><span>Poor</span><span>Excellent</span></div>

            <label for="issue_text">Issue Description <span style="font-weight:400;">(optional)</span></label>
            <textarea id="issue_text" name="issue_text" maxlength="1000"
                      placeholder="Anything that needs attention — broken equipment, cleanliness issues, etc."><?= htmlspecialchars($values['issue_text']) ?></textarea>

            <div class="form-actions">
                <button type="submit" class="btn">SUBMIT FEEDBACK</button>
                <a href="my_bookings.php" class="btn btn-secondary">CANCEL</a>
            </div>

        </form>

    </main>

</div>
</body>
</html>