<?php

/**
 * SESH - Faculty: New Booking
 *
 * Purpose:
 *   Booking form for a Faculty member's own lectures, extra classes,
 *   or subject exams, with resource-based room filtering (client-side)
 *   and server-side conflict detection.
 *
 * Requires tables:
 *   - bookings, rooms, programmes, semesters
 *
 * Access:
 *   Faculty only.
 *
 * Design notes:
 *   - Bookings created here are inserted as status = 'Pending' — they
 *     need Coordinator/Admin approval before becoming Approved.
 *   - Unlike Coordinator's book_exam.php, this form does NOT collect
 *     exam_name / invigilator_name / num_students even when type =
 *     'Exam'. Those read as exam-administration fields that belong to
 *     whoever is running the exam logistics, not a self-service
 *     request. `subject` is kept since it's meaningful either way.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['Faculty']);

$user = currentUser();

$rooms = $pdo->query("
    SELECT id, name, type, capacity, has_projector, has_whiteboard, has_ac
    FROM rooms
    WHERE status = 'Available'
    ORDER BY name ASC
")->fetchAll();

$programmes = $pdo->query("SELECT id, name FROM programmes ORDER BY name ASC")->fetchAll();
$semesters  = $pdo->query("SELECT id, programme_id, number FROM semesters ORDER BY programme_id ASC, number ASC")->fetchAll();

$semestersByProgramme = [];
foreach ($semesters as $s) {
    $semestersByProgramme[(int) $s['programme_id']][] = ['id' => (int) $s['id'], 'number' => (int) $s['number']];
}

$errors = [];

$values = [
    'room_id'      => '',
    'programme_id' => '',
    'semester_id'  => '',
    'type'         => 'Class',
    'purpose'      => '',
    'subject'      => '',
    'date'         => '',
    'start_time'   => '',
    'end_time'     => '',
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
            'room_id'      => 'Room',
            'programme_id' => 'Programme',
            'semester_id'  => 'Semester',
            'type'         => 'Booking type',
            'purpose'      => 'Purpose',
            'date'         => 'Date',
            'start_time'   => 'Start time',
            'end_time'     => 'End time',
        ];

        foreach ($requiredLabels as $key => $label) {
            if ($values[$key] === '') {
                $errors[] = "$label is required.";
            }
        }

        if (!in_array($values['type'], ['Class', 'Exam'], true)) {
            $errors[] = 'Please select a valid booking type.';
        }

        // --- CHANGE 1: Format validation ---
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
                $errors[] = 'Please select a valid, available room.';
            }

            $validProgrammeIds = array_column($programmes, 'id');
            if (!in_array((int) $values['programme_id'], $validProgrammeIds, true)) {
                $errors[] = 'Please select a valid programme.';
            }

            $semesterBelongs = false;
            foreach ($semesters as $s) {
                if ((int) $s['id'] === (int) $values['semester_id']
                    && (int) $s['programme_id'] === (int) $values['programme_id']) {
                    $semesterBelongs = true;
                    break;
                }
            }
            if (!$semesterBelongs) {
                $errors[] = 'Please select a semester that belongs to the selected programme.';
            }

            if ($values['date'] !== '' && $values['date'] < date('Y-m-d')) {
                $errors[] = 'Booking date cannot be in the past.';
            }

            if ($values['start_time'] !== '' && $values['end_time'] !== '' && $values['start_time'] >= $values['end_time']) {
                $errors[] = 'End time must be after start time.';
            }
        }

        // --- CHANGE 2: Shared Conflict Check Helper ---
        if (empty($errors) && hasBookingConflict($pdo, (int) $values['room_id'], $values['date'], $values['start_time'], $values['end_time'])) {
            $errors[] = 'This room is already booked (or has a pending request) during the selected time window. Choose a different room or time.';
        }

        // Insert
        if (empty($errors)) {

            $insert = $pdo->prepare("
                INSERT INTO bookings (
                    room_id, user_id, programme_id, semester_id,
                    date, start_time, end_time, type, purpose, subject, status
                ) VALUES (
                    :room_id, :user_id, :programme_id, :semester_id,
                    :date, :start_time, :end_time, :type, :purpose, :subject, 'Pending'
                )
            ");

            $insert->execute([
                ':room_id'      => $values['room_id'],
                ':user_id'      => $user['id'],
                ':programme_id' => $values['programme_id'],
                ':semester_id'  => $values['semester_id'],
                ':date'         => $values['date'],
                ':start_time'   => $values['start_time'],
                ':end_time'     => $values['end_time'],
                ':type'         => $values['type'],
                ':purpose'      => $values['purpose'],
                ':subject'      => $values['subject'] !== '' ? $values['subject'] : null,
            ]);

            setFlash('success', 'Booking request submitted. It is now pending approval.');
            header('Location: my_bookings.php');
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
    <title>Book a Room | SESH Faculty</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .fac-page { min-height: 100vh; background: #f4f3ef; color: #111; }
        .fac-nav { min-height: 80px; padding: 0 6vw; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #d5d5d0; background: #f4f3ef; }
        .fac-logo { font-size: 28px; font-weight: 900; letter-spacing: -0.08em; color: #111; text-decoration: none; }
        .fac-nav-links a { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: #555; text-decoration: none; }

        .fac-content { width: 88%; max-width: 850px; margin: 0 auto; padding: 60px 0; }
        .page-label { margin-bottom: 12px; font-size: 10px; font-weight: 700; letter-spacing: 0.2em; color: #777; }
        .fac-content h1 { margin: 0 0 40px 0; font-size: clamp(32px, 5vw, 50px); letter-spacing: -0.05em; }

        .form-errors { margin-bottom: 25px; padding: 16px 18px; background: #a33; color: #fff; font-size: 13px; }
        .form-errors ul { margin: 0; padding-left: 18px; }

        .filter-box { padding: 20px; background: #fff; border: 1px solid #ddd; margin-bottom: 25px; }
        .filter-box label { font-size: 10px; font-weight: 700; letter-spacing: 0.1em; margin-bottom: 12px; display: block; }
        .filter-chips { display: flex; gap: 20px; flex-wrap: wrap; }
        .filter-chips label { display: flex; align-items: center; gap: 6px; font-weight: 400; letter-spacing: 0; font-size: 13px; margin: 0; }
        .filter-chips input { width: auto; }

        .booking-form { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }
        .booking-form .full { grid-column: 1 / -1; }
        .booking-form label { display: block; margin-bottom: 8px; font-size: 10px; font-weight: 700; letter-spacing: 0.1em; }
        .booking-form input, .booking-form select, .booking-form textarea {
            width: 100%; padding: 13px; margin-bottom: 22px; border: 1px solid #ccc; background: #fff; font-size: 14px; font-family: inherit;
        }
        .booking-form textarea { resize: vertical; min-height: 80px; }

        .type-toggle { display: flex; gap: 15px; margin-bottom: 22px; }
        .type-toggle label { display: flex; align-items: center; gap: 6px; font-weight: 400; letter-spacing: 0; font-size: 13px; margin: 0; }
        .type-toggle input { width: auto; }

        .room-hint { margin-top: -14px; margin-bottom: 22px; font-size: 11px; color: #777; grid-column: 1 / -1; }

        .form-actions { grid-column: 1 / -1; display: flex; gap: 12px; margin-top: 10px; }
        .btn { display: inline-block; padding: 14px 22px; background: #111; color: #fff; text-decoration: none; font-size: 10px; font-weight: 800; letter-spacing: 0.14em; border: none; cursor: pointer; }
        .btn:hover { background: #333; }
        .btn-secondary { background: #ddd; color: #111; }
        .btn-secondary:hover { background: #ccc; }

        @media (max-width: 600px) {
            .booking-form { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="fac-page">

    <header class="fac-nav">
        <a href="index.php" class="fac-logo">SESH</a>
        <nav class="fac-nav-links">
            <a href="index.php">← BACK TO DASHBOARD</a>
        </nav>
    </header>

    <main class="fac-content">

        <p class="page-label">ROOM BOOKING</p>
        <h1>Book a Room</h1>

        <?php if (!empty($errors)): ?>
            <div class="form-errors">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="filter-box">
            <label>Filter rooms by resource</label>
            <div class="filter-chips">
                <label><input type="checkbox" id="filter_projector"> Projector</label>
                <label><input type="checkbox" id="filter_whiteboard"> Whiteboard</label>
                <label><input type="checkbox" id="filter_ac"> AC</label>
            </div>
        </div>

        <form method="POST" action="new_booking.php" class="booking-form">

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="full">
                <label>Booking Type</label>
                <div class="type-toggle">
                    <label><input type="radio" name="type" value="Class" <?= $values['type'] === 'Class' ? 'checked' : '' ?>> Class / Lecture</label>
                    <label><input type="radio" name="type" value="Exam" <?= $values['type'] === 'Exam' ? 'checked' : '' ?>> Subject Exam</label>
                </div>
            </div>

            <div>
                <label for="programme_id">Programme</label>
                <select id="programme_id" name="programme_id" required>
                    <option value="">Select programme</option>
                    <?php foreach ($programmes as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (string) $p['id'] === $values['programme_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="semester_id">Semester</label>
                <select id="semester_id" name="semester_id" required>
                    <option value="">Select programme first</option>
                </select>
            </div>

            <div class="full">
                <label for="subject">Subject <span style="font-weight:400;">(optional)</span></label>
                <input type="text" id="subject" name="subject" maxlength="100"
                       value="<?= htmlspecialchars($values['subject']) ?>">
            </div>

            <div class="full">
                <label for="purpose">Purpose</label>
                <input type="text" id="purpose" name="purpose" maxlength="255" required
                       value="<?= htmlspecialchars($values['purpose']) ?>" placeholder="e.g. Regular lecture — Data Structures">
            </div>

            <div class="full">
                <label for="room_id">Room</label>
                <select id="room_id" name="room_id" required>
                    <option value="">Select room</option>
                </select>
            </div>
            <p class="room-hint">Use the filters above to narrow this list by resource. Only rooms currently "Available" are listed.</p>

            <div>
                <label for="date">Date</label>
                <input type="date" id="date" name="date" required min="<?= date('Y-m-d') ?>"
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
                <button type="submit" class="btn">SUBMIT REQUEST</button>
                <a href="index.php" class="btn btn-secondary">CANCEL</a>
            </div>

        </form>

    </main>

</div>

<script>
    const allRooms = <?= json_encode($rooms) ?>;
    const preselectedRoomId = <?= json_encode($values['room_id'] !== '' ? (int) $values['room_id'] : null) ?>;

    const semestersByProgramme = <?= json_encode($semestersByProgramme) ?>;
    const preselectedSemesterId = <?= json_encode($values['semester_id'] !== '' ? (int) $values['semester_id'] : null) ?>;

    const programmeSelect = document.getElementById('programme_id');
    const semesterSelect  = document.getElementById('semester_id');
    const roomSelect      = document.getElementById('room_id');

    const filterProjector  = document.getElementById('filter_projector');
    const filterWhiteboard = document.getElementById('filter_whiteboard');
    const filterAc         = document.getElementById('filter_ac');

    function populateSemesters() {
        const programmeId = programmeSelect.value;
        semesterSelect.innerHTML = '';

        if (!programmeId || !semestersByProgramme[programmeId]) {
            semesterSelect.innerHTML = '<option value="">Select programme first</option>';
            return;
        }

        semesterSelect.innerHTML = '<option value="">Select semester</option>';

        semestersByProgramme[programmeId].forEach(function (s) {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = 'Semester ' + s.number;
            if (preselectedSemesterId !== null && s.id === preselectedSemesterId) {
                opt.selected = true;
            }
            semesterSelect.appendChild(opt);
        });
    }

    function populateRooms() {
        const wantProjector  = filterProjector.checked;
        const wantWhiteboard = filterWhiteboard.checked;
        const wantAc         = filterAc.checked;

        const filtered = allRooms.filter(function (r) {
            if (wantProjector && !r.has_projector) return false;
            if (wantWhiteboard && !r.has_whiteboard) return false;
            if (wantAc && !r.has_ac) return false;
            return true;
        });

        roomSelect.innerHTML = '<option value="">Select room</option>';

        filtered.forEach(function (r) {
            const opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = r.name + ' — ' + r.type + ' (cap. ' + r.capacity + ')';
            if (preselectedRoomId !== null && r.id === preselectedRoomId) {
                opt.selected = true;
            }
            roomSelect.appendChild(opt);
        });

        if (filtered.length === 0) {
            roomSelect.innerHTML = '<option value="">No rooms match these filters</option>';
        }
    }

    programmeSelect.addEventListener('change', populateSemesters);
    [filterProjector, filterWhiteboard, filterAc].forEach(function (el) {
        el.addEventListener('change', populateRooms);
    });

    if (programmeSelect.value) {
        populateSemesters();
    }
    populateRooms();
</script>

</body>
</html>