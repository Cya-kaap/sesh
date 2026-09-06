<?php

/**
 * SESH - Edit Booking (modules pathway)
 *
 * Purpose:
 *   Lets the owner of a Pending booking (or Admin/Coordinator, per scope
 *   below) change its room, programme, semester, date, time, type, and
 *   purpose before it is approved.
 *
 * Requires tables:
 *   - bookings, rooms, programmes, semesters, users
 *
 * Access:
 *   The Permission Matrix has no dedicated "Edit Booking" row. Editing an
 *   existing booking record is treated the same as Cancel Bookings rather
 *   than as Book Regular Classes / Book Examination Rooms, because both
 *   editing and cancelling act on a booking that already exists, while the
 *   "Book..." rows describe creating a new one. This matters concretely:
 *   Coordinator's tier on Book Regular Classes is Full Access (unscoped),
 *   but their tier on Cancel Bookings is Department Bookings (scoped) —
 *   and the Business Rules describe the Coordinator role itself as
 *   "Department-level scheduling and management authority" and
 *   "Creating and managing departmental bookings," which supports treating
 *   post-creation management of an existing booking as department-scoped
 *   for Coordinator, matching Cancel Bookings rather than Book Regular
 *   Classes. So this file reuses FEATURE_BOOKINGS_CANCEL's tiers:
 *     Admin       - Any Booking (System Override — bypasses scope checks)
 *     Coordinator - Department Bookings (own programme_id only)
 *     Faculty     - Assigned Bookings Only (own user_id only)
 *     Student, Maintenance - No Access
 *
 * Security notes (fixed in this revision):
 *   - Previously had no CSRF protection at all — the form carried no
 *     token and the POST handler never checked one. Now POST submissions
 *     require a valid csrf_token, matching delete.php and list.php.
 *   - Previously let Admin AND Coordinator edit any booking system-wide
 *     with no department scoping ($canEditAny = in_array($role,
 *     ['Admin', 'Coordinator'])). Now scoped through authorizeScope(),
 *     the same engine and the same feature delete.php enforces.
 *   - Previously compared $booking['status'] against the lowercase
 *     literal 'pending', which never matched the schema's actual
 *     capitalized 'Pending' — meaning nobody could successfully edit any
 *     booking through this file regardless of role. Fixed to 'Pending'.
 *   - Previously required a second, independent copy of
 *     hasBookingConflict() from modules/bookings/conflict_check.php,
 *     which checked lowercase 'pending'/'approved' against the status
 *     column (works today only because MySQL's default collation is
 *     case-insensitive) instead of the canonical, correctly-cased
 *     implementation already in includes/functions.php. This file now
 *     requires includes/functions.php and uses that single implementation
 *     instead — do not also require conflict_check.php from this file, as
 *     both define hasBookingConflict() and loading both is a fatal
 *     "cannot redeclare function" error.
 *   - Previously used die()/http_response_code() for booking-not-found,
 *     unauthorized, and wrong-status cases, which dumped raw unstyled text
 *     with no navigation and no flash-message consistency. These now
 *     setFlash() and redirect to list.php, which already renders flashes.
 *   - Previously read the booking id from either $_GET['id'] or
 *     $_POST['id'] interchangeably on every request. Now reads from
 *     $_GET['id'] only when displaying the form (GET) and $_POST['id']
 *     only when processing a submission (POST).
 *
 * Known limitation carried over (not fixed here):
 *   The edit form still lets a Faculty member reassign their own booking
 *   to an arbitrary programme_id, because the schema has no table linking
 *   Faculty to the classes/subjects they are actually assigned to — the
 *   same "Assigned Classes Only" data-model gap already identified for
 *   booking creation. Closing it here without that table would be an
 *   arbitrary, incomplete rule; it should be fixed once, at the schema
 *   level, and enforced consistently across creation, editing, and
 *   cancellation.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

// Matrix-driven gate, reusing Cancel Bookings' role set (see docblock
// above for why): identical admission to the original
// requireRole(['Faculty', 'Coordinator', 'Admin']).
requireWritePermission(FEATURE_BOOKINGS_CANCEL);

$user = currentUser();

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$id     = $isPost ? (int) ($_POST['id'] ?? 0) : (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Invalid booking.');
    header('Location: list.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = :id");
$stmt->execute([':id' => $id]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlash('error', 'Booking not found.');
    header('Location: list.php');
    exit;
}

// Department Scope Validation only matters for the Coordinator tier, so
// the extra lookup is skipped for everyone else. Fetched fresh from the
// database rather than trusted from session — same reasoning already
// applied in coordinator/index.php and the hardened delete.php.
$actorProgrammeId = null;

if ($user['role'] === ROLE_COORDINATOR) {
    $programmeStmt = $pdo->prepare("SELECT programme_id FROM users WHERE id = :id");
    $programmeStmt->execute([':id' => $user['id']]);
    $programmeId = $programmeStmt->fetchColumn();
    $actorProgrammeId = ($programmeId !== false && $programmeId !== null) ? (int) $programmeId : null;
}

$authorized = authorizeScope(
    FEATURE_BOOKINGS_CANCEL,
    [
        'owner_id'     => (int) $booking['user_id'],
        'programme_id' => (int) $booking['programme_id'],
    ],
    true,
    $actorProgrammeId
);

if (!$authorized) {
    setFlash('error', 'You are not authorized to edit this booking.');
    header('Location: list.php');
    exit;
}

if ($booking['status'] !== 'Pending') {
    setFlash('error', 'Only pending bookings can be edited.');
    header('Location: list.php');
    exit;
}

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
$errors    = [];

if ($isPost) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your session expired. Please try again.');
        header('Location: list.php');
        exit;
    }

    $room_id      = (int) ($_POST['room_id'] ?? 0);
    $programme_id = (int) ($_POST['programme_id'] ?? 0);
    $semester_id  = (int) ($_POST['semester_id'] ?? 0);
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

    if (empty($errors)) {
        if (hasBookingConflict($pdo, $room_id, $date, $start_time, $end_time, $id)) {
            $errors[] = 'This room is already booked or has a pending booking during the selected time.';
        }
    }

    if (empty($errors)) {

        $update = $pdo->prepare("
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

        $update->execute([
            ':room_id'      => $room_id,
            ':programme_id' => $programme_id,
            ':semester_id'  => $semester_id,
            ':date'         => $date,
            ':start_time'   => $start_time,
            ':end_time'     => $end_time,
            ':type'         => $type,
            ':purpose'      => $purpose,
            ':id'           => $id,
        ]);

        setFlash('success', 'Booking updated successfully.');
        header('Location: list.php');
        exit;
    }

    // Preserve entered values so the form redisplays what the user typed.
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

        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">


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