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
 *   days away.
 *
 * Requires tables:
 *   - bookings, rooms, semesters, programmes
 *
 * Access:
 *   Coordinator only, scoped to their own programme via users.programme_id
 *   (see database/migration_002_add_programme_fk.sql). Never another
 *   programme's booking, regardless of what id is passed in the URL.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['Coordinator']);

$user = currentUser();

$programmeStmt = $pdo->prepare("
    SELECT p.id, p.name
    FROM users u
    JOIN programmes p ON p.id = u.programme_id
    WHERE u.id = :id
    LIMIT 1
");
$programmeStmt->execute([':id' => $user['id']]);
$programme = $programmeStmt->fetch();

if (!$programme) {
    setFlash('error', 'Your account is not assigned to a Programme. Contact an Admin.');
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

        // Format validation runs before anything that assumes a
        // well-formed date/time string (comparisons, conflict checks).
        if ($values['date'] !== '' && !isValidDate($values['date'])) {
            $errors[] = 'Invalid date format.';
        }
        if ($values['start_time'] !== '' && !isValidTime($values['start_time'])) {
            $errors[] = 'Invalid start time format.';
        }
        if ($values['end_time'] !== '' && !isValidTime($values['end_time'])) {
            $errors[] = 'Invalid end time format.';
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

            if ($values['date'] < date('Y-m-d')) {
                $errors[] = 'Date cannot be in the past.';
            }

            // The new date must also sit outside the 2-day lock window —
            // a coordinator shouldn't be able to reschedule INTO it either.
            if ($values['date'] <= $editCutoff) {
                $errors[] = 'The new date must be more than 2 days from today.';
            }

            if ($values['start_time'] >= $values['end_time']) {
                $errors[] = 'End time must be after start time.';
            }

            if ($isExam && $values['num_students'] !== '' && (!ctype_digit($values['num_students']) || (int) $values['num_students'] < 1)) {
                $errors[] = 'Number of students must be a whole number of 1 or more.';
            }
        }

        if ($isExam && empty($errors) && $selectedRoom && (int) $values['num_students'] > (int) $selectedRoom['capacity']) {
            $errors[] = "Number of students ({$values['num_students']}) exceeds the room's capacity ({$selectedRoom['capacity']}).";
        }

        // Conflict check — shared helper (includes/functions.php), excludes
        // this booking's own row so it doesn't collide with itself.
        if (empty($errors) && hasBookingConflict($pdo, (int) $values['room_id'], $values['date'], $values['start_time'], $values['end_time'], $bookingId)) {
            $errors[] = 'This room is already booked during the selected time window. Choose a different room or time.';
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
    <title>Edit Booking #<?= htmlspecialchars((string)$bookingId) ?> - SESH</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f4f6f8; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 650px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; font-size: 1.5rem; color: #1e293b; }
        .alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px; }
        .alert-danger ul { margin: 0; padding-left: 20px; }
        .info-badge { background: #e0f2fe; color: #0369a1; padding: 8px 12px; border-radius: 6px; font-size: 0.9rem; margin-bottom: 20px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.9rem; }
        input[type="text"], input[type="number"], input[type="date"], input[type="time"], select {
            width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 0.95rem;
        }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        .actions { display: flex; gap: 10px; margin-top: 24px; }
        .btn { padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; font-size: 0.95rem; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #475569; }
    </style>
</head>
<body>

<div class="container">
    <h1>Edit Booking #<?= htmlspecialchars((string)$bookingId) ?> (<?= htmlspecialchars($booking['type']) ?>)</h1>

    <div class="info-badge">
        <strong>Programme:</strong> <?= htmlspecialchars($programme['name']) ?><br>
        <em>Note: Saving edits will reset this booking's status to <strong>Pending</strong> for Admin re-approval.</em>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="form-row">
            <div class="form-group">
                <label for="room_id">Room *</label>
                <select name="room_id" id="room_id" required>
                    <option value="">-- Select Room --</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $values['room_id'] == $r['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['type']) ?>, Cap: <?= $r['capacity'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="semester_id">Semester *</label>
                <select name="semester_id" id="semester_id" required>
                    <option value="">-- Select Semester --</option>
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $values['semester_id'] == $s['id'] ? 'selected' : '' ?>>
                            Semester <?= htmlspecialchars((string)$s['number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="date">Date *</label>
                <input type="date" name="date" id="date" value="<?= htmlspecialchars($values['date']) ?>" required>
            </div>
            <div class="form-group">
                <label for="start_time">Start Time *</label>
                <input type="time" name="start_time" id="start_time" value="<?= htmlspecialchars($values['start_time']) ?>" required>
            </div>
            <div class="form-group">
                <label for="end_time">End Time *</label>
                <input type="time" name="end_time" id="end_time" value="<?= htmlspecialchars($values['end_time']) ?>" required>
            </div>
        </div>

        <?php if ($isExam): ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="exam_name">Exam Name *</label>
                    <input type="text" name="exam_name" id="exam_name" value="<?= htmlspecialchars($values['exam_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="subject">Subject *</label>
                    <input type="text" name="subject" id="subject" value="<?= htmlspecialchars($values['subject']) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="invigilator_name">Invigilator Name *</label>
                    <input type="text" name="invigilator_name" id="invigilator_name" value="<?= htmlspecialchars($values['invigilator_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="num_students">Number of Students *</label>
                    <input type="number" name="num_students" id="num_students" min="1" value="<?= htmlspecialchars($values['num_students']) ?>" required>
                </div>
            </div>
        <?php else: ?>
            <div class="form-group">
                <label for="purpose">Purpose *</label>
                <input type="text" name="purpose" id="purpose" value="<?= htmlspecialchars($values['purpose']) ?>" required>
            </div>
        <?php endif; ?>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="bookings.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

</body>
</html>