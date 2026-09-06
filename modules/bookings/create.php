<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requirePermission(FEATURE_BOOKINGS_CLASS_CREATE);

$user = currentUser();

$errors = [];

$form = [
    'room_id'      => '',
    'programme_id' => '',
    'semester_id'  => '',
    'date'         => '',
    'start_time'   => '',
    'end_time'     => '',
    'type'         => '',
    'purpose'      => '',
];

$rooms = $pdo->query("
    SELECT id, name, capacity
    FROM rooms
    WHERE status = 'Available'
    ORDER BY name
")->fetchAll();

$programmes = $pdo->query("
    SELECT id, name
    FROM programmes
    ORDER BY name
")->fetchAll();

$semesters = $pdo->query("
    SELECT id, number
    FROM semesters
    ORDER BY number
")->fetchAll();

$typeOptions = getEnumValues($pdo, 'bookings', 'type');

$csrfToken = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your session expired. Please try again.');
        header('Location: create.php');
        exit;
    }

    foreach ($form as $key => $value) {
        $form[$key] = trim($_POST[$key] ?? '');
    }

    $room_id      = (int) $form['room_id'];
    $programme_id = (int) $form['programme_id'];
    $semester_id  = (int) $form['semester_id'];
    $date         = $form['date'];
    $start_time   = $form['start_time'];
    $end_time     = $form['end_time'];
    $type         = $form['type'];
    $purpose      = $form['purpose'];

    if ($room_id <= 0) {
        $errors[] = 'Please select a room.';
    }

    if ($programme_id <= 0) {
        $errors[] = 'Please select a programme.';
    }

    if ($semester_id <= 0) {
        $errors[] = 'Please select a semester.';
    }

    if ($date === '') {
        $errors[] = 'Please select a date.';
    }

    if ($start_time === '') {
        $errors[] = 'Please select a start time.';
    }

    if ($end_time === '') {
        $errors[] = 'Please select an end time.';
    }

    if ($start_time !== '' && $end_time !== '' && $start_time >= $end_time) {
        $errors[] = 'End time must be later than start time.';
    }

    if ($type === '') {
        $errors[] = 'Please select the session type.';
    }

    if ($purpose === '') {
        $errors[] = 'Please enter the purpose.';
    }

    if ($date !== '') {

        $dateObject = DateTime::createFromFormat('Y-m-d', $date);

        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            $errors[] = 'Invalid date.';
        }
    }

    if (empty($errors)) {
        if (hasBookingConflict($pdo, $room_id, $date, $start_time, $end_time)) {
            $errors[] = 'This room is already booked or has a pending booking during the selected time.';
        }
    }

    if (empty($errors)) {

        $insert = $pdo->prepare("
            INSERT INTO bookings
            (
                room_id,
                user_id,
                programme_id,
                semester_id,
                date,
                start_time,
                end_time,
                type,
                purpose,
                status
            )
            VALUES
            (
                :room_id,
                :user_id,
                :programme_id,
                :semester_id,
                :date,
                :start_time,
                :end_time,
                :type,
                :purpose,
                'Pending'
            )
        ");

        $insert->execute([
            ':room_id'      => $room_id,
            ':user_id'      => $user['id'],
            ':programme_id' => $programme_id,
            ':semester_id'  => $semester_id,
            ':date'         => $date,
            ':start_time'   => $start_time,
            ':end_time'     => $end_time,
            ':type'         => $type,
            ':purpose'      => $purpose,
        ]);

        setFlash('success', 'Booking request submitted successfully.');
        header('Location: list.php?success=created');
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>New Booking | SESH</title>

    <link rel="stylesheet" href="../../assets/css/style.css">

</head>

<body>

<div class="container">

    <h1>New Booking Request</h1>

    <p>
        <a href="list.php">← Back to Bookings</a>
    </p>

    <?php foreach ($errors as $error): ?>

        <div class="form-error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endforeach; ?>


    <form method="POST" action="create.php">

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <label for="room_id">Room</label>

        <select id="room_id" name="room_id" required>

            <option value="">-- Select Room --</option>

            <?php foreach ($rooms as $room): ?>

                <option
                    value="<?= (int)$room['id'] ?>"
                    <?= (string)$form['room_id'] === (string)$room['id'] ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($room['name']) ?>
                    — Capacity <?= (int)$room['capacity'] ?>
                </option>

            <?php endforeach; ?>

        </select>


        <label for="programme_id">Programme</label>

        <select id="programme_id" name="programme_id" required>

            <option value="">-- Select Programme --</option>

            <?php foreach ($programmes as $programme): ?>

                <option
                    value="<?= (int)$programme['id'] ?>"
                    <?= (string)$form['programme_id'] === (string)$programme['id'] ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($programme['name']) ?>
                </option>

            <?php endforeach; ?>

        </select>


        <label for="semester_id">Semester</label>

        <select id="semester_id" name="semester_id" required>

            <option value="">-- Select Semester --</option>

            <?php foreach ($semesters as $semester): ?>

                <option
                    value="<?= (int)$semester['id'] ?>"
                    <?= (string)$form['semester_id'] === (string)$semester['id'] ? 'selected' : '' ?>
                >
                    Semester <?= (int)$semester['number'] ?>
                </option>

            <?php endforeach; ?>

        </select>


        <label for="date">Date</label>

        <input
            type="date"
            id="date"
            name="date"
            required
            value="<?= htmlspecialchars($form['date']) ?>"
        >


        <label for="start_time">Start Time</label>

        <input
            type="time"
            id="start_time"
            name="start_time"
            required
            value="<?= htmlspecialchars($form['start_time']) ?>"
        >


        <label for="end_time">End Time</label>

        <input
            type="time"
            id="end_time"
            name="end_time"
            required
            value="<?= htmlspecialchars($form['end_time']) ?>"
        >


        <label for="type">Session Type</label>

        <select id="type" name="type" required>

            <option value="">-- Select Type --</option>

            <?php foreach ($typeOptions as $typeOption): ?>

                <option
                    value="<?= htmlspecialchars($typeOption) ?>"
                    <?= $form['type'] === $typeOption ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($typeOption) ?>
                </option>

            <?php endforeach; ?>

        </select>


        <label for="purpose">Purpose</label>

        <textarea
            id="purpose"
            name="purpose"
            rows="5"
            required
        ><?= htmlspecialchars($form['purpose']) ?></textarea>


        <button type="submit">
            Submit Booking Request
        </button>

    </form>

</div>

</body>

</html>