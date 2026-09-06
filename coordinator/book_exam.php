<?php

/**
 * SESH - Coordinator: Book Exam
 *
 * Purpose:
 *   Special examination booking form with invigilator and capacity
 *   validation, plus room double-booking conflict detection.
 *
 * Requires tables:
 *   - bookings, rooms, programmes, semesters
 *
 * Access:
 *   Coordinator only.
 *
 * Approval note:
 *   Coordinator examination bookings are inserted as 'Approved' after
 *   server-side conflict and capacity validation. Faculty-created
 *   bookings remain 'Pending' and enter the approval workflow.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requirePermission(FEATURE_BOOKINGS_EXAM_CREATE);

$user = currentUser();

$rooms = $pdo->query("
    SELECT id, room_code, name, building, floor, type, capacity, exam_capacity, primary_department, amenities, accessibility, has_projector, has_whiteboard, has_ac FROM rooms
    WHERE status = 'Active'
    ORDER BY name ASC
")->fetchAll();

$programmes = $pdo->query("SELECT id, name FROM programmes ORDER BY name ASC")->fetchAll();
$semesters  = $pdo->query("SELECT id, programme_id, number FROM semesters ORDER BY programme_id ASC, number ASC")->fetchAll();

// Group semesters by programme for the JS-driven dependent dropdown.
$semestersByProgramme = [];
foreach ($semesters as $s) {
    $semestersByProgramme[(int) $s['programme_id']][] = ['id' => (int) $s['id'], 'number' => (int) $s['number']];
}

$errors = [];

$values = [
    'room_id'          => '',
    'programme_id'     => '',
    'semester_id'      => '',
    'exam_name'        => '',
    'subject'          => '',
    'invigilator_name' => '',
    'num_students'     => '',
    'date'             => '',
    'start_time'       => '',
    'end_time'         => '',
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

        // Required fields
        $requiredLabels = [
            'room_id'          => 'Room',
            'programme_id'     => 'Programme',
            'semester_id'      => 'Semester',
            'exam_name'        => 'Exam name',
            'subject'          => 'Subject',
            'invigilator_name' => 'Invigilator name',
            'num_students'     => 'Number of students',
            'date'             => 'Date',
            'start_time'       => 'Start time',
            'end_time'         => 'End time',
        ];

        foreach ($requiredLabels as $key => $label) {
            if ($values[$key] === '') {
                $errors[] = "$label is required.";
            }
        }

        // Format validation — runs before any logic that assumes a
        // well-formed date/time string.
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

            // Room must be a real, available room
            foreach ($rooms as $r) {
                if ((int) $r['id'] === (int) $values['room_id']) {
                    $selectedRoom = $r;
                    break;
                }
            }
            if (!$selectedRoom) {
                $errors[] = 'Please select a valid, available room.';
            }

            // Programme / semester must be real
            $validProgrammeIds = array_column($programmes, 'id');
            $validSemesterIds  = array_column($semesters, 'id');

            if (!in_array((int) $values['programme_id'], $validProgrammeIds, true)) {
                $errors[] = 'Please select a valid programme.';
            }
            if (!in_array((int) $values['semester_id'], $validSemesterIds, true)) {
                $errors[] = 'Please select a valid semester.';
            } else {
                // Semesters are scoped per-programme — confirm the chosen
                // semester actually belongs to the chosen programme.
                $semesterBelongs = false;
                foreach ($semesters as $s) {
                    if ((int) $s['id'] === (int) $values['semester_id']
                        && (int) $s['programme_id'] === (int) $values['programme_id']) {
                        $semesterBelongs = true;
                        break;
                    }
                }
                if (!$semesterBelongs) {
                    $errors[] = 'The selected semester does not belong to the selected programme.';
                }
            }

            // Date must not be in the past
            if ($values['date'] !== '' && $values['date'] < date('Y-m-d')) {
                $errors[] = 'Exam date cannot be in the past.';
            }

            // Time range must be valid
            if ($values['start_time'] !== '' && $values['end_time'] !== '' && $values['start_time'] >= $values['end_time']) {
                $errors[] = 'End time must be after start time.';
            }

            // Number of students must be a positive whole number
            if ($values['num_students'] !== '' && (!ctype_digit($values['num_students']) || (int) $values['num_students'] < 1)) {
                $errors[] = 'Number of students must be a whole number of 1 or more.';
            }
        }

        // Capacity check
        if (empty($errors) && $selectedRoom && (int) $values['num_students'] > (int) ($selectedRoom['exam_capacity'] ?: $selectedRoom['capacity'])) {
            $examCapacity = (int) ($selectedRoom['exam_capacity'] ?: $selectedRoom['capacity']);
            $errors[] = "Number of students ({$values['num_students']}) exceeds the room's examination capacity ({$examCapacity}).";
        }

        // Conflict check — shared helper (includes/functions.php).
        $conflict = null;
        if (empty($errors)) {
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            $pdo->beginTransaction();
            $conflict = findBookingConflict($pdo, (int) $values['room_id'], $values['date'], $values['start_time'], $values['end_time'], null, true);
        }
        if ($conflict) {
            $pdo->rollBack();
            $errors[] = sprintf(
                'This room is already approved for %s on %s from %s to %s (%s, %s).',
                $conflict['room_name'], $conflict['date'], $conflict['start_time'],
                $conflict['end_time'], $conflict['type'], $conflict['programme_name']
            );
        }

        // Insert
        if (empty($errors)) {

            $insert = $pdo->prepare("
                INSERT INTO bookings (
                    room_id, user_id, programme_id, semester_id,
                    date, start_time, end_time, type, purpose,
                    exam_name, subject, invigilator_name, num_students, status
                ) VALUES (
                    :room_id, :user_id, :programme_id, :semester_id,
                    :date, :start_time, :end_time, 'Exam', :purpose,
                    :exam_name, :subject, :invigilator_name, :num_students, 'Approved'
                )
            ");

            $insert->execute([
                ':room_id'          => $values['room_id'],
                ':user_id'          => $user['id'],
                ':programme_id'     => $values['programme_id'],
                ':semester_id'      => $values['semester_id'],
                ':date'             => $values['date'],
                ':start_time'       => $values['start_time'],
                ':end_time'         => $values['end_time'],
                ':purpose'          => $values['exam_name'],
                ':exam_name'        => $values['exam_name'],
                ':subject'          => $values['subject'],
                ':invigilator_name' => $values['invigilator_name'],
                ':num_students'     => (int) $values['num_students'],
            ]);

            $examInsert = $pdo->prepare("INSERT INTO exam_details (booking_id, exam_name, subject, invigilator_name, num_students) VALUES (:booking_id, :exam_name, :subject, :invigilator_name, :num_students)");
            $examInsert->execute([
                ':booking_id' => (int) $pdo->lastInsertId(),
                ':exam_name' => $values['exam_name'],
                ':subject' => $values['subject'],
                ':invigilator_name' => $values['invigilator_name'],
                ':num_students' => (int) $values['num_students'],
            ]);

            $pdo->commit();

            setFlash('success', 'Exam "' . $values['exam_name'] . '" submitted for approval. An Admin will review the room, date and time before it is confirmed.');
            header('Location: index.php');
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
    <title>Book Exam | SESH Coordinator</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .coord-page { min-height: 100vh; background: #f4f3ef; color: #111; }
        .coord-nav { min-height: 80px; padding: 0 6vw; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #d5d5d0; background: #f4f3ef; }
        .coord-logo { font-size: 28px; font-weight: 900; letter-spacing: -0.08em; color: #111; text-decoration: none; }
        .coord-nav-links a { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: #555; text-decoration: none; }

        .coord-content { width: 88%; max-width: 800px; margin: 0 auto; padding: 60px 0; }
        .page-label { margin-bottom: 12px; font-size: 10px; font-weight: 700; letter-spacing: 0.2em; color: #777; }
        .coord-content h1 { margin: 0 0 40px 0; font-size: clamp(32px, 5vw, 50px); letter-spacing: -0.05em; }

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
            <a href="index.php">← BACK TO DASHBOARD</a>
        </nav>
    </header>

    <main class="coord-content">

        <p class="page-label">EXAMINATION BOOKING</p>
        <h1>Book an Exam</h1>

        <?php if (!empty($errors)): ?>
            <div class="form-errors">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="book_exam.php" class="exam-form">

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="full">
                <label for="exam_name">Exam Name</label>
                <input type="text" id="exam_name" name="exam_name" maxlength="150" required
                       value="<?= htmlspecialchars($values['exam_name']) ?>" placeholder="e.g. CIA-1 Examination">
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

            <div class="full">
                <label>Filter rooms</label>
                <div class="filter-chips">
                    <label><input type="text" id="filter_room_type" placeholder="Room type"></label>
                    <label><input type="text" id="filter_department" placeholder="Department"></label>
                    <label><input type="checkbox" id="filter_projector"> Projector</label>
                    <label><input type="checkbox" id="filter_whiteboard"> Whiteboard</label>
                    <label><input type="checkbox" id="filter_ac"> AC</label>
                </div>
            </div>

            <div class="full">
                <label for="room_id">Room</label>
                <select id="room_id" name="room_id" required>
                    <option value="">Select room</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (string) $r['id'] === $values['room_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['name'] ?: $r['room_code']) ?> [<?= htmlspecialchars($r['room_code']) ?>] — <?= htmlspecialchars($r['type']) ?> (cap. <?= (int) $r['capacity'] ?>, exam cap. <?= (int) ($r['exam_capacity'] ?: $r['capacity']) ?>, <?= htmlspecialchars($r['primary_department']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p><a id="room-details-link" href="../modules/rooms/view.php" target="_blank" rel="noopener" hidden>View room details, photos, amenities and accessibility</a></p>
            </div>
            <p class="room-hint">Only rooms currently marked "Available" are listed. Capacity is checked against Number of Students.</p>

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
                <button type="submit" class="btn">BOOK EXAM</button>
                <a href="index.php" class="btn btn-secondary">CANCEL</a>
            </div>

        </form>

    </main>

</div>

<script>
    // Semesters are scoped per-programme, so this list is populated
    // dynamically based on which programme is selected — no page reload.
    const semestersByProgramme = <?= json_encode($semestersByProgramme) ?>;
    const preselectedSemesterId = <?= json_encode($values['semester_id'] !== '' ? (int) $values['semester_id'] : null) ?>;

    const programmeSelect = document.getElementById('programme_id');
    const semesterSelect  = document.getElementById('semester_id');
    const roomSelect = document.getElementById('room_id');
    const roomDetailsLink = document.getElementById('room-details-link');
    const allRooms = <?= json_encode($rooms) ?>;
    const preselectedRoomId = <?= json_encode($values['room_id'] !== '' ? (int) $values['room_id'] : null) ?>;
    const filterRoomType = document.getElementById('filter_room_type');
    const filterDepartment = document.getElementById('filter_department');
    const filterProjector = document.getElementById('filter_projector');
    const filterWhiteboard = document.getElementById('filter_whiteboard');
    const filterAc = document.getElementById('filter_ac');

    function populateRooms() {
        const roomType = filterRoomType.value.toLowerCase().trim();
        const department = filterDepartment.value.toLowerCase().trim();
        const filtered = allRooms.filter(function (room) {
            if (roomType && room.type.toLowerCase().indexOf(roomType) === -1) return false;
            if (department && room.primary_department.toLowerCase().indexOf(department) === -1) return false;
            if (filterProjector.checked && !room.has_projector) return false;
            if (filterWhiteboard.checked && !room.has_whiteboard) return false;
            if (filterAc.checked && !room.has_ac) return false;
            return true;
        });
        roomSelect.innerHTML = '<option value="">Select room</option>';
        filtered.forEach(function (room) {
            const option = document.createElement('option');
            option.value = room.id;
            option.textContent = (room.name || room.room_code) + ' [' + room.room_code + '] - ' + room.type + ' (cap. ' + room.capacity + ', exam cap. ' + (room.exam_capacity || room.capacity) + ', ' + room.primary_department + ')';
            if (preselectedRoomId !== null && Number(room.id) === preselectedRoomId) option.selected = true;
            roomSelect.appendChild(option);
        });
        updateRoomDetailsLink();
    }

    function updateRoomDetailsLink() {
        if (roomSelect.value) {
            roomDetailsLink.href = '../modules/rooms/view.php?id=' + encodeURIComponent(roomSelect.value);
            roomDetailsLink.hidden = false;
        } else {
            roomDetailsLink.hidden = true;
        }
    }

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

    programmeSelect.addEventListener('change', populateSemesters);
    [filterRoomType, filterDepartment].forEach(function (field) { field.addEventListener('input', populateRooms); });
    [filterProjector, filterWhiteboard, filterAc].forEach(function (field) { field.addEventListener('change', populateRooms); });
    roomSelect.addEventListener('change', updateRoomDetailsLink);

    // Re-populate on load so a validation-error reload keeps the
    // previously selected programme's semester list (and selection).
    if (programmeSelect.value) {
        populateSemesters();
    }
    populateRooms();
</script>

</body>
</html>