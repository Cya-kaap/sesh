<?php

/**
 * SESH - Coordinator: Edit Booking
 *
 * Purpose:
 *   Lets a Coordinator adjust a booking's room, semester, date, time,
 *   and type-specific details within their own programme scope, to
 *   resolve scheduling conflicts.
 *
 * Approval note:
 *   An edit does NOT take effect immediately. Saving changes updates
 *   the row and resets status to 'Pending', so it re-enters the Admin
 *   approval queue at admin/bookings/index.php exactly like a new
 *   booking. If the booking was previously 'Approved', that approval
 *   is gone until an Admin reviews and re-approves the edited version.
 *
 * Lock window:
 *   Editing is only allowed while the booking's date is MORE than 2
 *   days away. Inside that window there's no longer enough lead time
 *   for an Admin to review and approve before the exam/class happens,
 *   so editing is blocked here (and the EDIT link is hidden on
 *   bookings.php once a booking crosses into the window).
 *
 * Requires tables:
 *   - bookings, rooms, semesters
 *
 * Access:
 *   Coordinator only, and only for a booking inside their own
 *   programme scope (never another programme's booking, regardless
 *   of what id is passed in the URL).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['Coordinator']);

$user = currentUser();

$department = getCurrentDepartment($pdo, (int) $user['id']);

$programmeStmt = $pdo->prepare("SELECT id, name FROM programmes WHERE name = :dept LIMIT 1");
$programmeStmt->execute([':dept' => $department ?? '']);
$programme = $programmeStmt->fetch();

if (!$programme) {
    setFlash('error', 'Your account department does not match any configured programme. Contact an Admin.');
    header('Location: index.php');
    exit;
}

$bookingId = (int) ($_GET['id'] ?? 0);

$bookingStmt = $pdo->prepare("
    SELECT * FROM bookings WHERE id = :id AND programme_id = :pid LIMIT 1
");
$bookingStmt->execute([':id' => $bookingId, ':pid' => $programme['id']]);
$booking = $bookingStmt->fetch();

if (!$booking) {
    setFlash('error', 'Booking not found in your programme scope.');
    header('Location: bookings.php');
    exit;
}

if (!in_array($booking['status'], ['Pending', 'Approved'], true)) {
    setFlash('error', 'Only pending or approved bookings can be edited.');
    header('Location: bookings.php');
    exit;
}

// Lock window: block editing once the booking's date is 2 days out or closer.
$editCutoff = date('Y-m-d', strtotime('+2 days'));

if ($booking['date'] <= $editCutoff) {
    setFlash('error', 'This booking is within 2 days of its date and can no longer be edited. Contact an Admin directly for urgent changes.');
    header('Location: bookings.php');
    exit;
}

// The currently-booked room might not carry status 'Available' right now
// (e.g. flagged for maintenance after this booking was made) — it's
// included alongside the available list so the coordinator isn't forced
// off their existing room just to open the edit form.
$roomsStmt = $pdo->prepare("
    SELECT id, name, type, capacity FROM rooms
    WHERE status = 'Available' OR id = :current_room_id
    ORDER BY name ASC
");
$roomsStmt->execute([':current_room_id' => $booking['room_id']]);
$rooms = $roomsStmt->fetchAll();

$semesterStmt = $pdo->prepare("SELECT id, number FROM semesters WHERE programme_id = :pid ORDER BY number ASC");
$semesterStmt->execute([':pid' => $programme['id']]);
$semesters = $semesterStmt->fetchAll();

$isExam = $booking['type'] === 'Exam';
$errors = [];

$values = [
    'room_id'          => (string) $booking['room_id'],
    'semester_id'      => (string) $booking['semester_id'],
    'exam_name'        => (string) ($booking['exam_name'] ?? ''),
    'subject'          => (string) ($booking['subject'] ?? ''),
    'invigilator_name' => (string) ($booking['invigilator_name'] ?? ''),
    'num_students'     => (string) ($booking['num_students'] ?? ''),
    'purpose'          => (string) ($booking['purpose'] ?? ''),
    'date'             => (string) $booking['date'],
    'start_time'       => substr((string) $booking['start_time'], 0, 5),
    'end_time'         => substr((string) $booking['end_time'], 0, 5),
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

        foreach ($values as $key => $_) {
            $values[$key] = trim($_POST[$key] ?? '');
        }

        $requiredLabels = [
            'room_id'     => 'Room',
            'semester_id' => 'Semester',
            'date'        => 'Date',
            'start_time'  => 'Start time',
            'end_time'    => 'End time',
        ];

        if ($isExam) {
            $requiredLabels['exam_name']        = 'Exam name';
            $requiredLabels['subject']          = 'Subject';
            $requiredLabels['invigilator_name'] = 'Invigilator name';
            $requiredLabels['num_students']     = 'Number of students';
        } else {
            $requiredLabels['purpose'] = 'Purpose';
        }

        foreach ($requiredLabels as $key => $label) {
            if ($values[$key] === '') {
                $errors[] = "$label is required.";
            }
        }

        $selectedRoom = null;

        if (empty($errors)) {

            foreach ($rooms as $r) {
                if ((int) $r['id'] === (int) $values['room_id']) {
                    $selectedRoom = $r;
                    break;
                }
            }
            if (!$selectedRoom) {
                $errors[] = 'Please select a valid room.';
            }

            $validSemesterIds = array_column($semesters, 'id');
            if (!in_array((int) $values['semester_id'], $validSemesterIds, true)) {
                $errors[] = 'Please select a valid semester.';
            }

            if ($values['date'] !== '' && $values['date'] < date('Y-m-d')) {
                $errors[] = 'Date cannot be in the past.';
            }

            // The new date must also sit outside the 2-day lock window —
            // a coordinator shouldn't be able to reschedule INTO it either.
            if ($values['date'] !== '' && $values['date'] <= $editCutoff) {
                $errors[] = 'The new date must be more than 2 days from today.';
            }

            if ($values['start_time'] !== '' && $values['end_time'] !== '' && $values['start_time'] >= $values['end_time']) {
                $errors[] = 'End time must be after start time.';
            }

            if ($isExam && $values['num_students'] !== '' && (!ctype_digit($values['num_students']) || (int) $values['num_students'] < 1)) {
                $errors[] = 'Number of students must be a whole number of 1 or more.';
            }
        }

        if ($isExam && empty($errors) && $selectedRoom && (int) $values['num_students'] > (int) $selectedRoom['capacity']) {
            $errors[] = "Number of students ({$values['num_students']}) exceeds the room's capacity ({$selectedRoom['capacity']}).";
        }

        // Conflict check — same as book_exam.php, but excludes this
        // booking's own row so it doesn't collide with itself.
        if (empty($errors)) {

            $conflictStmt = $pdo->prepare("
                SELECT COUNT(*) FROM bookings
                WHERE room_id = :room_id
                  AND date = :date
                  AND status IN ('Pending', 'Approved')
                  AND id != :self_id
                  AND start_time < :end_time
                  AND end_time > :start_time
            ");
            $conflictStmt->execute([
                ':room_id'    => $values['room_id'],
                ':date'       => $values['date'],
                ':self_id'    => $bookingId,
                ':start_time' => $values['start_time'],
                ':end_time'   => $values['end_time'],
            ]);

            if ((int) $conflictStmt->fetchColumn() > 0) {
                $errors[] = 'This room is already booked during the selected time window. Choose a different room or time.';
            }
        }

        // Save — always resets status to 'Pending' so it re-enters the
        // Admin approval queue instead of silently keeping its old
        // Approved state with new, unreviewed details.
        if (empty($errors)) {

            $update = $pdo->prepare("
                UPDATE bookings SET
                    room_id = :room_id,
                    semester_id = :semester_id,
                    date = :date,
                    start_time = :start_time,
                    end_time = :end_time,
                    purpose = :purpose,
                    exam_name = :exam_name,
                    subject = :subject,
                    invigilator_name = :invigilator_name,
                    num_students = :num_students,
                    status = 'Pending',
                    updated_at = NOW()
                WHERE id = :id
            ");

            $update->execute([
                ':room_id'          => $values['room_id'],
                ':semester_id'      => $values['semester_id'],
                ':date'             => $values['date'],
                ':start_time'       => $values['start_time'],
                ':end_time'         => $values['end_time'],
                ':purpose'          => $isExam ? $values['exam_name'] : $values['purpose'],
                ':exam_name'        => $isExam ? $values['exam_name'] : null,
                ':subject'          => $isExam ? $values['subject'] : null,
                ':invigilator_name' => $isExam ? $values['invigilator_name'] : null,
                ':num_students'     => $isExam ? (int) $values['num_students'] : null,
                ':id'               => $bookingId,
            ]);

            setFlash('success', 'Booking updated and re-submitted for Admin approval.');
            header('Location: bookings.php');
            exit;
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
    <title>Edit Booking | SESH Coordinator</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .coord-page { min-height: 100vh; background: #f4f3ef; color: #111; }
        .coord-nav { min-height: 80px; padding: 0 6vw; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #d5d5d0; background: #f4f3ef; }
        .coord-logo { font-size: 28px; font-weight: 900; letter-spacing: -0.08em; color: #111; text-decoration: none; }
        .coord-nav-links a { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: #555; text-decoration: none; }

        .coord-content { width: 88%; max-width: 800px; margin: 0 auto; padding: 60px 0; }
        .page-label { margin-bottom: 12px; font-size: 10px; font-weight: 700; letter-spacing: 0.2em; color: #777; }
        .coord-content h1 { margin: 0 0 16px 0; font-size: clamp(32px, 5vw, 50px); letter-spacing: -0.05em; }

        .approval-note { margin-bottom: 30px; padding: 16px 18px; background: #fff3cd; color: #856404; font-size: 13px; }

        .form-errors { margin-bottom: 25px; padding: 16px 18px; background: #a33; color: #fff; font-size: 13px; }
        .form-errors ul { margin: 0; padding-left: 18px; }

        .exam-form { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }
        .exam-form .full { grid-column: 1 / -1; }

        .exam-form label { display: block; margin-bottom: 8px; font-size: 10px; font-weight: 700; letter-spacing: 0.1em; }
        .exam-form input, .exam-form select {
            width: 100%; padding: 13px; margin-bottom: 22px; border: 1px solid #ccc; background: #fff; font-size: 14px;
        }

        .room-hint { margin-top: -14px; margin-bottom: 22px; font-size: 11px; color: #777; grid-column: 1 / -1; }

        .form-actions { grid-column: 1 / -1; display: flex; gap: 12px; margin-top: 10px; }
        .btn { display: inline-block; padding: 14px 22px; background: #111; color: #fff; text-decoration: none; font-size: 10px; font-weight: 800; letter-spacing: 0.14em; border: none; cursor: pointer; }
        .btn:hover { background: #333; }
        .btn-secondary { background: #ddd; color: #111; }
        .btn-secondary:hover { background: #ccc; }

        @media (max-width: 600px) {
            .exam-form { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="coord-page">

    <header class="coord-nav">
        <a href="index.php" class="coord-logo">SESH</a>
        <nav class="coord-nav-links">
            <a href="bookings.php">← BACK TO BOOKINGS</a>
        </nav>
    </header>

    <main class="coord-content">

        <p class="page-label"><?= htmlspecialchars($programme['name']) ?> PROGRAMME</p>
        <h1>Edit Booking</h1>

        <div class="approval-note">
            Saving changes will resubmit this booking for Admin approval — its status will return to Pending until an Admin reviews the update.
        </div>

        <?php if (!empty($errors)): ?>
            <div class="form-errors">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="edit_booking.php?id=<?= (int) $bookingId ?>" class="exam-form">

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <?php if ($isExam): ?>

                <div class="full">
                    <label for="exam_name">Exam Name</label>
                    <input type="text" id="exam_name" name="exam_name" maxlength="150" required
                           value="<?= htmlspecialchars($values['exam_name']) ?>">
                </div>

                <div class="full">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" maxlength="100" required
                           value="<?= htmlspecialchars($values['subject']) ?>">
                </div>

                <div>
                    <label for="invigilator_name">Invigilator Name</label>
                    <input type="text" id="invigilator_name" name="invigilator_name" maxlength="100" required
                           value="<?= htmlspecialchars($values['invigilator_name']) ?>">
                </div>

                <div>
                    <label for="num_students">Number of Students</label>
                    <input type="number" id="num_students" name="num_students" min="1" required
                           value="<?= htmlspecialchars($values['num_students']) ?>">
                </div>

            <?php else: ?>

                <div class="full">
                    <label for="purpose">Purpose</label>
                    <input type="text" id="purpose" name="purpose" maxlength="150" required
                           value="<?= htmlspecialchars($values['purpose']) ?>">
                </div>

            <?php endif; ?>

            <div>
                <label for="semester_id">Semester</label>
                <select id="semester_id" name="semester_id" required>
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (string) $s['id'] === $values['semester_id'] ? 'selected' : '' ?>>
                            Semester <?= (int) $s['number'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="room_id">Room</label>
                <select id="room_id" name="room_id" required>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (string) $r['id'] === $values['room_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['name']) ?> — <?= htmlspecialchars($r['type']) ?> (cap. <?= (int) $r['capacity'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <p class="room-hint">Your current room is included even if it's no longer marked "Available".</p>

            <div>
                <label for="date">Date</label>
                <input type="date" id="date" name="date" required min="<?= htmlspecialchars($editCutoff) ?>"
                       value="<?= htmlspecialchars($values['date']) ?>">
            </div>

            <div></div>

            <div>
                <label for="start_time">Start Time</label>
                <input type="time" id="start_time" name="start_time" required
                       value="<?= htmlspecialchars($values['start_time']) ?>">
            </div>

            <div>
                <label for="end_time">End Time</label>
                <input type="time" id="end_time" name="end_time" required
                       value="<?= htmlspecialchars($values['end_time']) ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">SAVE &amp; RESUBMIT</button>
                <a href="bookings.php" class="btn btn-secondary">CANCEL</a>
            </div>

        </form>

    </main>

</div>
</body>
</html>