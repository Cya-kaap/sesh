<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/conflict_check.php';

requireRole(['Faculty', 'Coordinator', 'Admin']);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: list.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

$canEditAny = in_array($role, ['Admin', 'Coordinator'], true);


/*
|--------------------------------------------------------------------------
| Get booking
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM bookings
    WHERE id = :id
");

$stmt->execute([
    ':id' => $id
]);

$booking = $stmt->fetch();

if (!$booking) {
    die('Booking not found.');
}


/*
|--------------------------------------------------------------------------
| Permission
|--------------------------------------------------------------------------
*/

if (!$canEditAny && (int)$booking['user_id'] !== $user_id) {
    http_response_code(403);
    die('You are not allowed to edit this booking.');
}


/*
|--------------------------------------------------------------------------
| Only pending bookings can be edited
|--------------------------------------------------------------------------
*/

if ($booking['status'] !== 'pending') {
    die('Only pending bookings can be edited.');
}


/*
|--------------------------------------------------------------------------
| Load supporting data
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Get type ENUM values
|--------------------------------------------------------------------------
*/

$typeOptions = [];

$stmt = $pdo->query("
    SELECT COLUMN_TYPE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bookings'
      AND COLUMN_NAME = 'type'
");

$columnType = $stmt->fetchColumn();

if ($columnType && preg_match("/^enum\\((.*)\\)$/", $columnType, $matches)) {
    $typeOptions = str_getcsv($matches[1], ',', "'");
}


$errors = [];


/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $room_id      = (int)($_POST['room_id'] ?? 0);
    $programme_id = (int)($_POST['programme_id'] ?? 0);
    $semester_id  = (int)($_POST['semester_id'] ?? 0);
    $date         = trim($_POST['date'] ?? '');
    $start_time   = trim($_POST['start_time'] ?? '');
    $end_time     = trim($_POST['end_time'] ?? '');
    $type         = trim($_POST['type'] ?? '');
    $purpose      = trim($_POST['purpose'] ?? '');

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


    /*
    |--------------------------------------------------------------------------
    | Conflict check
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        if (
            hasBookingConflict(
                $pdo,
                $room_id,
                $date,
                $start_time,
                $end_time,
                $id
            )
        ) {
            $errors[] =
                'This room is already booked or has a pending booking during the selected time.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Update database
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            UPDATE bookings
            SET
                room_id = :room_id,
                programme_id = :programme_id,
                semester_id = :semester_id,
                date = :date,
                start_time = :start_time,
                end_time = :end_time,
                type = :type,
                purpose = :purpose
            WHERE id = :id
        ");

        $stmt->execute([
            ':room_id'      => $room_id,
            ':programme_id' => $programme_id,
            ':semester_id'  => $semester_id,
            ':date'         => $date,
            ':start_time'   => $start_time,
            ':end_time'     => $end_time,
            ':type'         => $type,
            ':purpose'      => $purpose,
            ':id'           => $id
        ]);

        header('Location: list.php?success=updated');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Preserve entered values after validation error
    |--------------------------------------------------------------------------
    */

    $booking['room_id']      = $room_id;
    $booking['programme_id'] = $programme_id;
    $booking['semester_id']  = $semester_id;
    $booking['date']         = $date;
    $booking['start_time']   = $start_time;
    $booking['end_time']     = $end_time;
    $booking['type']         = $type;
    $booking['purpose']      = $purpose;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Booking | SESH</title>

    <link rel="stylesheet" href="../../assets/css/style.css">

</head>

<body>

<div class="container">

    <h1>Edit Booking</h1>

    <p>
        <a href="list.php">← Back to Bookings</a>
    </p>


    <?php foreach ($errors as $error): ?>

        <div class="form-error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endforeach; ?>


    <form method="POST" action="edit.php">

        <input type="hidden" name="id" value="<?= $id ?>">


        <label for="room_id">Room</label>

        <select id="room_id" name="room_id" required>

            <option value="">-- Select Room --</option>

            <?php foreach ($rooms as $room): ?>

                <option
                    value="<?= (int)$room['id'] ?>"
                    <?= (int)$booking['room_id'] === (int)$room['id'] ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($room['name']) ?>
                    — Capacity <?= (int)$room['capacity'] ?>
                </option>

            <?php endforeach; ?>

        </select>


        <label for="programme_id">Programme</label>

        <select id="programme_id" name="programme_id" required>

            <?php foreach ($programmes as $programme): ?>

                <option
                    value="<?= (int)$programme['id'] ?>"
                    <?= (int)$booking['programme_id'] === (int)$programme['id'] ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($programme['name']) ?>
                </option>

            <?php endforeach; ?>

        </select>


        <label for="semester_id">Semester</label>

        <select id="semester_id" name="semester_id" required>

            <?php foreach ($semesters as $semester): ?>

                <option
                    value="<?= (int)$semester['id'] ?>"
                    <?= (int)$booking['semester_id'] === (int)$semester['id'] ? 'selected' : '' ?>
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
            value="<?= htmlspecialchars($booking['date']) ?>"
            required
        >


        <label for="start_time">Start Time</label>

        <input
            type="time"
            id="start_time"
            name="start_time"
            value="<?= htmlspecialchars($booking['start_time']) ?>"
            required
        >


        <label for="end_time">End Time</label>

        <input
            type="time"
            id="end_time"
            name="end_time"
            value="<?= htmlspecialchars($booking['end_time']) ?>"
            required
        >


        <label for="type">Session Type</label>

        <select id="type" name="type" required>

            <?php foreach ($typeOptions as $typeOption): ?>

                <option
                    value="<?= htmlspecialchars($typeOption) ?>"
                    <?= $booking['type'] === $typeOption ? 'selected' : '' ?>
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
        ><?= htmlspecialchars($booking['purpose']) ?></textarea>


        <button type="submit">
            Update Booking
        </button>

    </form>

</div>

</body>

</html>