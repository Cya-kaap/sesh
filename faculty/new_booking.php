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
 *   - Exam bookings collect the same exam metadata as the Coordinator flow.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/room_helpers.php';

requireLogin();
if (!(hasPermission(FEATURE_BOOKINGS_CLASS_CREATE) || hasPermission(FEATURE_BOOKINGS_EXAM_CREATE))) {
    header('Location: ' . basePath('unauthorized.php'));
    exit;
}

$user = currentUser();
$dashboardPath = $user['role'] === ROLE_COORDINATOR ? '../coordinator/index.php' : 'index.php';
$bookingForId = $user['id'];

$searchDate = trim($_GET['date'] ?? '');
$searchStart = trim($_GET['start_time'] ?? '');
$searchEnd = trim($_GET['end_time'] ?? '');
$searchSubmitted = $searchDate !== '' || $searchStart !== '' || $searchEnd !== '';
$searchErrors = [];

if ($searchSubmitted) {
    if (!isValidDate($searchDate) || $searchDate < date('Y-m-d')) $searchErrors[] = 'Choose a valid date that is not in the past.';
    if (!isValidTime($searchStart) || !isValidTime($searchEnd) || $searchStart >= $searchEnd) $searchErrors[] = 'Choose a valid start and end time.';
}

$roomsSql = "
    SELECT r.id, r.room_code, r.name, r.building, r.floor, r.type, r.capacity, r.exam_capacity,
           r.primary_department, r.amenities, r.accessibility,
           r.has_projector, r.has_whiteboard, r.has_ac
    FROM rooms r
    WHERE r.status = 'Active'
";
$roomParams = [];
if (empty($searchErrors) && $searchSubmitted) {
    $roomsSql .= " AND NOT EXISTS (
        SELECT 1 FROM bookings b
        WHERE b.room_id = r.id AND b.date = :search_date
          AND b.status = 'Approved'
          AND b.start_time < :search_end AND b.end_time > :search_start
    )";
    $roomParams = [':search_date' => $searchDate, ':search_start' => $searchStart, ':search_end' => $searchEnd];
}
$roomsSql .= ' ORDER BY r.name, r.room_code';
$roomStmt = $pdo->prepare($roomsSql);
$roomStmt->execute($roomParams);
$rooms = $roomStmt->fetchAll();

$roomTypeFilter = trim($_GET['room_type'] ?? '');
$minimumCapacity = (int) ($_GET['minimum_capacity'] ?? 0);
$departmentFilter = trim($_GET['department'] ?? '');
$amenityFilters = array_values(array_intersect((array) ($_GET['amenities'] ?? []), ROOM_AMENITIES));
$accessibilityFilters = array_values(array_intersect((array) ($_GET['accessibility'] ?? []), ROOM_ACCESSIBILITY));

$rooms = array_values(array_filter($rooms, function (array $room) use ($roomTypeFilter, $minimumCapacity, $departmentFilter, $amenityFilters, $accessibilityFilters): bool {
    $roomAmenities = roomDecodeValues($room['amenities']);
    $roomAccessibility = roomDecodeValues($room['accessibility']);
    if ($roomTypeFilter !== '' && $room['type'] !== $roomTypeFilter) return false;
    if ($minimumCapacity > 0 && (int) $room['capacity'] < $minimumCapacity) return false;
    if ($departmentFilter !== '' && stripos((string) $room['primary_department'], $departmentFilter) === false) return false;
    foreach ($amenityFilters as $amenity) if (!in_array($amenity, $roomAmenities, true)) return false;
    foreach ($accessibilityFilters as $item) if (!in_array($item, $roomAccessibility, true)) return false;
    return true;
}));

$selectedRoomId = (int) ($_GET['selected_room'] ?? 0);
$confirmationStep = $_SERVER['REQUEST_METHOD'] === 'POST' || ($selectedRoomId > 0 && $searchSubmitted && empty($searchErrors));
$mediaByRoom = [];
if ($rooms) {
    $mediaStmt = $pdo->query('SELECT room_id, media_type, file_path FROM room_media ORDER BY id');
    foreach ($mediaStmt->fetchAll() as $mediaRow) $mediaByRoom[(int) $mediaRow['room_id']][] = $mediaRow;
}

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
    'exam_name'    => '',
    'invigilator_name' => '',
    'num_students' => '',
    'date'         => '',
    'start_time'   => '',
    'end_time'     => '',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $values['date'] = $searchDate;
    $values['start_time'] = $searchStart;
    $values['end_time'] = $searchEnd;
    $values['room_id'] = $selectedRoomId > 0 ? (string) $selectedRoomId : '';
}

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

        if ($values['type'] === 'Exam' && !hasPermission(FEATURE_BOOKINGS_EXAM_CREATE)) {
            $errors[] = 'You do not have permission to submit examination bookings.';
        }
        if ($values['type'] === 'Class' && !hasPermission(FEATURE_BOOKINGS_CLASS_CREATE)) {
            $errors[] = 'You do not have permission to submit class bookings.';
        }

        if ($values['type'] === 'Exam') {
            foreach (['exam_name' => 'Exam name', 'subject' => 'Subject', 'invigilator_name' => 'Invigilator name', 'num_students' => 'Number of students'] as $key => $label) {
                if ($values[$key] === '') {
                    $errors[] = "$label is required for examination bookings.";
                }
            }

            if ($values['num_students'] !== '' && (!ctype_digit($values['num_students']) || (int) $values['num_students'] < 1)) {
                $errors[] = 'Number of students must be a whole number of 1 or more.';
            }
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

            if ($values['type'] === 'Exam' && $selectedRoom && (int) $values['num_students'] > (int) ($selectedRoom['exam_capacity'] ?: $selectedRoom['capacity'])) {
                $examCapacity = (int) ($selectedRoom['exam_capacity'] ?: $selectedRoom['capacity']);
                $errors[] = "Number of students ({$values['num_students']}) exceeds the room's examination capacity ({$examCapacity}).";
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
            if ($values['date'] !== '' && isValidDate($values['date']) && $values['date'] > date('Y-m-d', strtotime('+' . (int) getBookingSetting($pdo, 'advance_days', '90') . ' days'))) {
                $errors[] = 'This booking is beyond the configured advance-booking window.';
            }
            if ($values['start_time'] !== '' && $values['end_time'] !== '' && isValidTime($values['start_time']) && isValidTime($values['end_time'])) {
                $minutes = (strtotime($values['end_time']) - strtotime($values['start_time'])) / 60;
                if ($minutes > (int) getBookingSetting($pdo, 'max_duration_minutes', '180')) $errors[] = 'This booking exceeds the configured maximum duration.';
            }
            if ($values['date'] !== '' && $values['start_time'] !== '' && $values['end_time']) {
                $blackout = bookingFallsInBlackout($pdo, $values['date'], $values['start_time'], $values['end_time']);
                if ($blackout) $errors[] = 'This time falls within a blackout period: ' . $blackout['reason'];
            }
        }

        // --- CHANGE 2: Shared Conflict Check Helper ---
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

            $status = getBookingSetting($pdo, 'approval_required', '1') === '1' ? 'Pending' : 'Approved';
            $insert = $pdo->prepare("
                INSERT INTO bookings (
                    room_id, user_id, programme_id, semester_id,
                    date, start_time, end_time, type, purpose, subject,
                    exam_name, invigilator_name, num_students, status
                ) VALUES (
                    :room_id, :user_id, :programme_id, :semester_id,
                    :date, :start_time, :end_time, :type, :purpose, :subject,
                    :exam_name, :invigilator_name, :num_students, :status
                )
            ");

            $insert->execute([
                ':room_id'      => $values['room_id'],
                    ':user_id'      => $bookingForId,
                ':programme_id' => $values['programme_id'],
                ':semester_id'  => $values['semester_id'],
                ':date'         => $values['date'],
                ':start_time'   => $values['start_time'],
                ':end_time'     => $values['end_time'],
                ':type'         => $values['type'],
                ':purpose'      => $values['purpose'] !== '' ? $values['purpose'] : null,
                ':subject'      => $values['subject'] !== '' ? $values['subject'] : null,
                ':exam_name'    => $values['type'] === 'Exam' ? $values['exam_name'] : null,
                ':invigilator_name' => $values['type'] === 'Exam' ? $values['invigilator_name'] : null,
                ':num_students' => $values['type'] === 'Exam' ? (int) $values['num_students'] : null,
                ':status'       => $status,
            ]);

            $createdBookingId = (int) $pdo->lastInsertId();

            if ($values['type'] === 'Exam') {
                $examInsert = $pdo->prepare("INSERT INTO exam_details (booking_id, exam_name, subject, invigilator_name, num_students) VALUES (:booking_id, :exam_name, :subject, :invigilator_name, :num_students)");
                $examInsert->execute([
                    ':booking_id' => $createdBookingId,
                    ':exam_name' => $values['exam_name'],
                    ':subject' => $values['subject'],
                    ':invigilator_name' => $values['invigilator_name'],
                    ':num_students' => (int) $values['num_students'],
                ]);
            }

            $pdo->commit();

            setFlash('success', 'Booking request submitted. It is now pending approval. Reference #' . (int) $pdo->lastInsertId());
            header('Location: ' . ($user['role'] === ROLE_COORDINATOR ? '../coordinator/bookings.php' : 'my_bookings.php'));
            exit;
        }
    }
}

$csrfToken = generateCsrfToken();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedRoomId = (int) $values['room_id'];
    $searchDate = $values['date'];
    $searchStart = $values['start_time'];
    $searchEnd = $values['end_time'];
}

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
        .exam-only { display: none; }
        .duration-buttons { display: flex; gap: 6px; align-items: end; flex-wrap: wrap; }
        .duration-buttons label { width: 100%; }
        .duration-buttons button { padding: 10px 12px; border: 1px solid #ccc; background: #fff; cursor: pointer; }
        fieldset { border: 1px solid #ddd; padding: 14px; margin: 0 0 20px; }
        legend { padding: 0 5px; font-size: 10px; font-weight: 700; letter-spacing: .1em; }
        .check { display: inline-flex !important; width: auto; margin: 5px 14px 5px 0 !important; font-size: 12px !important; font-weight: 400 !important; }
        .check input { width: auto; margin: 0 5px 0 0; }
        .results-section { margin: 35px 0; }
        .room-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 18px; }
        .room-card { background: #fff; border: 1px solid #ddd; overflow: hidden; }
        .room-card > img, .room-placeholder { width: 100%; height: 150px; object-fit: cover; background: #eae8e1; display: grid; place-items: center; color: #777; }
        .room-card-body { padding: 18px; }
        .room-card h2 { margin: 0 0 8px; font-size: 20px; }
        .room-card p { margin: 6px 0; color: #666; font-size: 12px; }
        .tags { display: flex; gap: 5px; flex-wrap: wrap; margin: 12px 0; }
        .tags span { padding: 4px 7px; background: #e8f0f5; font-size: 10px; }
        .empty-state { padding: 30px; background: #fff; border: 1px solid #ddd; color: #777; }

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
            <a href="<?= htmlspecialchars($dashboardPath) ?>">← BACK TO DASHBOARD</a>
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

        <?php if (!empty($searchErrors)): ?>
            <div class="form-errors"><ul><?php foreach ($searchErrors as $searchError): ?><li><?= htmlspecialchars($searchError) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <section class="filter-box">
            <p class="page-label">STEP 1 / FIND A SLOT</p>
            <form method="GET" action="new_booking.php" class="booking-form" id="availability-form">
                <div>
                    <label for="search_date">Date</label>
                    <input type="date" id="search_date" name="date" required min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($searchDate) ?>">
                </div>
                <div class="duration-buttons">
                    <label>Quick duration</label>
                    <button type="button" data-duration="60">1h</button>
                    <button type="button" data-duration="90">1.5h</button>
                    <button type="button" data-duration="120">2h</button>
                    <button type="button" data-duration="180">3h</button>
                </div>
                <div>
                    <label for="search_start">Start time</label>
                    <input type="time" id="search_start" name="start_time" required value="<?= htmlspecialchars($searchStart) ?>">
                </div>
                <div>
                    <label for="search_end">End time</label>
                    <input type="time" id="search_end" name="end_time" required value="<?= htmlspecialchars($searchEnd) ?>">
                </div>
                <div class="full">
                    <label for="room_type">Room type</label>
                    <select id="room_type" name="room_type">
                        <option value="">Any room type</option>
                        <?php foreach (ROOM_TYPES as $roomType): ?><option value="<?= htmlspecialchars($roomType) ?>" <?= $roomTypeFilter === $roomType ? 'selected' : '' ?>><?= htmlspecialchars($roomType) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="minimum_capacity">Minimum capacity</label>
                    <input type="number" id="minimum_capacity" name="minimum_capacity" min="1" value="<?= $minimumCapacity ?: '' ?>">
                </div>
                <div>
                    <label for="department">Department</label>
                    <input type="text" id="department" name="department" value="<?= htmlspecialchars($departmentFilter) ?>" placeholder="Optional">
                </div>
                <fieldset class="full"><legend>Amenities</legend><?php foreach (ROOM_AMENITIES as $amenity): ?><label class="check"><input type="checkbox" name="amenities[]" value="<?= htmlspecialchars($amenity) ?>" <?= in_array($amenity, $amenityFilters, true) ? 'checked' : '' ?>> <?= htmlspecialchars($amenity) ?></label><?php endforeach; ?></fieldset>
                <fieldset class="full"><legend>Accessibility</legend><?php foreach (ROOM_ACCESSIBILITY as $item): ?><label class="check"><input type="checkbox" name="accessibility[]" value="<?= htmlspecialchars($item) ?>" <?= in_array($item, $accessibilityFilters, true) ? 'checked' : '' ?>> <?= htmlspecialchars($item) ?></label><?php endforeach; ?></fieldset>
                <div class="form-actions"><button type="submit" class="btn">FIND AVAILABLE ROOMS</button></div>
            </form>
        </section>

        <?php if ($searchSubmitted && empty($searchErrors) && !$confirmationStep): ?>
            <section class="results-section">
                <p class="page-label">STEP 3 / AVAILABLE ROOMS</p>
                <?php if (!$rooms): ?><p class="empty-state">No Active rooms are available for this time slot and filter combination.</p><?php else: ?>
                    <div class="room-grid">
                    <?php foreach ($rooms as $room): $roomMedia = $mediaByRoom[(int) $room['id']] ?? []; $thumbnail = $roomMedia[0]['file_path'] ?? null; $bookQuery = $_GET; $bookQuery['selected_room'] = (int) $room['id']; ?>
                        <article class="room-card">
                            <?php if ($thumbnail): ?><img src="../<?= htmlspecialchars($thumbnail) ?>" alt="Room photo"><?php else: ?><div class="room-placeholder">No photo</div><?php endif; ?>
                            <div class="room-card-body"><h2><?= htmlspecialchars($room['name'] ?: $room['room_code']) ?></h2><p><?= htmlspecialchars($room['room_code']) ?> · <?= htmlspecialchars($room['type']) ?></p><p>Capacity: <?= (int) $room['capacity'] ?><?php if ($room['exam_capacity']): ?> · Exam: <?= (int) $room['exam_capacity'] ?><?php endif; ?></p><p><?= htmlspecialchars($room['primary_department']) ?></p><div class="tags"><?php foreach (roomDecodeValues($room['amenities']) as $amenity): ?><span><?= htmlspecialchars($amenity) ?></span><?php endforeach; ?></div><a class="btn btn-secondary" target="_blank" rel="noopener" href="../modules/rooms/view.php?id=<?= (int) $room['id'] ?>">VIEW DETAILS</a> <a class="btn" href="?<?= htmlspecialchars(http_build_query($bookQuery)) ?>">BOOK NOW</a></div>
                        </article>
                    <?php endforeach; ?></div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($confirmationStep): ?>
        <p class="page-label">STEP 5 / CONFIRM BOOKING</p>

        <div class="filter-box">
            <?php $selectedRoom = null; foreach ($rooms as $room): if ((int) $room['id'] === $selectedRoomId) { $selectedRoom = $room; break; } endforeach; ?>
            <strong>Selected room</strong>
            <?php if ($selectedRoom): ?>
                <p><?= htmlspecialchars($selectedRoom['name'] ?: $selectedRoom['room_code']) ?> · <?= htmlspecialchars($selectedRoom['type']) ?> · Capacity <?= (int) $selectedRoom['capacity'] ?></p>
                <p><?= htmlspecialchars(($selectedRoom['building'] ?: 'Location not specified') . ($selectedRoom['floor'] ? ' · Floor ' . $selectedRoom['floor'] : '')) ?></p>
                <p><a href="../modules/rooms/view.php?id=<?= (int) $selectedRoom['id'] ?>" target="_blank" rel="noopener">Review full room details</a></p>
            <?php else: ?>
                <p>Selected room is no longer available. Return to the search step.</p>
            <?php endif; ?>
            <p><?= htmlspecialchars($searchDate) ?> · <?= htmlspecialchars($searchStart) ?> - <?= htmlspecialchars($searchEnd) ?></p>
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
                <label for="subject">Subject <span style="font-weight:400;">(required for examinations)</span></label>
                <input type="text" id="subject" name="subject" maxlength="100"
                       value="<?= htmlspecialchars($values['subject']) ?>">
            </div>

            <div class="full exam-only">
                <label for="exam_name">Exam Name</label>
                <input type="text" id="exam_name" name="exam_name" maxlength="150"
                       value="<?= htmlspecialchars($values['exam_name']) ?>" placeholder="e.g. Midterm Examination">
            </div>

            <div class="exam-only">
                <label for="invigilator_name">Invigilator Name</label>
                <input type="text" id="invigilator_name" name="invigilator_name" maxlength="100"
                       value="<?= htmlspecialchars($values['invigilator_name']) ?>">
            </div>

            <div class="exam-only">
                <label for="num_students">Expected Students</label>
                <input type="number" id="num_students" name="num_students" min="1"
                       value="<?= htmlspecialchars($values['num_students']) ?>">
            </div>

            <div class="full">
                <label for="purpose">Purpose <span style="font-weight:400;">(optional)</span></label>
                <input type="text" id="purpose" name="purpose" maxlength="255"
                       value="<?= htmlspecialchars($values['purpose']) ?>" placeholder="e.g. Regular lecture — Data Structures">
            </div>

            <input type="hidden" name="room_id" value="<?= htmlspecialchars($values['room_id']) ?>">

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

        <?php endif; ?>

    </main>

</div>

<script>
    document.getElementById('availability-form').addEventListener('submit', function (event) {
        const date = document.getElementById('search_date').value;
        const start = document.getElementById('search_start').value;
        const end = document.getElementById('search_end').value;
        if (!date || !start || !end || start >= end) {
            event.preventDefault();
            window.alert('Select a valid date, start time, and end time.');
        }
    });

    const allRooms = <?= json_encode($rooms) ?>;
    const preselectedRoomId = <?= json_encode($values['room_id'] !== '' ? (int) $values['room_id'] : null) ?>;

    const semestersByProgramme = <?= json_encode($semestersByProgramme) ?>;
    const preselectedSemesterId = <?= json_encode($values['semester_id'] !== '' ? (int) $values['semester_id'] : null) ?>;

    const programmeSelect = document.getElementById('programme_id');
    const semesterSelect  = document.getElementById('semester_id');
    const roomSelect      = document.getElementById('room_id');
    const roomDetailsLink = document.getElementById('room-details-link');

    const filterProjector  = document.getElementById('filter_projector');
    const filterWhiteboard = document.getElementById('filter_whiteboard');
    const filterAc         = document.getElementById('filter_ac');
    const filterRoomType   = document.getElementById('filter_room_type');
    const filterDepartment = document.getElementById('filter_department');
    const examFields       = document.querySelectorAll('.exam-only');
    const typeInputs       = document.querySelectorAll('input[name="type"]');
    const bookingForm      = document.querySelector('form.booking-form');

    function toggleExamFields() {
        const isExam = document.querySelector('input[name="type"]:checked')?.value === 'Exam';
        examFields.forEach(function (field) {
            field.style.display = isExam ? '' : 'none';
            field.querySelectorAll('input').forEach(function (input) {
                input.required = isExam;
            });
        });
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

    function populateRooms() {
        const wantProjector  = filterProjector.checked;
        const wantWhiteboard = filterWhiteboard.checked;
        const wantAc         = filterAc.checked;
        const roomType = filterRoomType.value.toLowerCase().trim();
        const department = filterDepartment.value.toLowerCase().trim();

        const filtered = allRooms.filter(function (r) {
            if (roomType && r.type.toLowerCase().indexOf(roomType) === -1) return false;
            if (department && r.primary_department.toLowerCase().indexOf(department) === -1) return false;
            if (wantProjector && !r.has_projector) return false;
            if (wantWhiteboard && !r.has_whiteboard) return false;
            if (wantAc && !r.has_ac) return false;
            return true;
        });


            writeBookingAudit($pdo, $createdBookingId, $user['id'], 'created');
        roomSelect.innerHTML = '<option value="">Select room</option>';

        filtered.forEach(function (r) {
            setFlash('success', 'Booking request submitted. Reference #' . $createdBookingId);
            opt.value = r.id;
            const displayName = r.name || r.room_code;
            const examCapacity = r.exam_capacity ? ', exam cap. ' + r.exam_capacity : '';
            opt.textContent = displayName + ' [' + r.room_code + '] — ' + r.type + ' (cap. ' + r.capacity + examCapacity + ', ' + r.primary_department + ')';
            if (preselectedRoomId !== null && r.id === preselectedRoomId) {
                opt.selected = true;
            }
            roomSelect.appendChild(opt);
        });

        if (filtered.length === 0) {
            roomSelect.innerHTML = '<option value="">No rooms match these filters</option>';
        }
        updateRoomDetailsLink();
    }

    function updateRoomDetailsLink() {
        if (roomSelect.value) {
            roomDetailsLink.href = '../../modules/rooms/view.php?id=' + encodeURIComponent(roomSelect.value);
            roomDetailsLink.hidden = false;
        } else {
            roomDetailsLink.hidden = true;
        }
    }

    programmeSelect.addEventListener('change', populateSemesters);
    typeInputs.forEach(function (input) {
        input.addEventListener('change', toggleExamFields);
    });
    bookingForm.addEventListener('submit', function (event) {
        const start = document.getElementById('start_time').value;
        const end = document.getElementById('end_time').value;
        const selectedType = document.querySelector('input[name="type"]:checked')?.value;
        const errors = [];

        if (start && end && start >= end) {
            errors.push('End time must be after start time.');
        }
        if (selectedType === 'Exam') {
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
    [filterProjector, filterWhiteboard, filterAc].forEach(function (el) {
        el.addEventListener('change', populateRooms);
    });
    [filterRoomType, filterDepartment].forEach(function (el) {
        el.addEventListener('input', populateRooms);
    });
    roomSelect.addEventListener('change', updateRoomDetailsLink);

    if (programmeSelect.value) {
        populateSemesters();
    }
    toggleExamFields();
    populateRooms();
</script>

<script>
    document.querySelectorAll('[data-duration]').forEach(function (button) {
        button.addEventListener('click', function () {
            const start = document.getElementById('search_start');
            const end = document.getElementById('search_end');
            if (!start.value) return;
            const parts = start.value.split(':');
            const date = new Date(2000, 0, 1, Number(parts[0]), Number(parts[1]));
            date.setMinutes(date.getMinutes() + Number(button.dataset.duration));
            end.value = String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
        });
    });
</script>

</body>
</html>