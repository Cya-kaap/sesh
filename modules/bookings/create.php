<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
if (!(hasPermission(FEATURE_BOOKINGS_CLASS_CREATE) || hasPermission(FEATURE_BOOKINGS_EXAM_CREATE))) {
    header('Location: ' . basePath('unauthorized.php'));
    exit;
}

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
    'subject'      => '',
    'exam_name'    => '',
    'invigilator_name' => '',
    'num_students' => '',
];

$rooms = $pdo->query("
    SELECT id, room_code, name, type, capacity, exam_capacity, primary_department, amenities, accessibility, has_projector, has_whiteboard, has_ac
    FROM rooms
    WHERE status = 'Active'
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
    $selectedRoom = null;

    foreach ($rooms as $room) {
        if ((int) $room['id'] === $room_id) {
            $selectedRoom = $room;
            break;
        }
    }

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

    if (!in_array($type, ['Class', 'Exam'], true)) {
        $errors[] = 'Please select a valid session type.';
    }

    if ($type === 'Exam' && !hasPermission(FEATURE_BOOKINGS_EXAM_CREATE)) {
        $errors[] = 'You do not have permission to create examination bookings.';
    }
    if ($type === 'Class' && !hasPermission(FEATURE_BOOKINGS_CLASS_CREATE)) {
        $errors[] = 'You do not have permission to create class bookings.';
    }

    if ($purpose === '') {
        $errors[] = 'Please enter the purpose.';
    }

    if ($type === 'Exam') {
        foreach (['exam_name' => 'Exam name', 'subject' => 'Subject', 'invigilator_name' => 'Invigilator name', 'num_students' => 'Number of students'] as $key => $label) {
            if ($form[$key] === '') {
                $errors[] = "$label is required for examination bookings.";
            }
        }
        if ($form['num_students'] !== '' && (!ctype_digit($form['num_students']) || (int) $form['num_students'] < 1)) {
            $errors[] = 'Number of students must be a whole number of 1 or more.';
        }
        if ($selectedRoom && (int) $form['num_students'] > (int) ($selectedRoom['exam_capacity'] ?: $selectedRoom['capacity'])) {
            $examCapacity = (int) ($selectedRoom['exam_capacity'] ?: $selectedRoom['capacity']);
            $errors[] = "Number of students ({$form['num_students']}) exceeds the room's examination capacity ({$examCapacity}).";
        }
    }

    if ($date !== '') {

        $dateObject = DateTime::createFromFormat('Y-m-d', $date);

        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            $errors[] = 'Invalid date.';
        }
    }

    $conflict = null;
    if (empty($errors)) {
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $pdo->beginTransaction();
        $conflict = findBookingConflict($pdo, $room_id, $date, $start_time, $end_time, null, true);
    }
    if ($conflict) {
        $pdo->rollBack();
        $errors[] = sprintf(
            'This room is already approved for %s on %s from %s to %s (%s, %s).',
            $conflict['room_name'], $conflict['date'], $conflict['start_time'],
            $conflict['end_time'], $conflict['type'], $conflict['programme_name']
        );
    }

    if (empty($errors)) {

        $status = in_array($user['role'], [ROLE_ADMIN, ROLE_COORDINATOR], true) ? 'Approved' : 'Pending';
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
                subject,
                exam_name,
                invigilator_name,
                num_students,
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
                :subject,
                :exam_name,
                :invigilator_name,
                :num_students,
                :status
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
            ':subject'      => $form['subject'] !== '' ? $form['subject'] : null,
            ':exam_name'    => $type === 'Exam' ? $form['exam_name'] : null,
            ':invigilator_name' => $type === 'Exam' ? $form['invigilator_name'] : null,
            ':num_students' => $type === 'Exam' ? (int) $form['num_students'] : null,
            ':status'       => $status,
        ]);

        if ($type === 'Exam') {
            $examInsert = $pdo->prepare("INSERT INTO exam_details (booking_id, exam_name, subject, invigilator_name, num_students) VALUES (:booking_id, :exam_name, :subject, :invigilator_name, :num_students)");
            $examInsert->execute([
                ':booking_id' => (int) $pdo->lastInsertId(),
                ':exam_name' => $form['exam_name'],
                ':subject' => $form['subject'],
                ':invigilator_name' => $form['invigilator_name'],
                ':num_students' => (int) $form['num_students'],
            ]);
        }

        $pdo->commit();

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
    <style>.exam-only { display: none; }</style>

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
                    <?= htmlspecialchars($room['name'] ?: $room['room_code']) ?> [<?= htmlspecialchars($room['room_code']) ?>]
                    — <?= htmlspecialchars($room['type']) ?>, Capacity <?= (int)$room['capacity'] ?>
                    — Exam Capacity <?= (int) ($room['exam_capacity'] ?: $room['capacity']) ?>
                    — <?= htmlspecialchars($room['primary_department']) ?>
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

         <label for="subject">Subject</label>
         <input type="text" id="subject" name="subject" maxlength="100"
             value="<?= htmlspecialchars($form['subject']) ?>">

         <div class="exam-only">
             <label for="exam_name">Exam Name</label>
             <input type="text" id="exam_name" name="exam_name" maxlength="150"
                 value="<?= htmlspecialchars($form['exam_name']) ?>">

             <label for="invigilator_name">Invigilator Name</label>
             <input type="text" id="invigilator_name" name="invigilator_name" maxlength="100"
                 value="<?= htmlspecialchars($form['invigilator_name']) ?>">

             <label for="num_students">Expected Students</label>
             <input type="number" id="num_students" name="num_students" min="1"
                 value="<?= htmlspecialchars($form['num_students']) ?>">
         </div>


        <button type="submit">
            Submit Booking Request
        </button>

    </form>

</div>

<script>
    const typeSelect = document.getElementById('type');
    const examSection = document.querySelector('.exam-only');
    const bookingForm = document.querySelector('form');

    function toggleExamSection() {
        const isExam = typeSelect.value === 'Exam';
        examSection.style.display = isExam ? 'block' : 'none';
        examSection.querySelectorAll('input').forEach(function (input) {
            input.required = isExam;
        });
    }

    typeSelect.addEventListener('change', toggleExamSection);
    bookingForm.addEventListener('submit', function (event) {
        const start = document.getElementById('start_time').value;
        const end = document.getElementById('end_time').value;
        const errors = [];

        if (start && end && start >= end) {
            errors.push('End time must be after start time.');
        }
        if (typeSelect.value === 'Exam') {
            ['exam_name', 'subject', 'invigilator_name', 'num_students'].forEach(function (id) {
                if (!document.getElementById(id).value.trim()) {
                    errors.push('All examination fields are required.');
                }
            });
        }
        if (errors.length) {
            event.preventDefault();
            window.alert([...new Set(errors)].join('\n'));
        }
    });
    toggleExamSection();
</script>

</body>

</html>