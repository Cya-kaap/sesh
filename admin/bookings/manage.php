<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole([ROLE_ADMIN]);

$user = currentUser();
$errors = [];
$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your session expired. Please try again.');
        header('Location: manage.php');
        exit;
    }

    $bookingId = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['rejection_reason'] ?? '');

    if ($bookingId < 1 || !in_array($action, ['approve', 'reject', 'cancel'], true)) {
        setFlash('error', 'Invalid booking action.');
    } else {
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $bookingId]);
            $booking = $stmt->fetch();

            if (!$booking) {
                throw new RuntimeException('Booking not found.');
            }

            if ($action === 'approve') {
                if ($booking['status'] !== 'Pending') throw new RuntimeException('Only pending bookings can be approved.');
                if (findBookingConflict($pdo, (int) $booking['room_id'], $booking['date'], $booking['start_time'], $booking['end_time'], $bookingId, true)) {
                    throw new RuntimeException('Cannot approve: this booking overlaps an existing approved booking.');
                }
                $update = $pdo->prepare("UPDATE bookings SET status = 'Approved', approved_by = :actor, approved_at = NOW(), rejection_reason = NULL WHERE id = :id");
                $update->execute([':actor' => $user['id'], ':id' => $bookingId]);
                writeBookingAudit($pdo, $bookingId, $user['id'], 'approved');
                $message = 'Booking approved.';
            } elseif ($action === 'reject') {
                if ($booking['status'] !== 'Pending') throw new RuntimeException('Only pending bookings can be rejected.');
                if ($reason === '') throw new RuntimeException('A rejection reason is required.');
                $update = $pdo->prepare("UPDATE bookings SET status = 'Rejected', rejection_reason = :reason WHERE id = :id");
                $update->execute([':reason' => $reason, ':id' => $bookingId]);
                writeBookingAudit($pdo, $bookingId, $user['id'], 'rejected', $reason);
                $message = 'Booking rejected.';
            } else {
                if (!in_array($booking['status'], ['Pending', 'Approved'], true)) throw new RuntimeException('This booking cannot be cancelled.');
                $update = $pdo->prepare("UPDATE bookings SET status = 'Cancelled', cancelled_by = :actor, cancelled_at = NOW() WHERE id = :id");
                $update->execute([':actor' => $user['id'], ':id' => $bookingId]);
                writeBookingAudit($pdo, $bookingId, $user['id'], 'cancelled');
                $message = 'Booking cancelled.';
            }

            $pdo->commit();
            setFlash('success', $message);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            setFlash('error', $e->getMessage());
        }
    }

    header('Location: manage.php');
    exit;
}

$dateFilter = trim($_GET['date'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$roomFilter = (int) ($_GET['room_id'] ?? 0);
$facultyFilter = trim($_GET['faculty'] ?? '');
$departmentFilter = trim($_GET['department'] ?? '');
$where = ['1 = 1'];
$params = [];
if ($dateFilter !== '' && isValidDate($dateFilter)) { $where[] = 'b.date = :date'; $params[':date'] = $dateFilter; }
if ($statusFilter !== '') { $where[] = 'b.status = :status'; $params[':status'] = $statusFilter; }
if ($roomFilter > 0) { $where[] = 'b.room_id = :room_id'; $params[':room_id'] = $roomFilter; }
if ($facultyFilter !== '') { $where[] = 'u.full_name LIKE :faculty'; $params[':faculty'] = '%' . $facultyFilter . '%'; }
if ($departmentFilter !== '') { $where[] = 'p.name LIKE :department'; $params[':department'] = '%' . $departmentFilter . '%'; }

$stmt = $pdo->prepare("SELECT b.*, u.full_name AS faculty_name, u.email AS faculty_email, r.name AS room_name, r.room_code, p.name AS programme_name, s.number AS semester_number, approver.full_name AS approver_name FROM bookings b JOIN users u ON u.id = b.user_id JOIN rooms r ON r.id = b.room_id JOIN programmes p ON p.id = b.programme_id JOIN semesters s ON s.id = b.semester_id LEFT JOIN users approver ON approver.id = b.approved_by WHERE " . implode(' AND ', $where) . ' ORDER BY b.date DESC, b.start_time DESC');
$stmt->execute($params);
$bookings = $stmt->fetchAll();
$rooms = $pdo->query('SELECT id, room_code, name FROM rooms ORDER BY room_code')->fetchAll();
$statuses = ['Pending', 'Approved', 'Rejected', 'Cancelled', 'Completed'];
$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Booking Management | SESH</title>
<link rel="stylesheet" href="../../assets/css/style.css">
<style>
.admin-page{min-height:100vh;background:#f4f3ef;color:#111}.admin-nav{padding:22px 6vw;display:flex;justify-content:space-between;border-bottom:1px solid #d5d5d0}.admin-nav a{color:#111;text-decoration:none}.admin-content{width:92%;max-width:1500px;margin:auto;padding:55px 0}.filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:28px 0}.filters label{font-size:10px;font-weight:700;letter-spacing:.1em}.filters input,.filters select,.filters button{width:100%;padding:11px;border:1px solid #ccc;background:#fff}.filters button{background:#111;color:#fff}.table-wrap{overflow-x:auto}.data-table{width:100%;border-collapse:collapse;background:#fff}.data-table th,.data-table td{padding:13px 10px;border-bottom:1px solid #ddd;text-align:left;font-size:12px;vertical-align:top}.data-table th{font-size:9px;background:#eae8e1}.actions{display:flex;gap:6px;flex-wrap:wrap}.actions form{display:inline}.actions button{padding:6px 8px;border:1px solid #111;background:#111;color:#fff;font-size:10px}.actions .reject{background:#fff;color:#a33;border-color:#a33}.flash{padding:14px;margin:15px 0}.flash-success{background:#d4edda}.flash-error{background:#f8d7da}.reason{width:150px;padding:6px;border:1px solid #ccc}.badge{padding:4px 7px;font-size:9px;text-transform:uppercase}.badge-pending{background:#fff3cd}.badge-approved{background:#d4edda}.badge-rejected{background:#f8d7da}.badge-cancelled{background:#eee}.badge-completed{background:#d1ecf1}
</style>
</head>
<body><div class="admin-page"><header class="admin-nav"><a href="../index.php">SESH Admin</a><a href="../../logout.php">LOGOUT</a></header><main class="admin-content"><p>BOOKING CONTROL</p><h1>All Bookings</h1>
<?php if (!empty($flash['success'])): ?><div class="flash flash-success"><?= htmlspecialchars($flash['success']) ?></div><?php endif; ?><?php if (!empty($flash['error'])): ?><div class="flash flash-error"><?= htmlspecialchars($flash['error']) ?></div><?php endif; ?>
<form method="GET" class="filters"><label>Date<input type="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>"></label><label>Status<select name="status"><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option><?php endforeach; ?></select></label><label>Room<select name="room_id"><option value="0">All rooms</option><?php foreach ($rooms as $room): ?><option value="<?= (int) $room['id'] ?>" <?= $roomFilter === (int) $room['id'] ? 'selected' : '' ?>><?= htmlspecialchars($room['room_code'] . ' ' . ($room['name'] ?: '')) ?></option><?php endforeach; ?></select></label><label>Faculty<input name="faculty" value="<?= htmlspecialchars($facultyFilter) ?>"></label><label>Department / Programme<input name="department" value="<?= htmlspecialchars($departmentFilter) ?>"></label><button type="submit">FILTER</button></form>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Date / Time</th><th>Room</th><th>Faculty</th><th>Programme</th><th>Type / Purpose</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php if (!$bookings): ?><tr><td colspan="7">No bookings found.</td></tr><?php else: foreach ($bookings as $booking): ?><tr><td><?= htmlspecialchars($booking['date']) ?><br><?= htmlspecialchars(substr($booking['start_time'],0,5) . ' - ' . substr($booking['end_time'],0,5)) ?></td><td><strong><?= htmlspecialchars($booking['room_code']) ?></strong><br><?= htmlspecialchars($booking['room_name'] ?: '') ?></td><td><?= htmlspecialchars($booking['faculty_name']) ?><br><small><?= htmlspecialchars($booking['faculty_email']) ?></small></td><td><?= htmlspecialchars($booking['programme_name']) ?><br>Semester <?= (int) $booking['semester_number'] ?></td><td><?= htmlspecialchars($booking['type']) ?><br><?= htmlspecialchars($booking['purpose'] ?: $booking['exam_name'] ?: '') ?></td><td><span class="badge badge-<?= strtolower($booking['status']) ?>"><?= htmlspecialchars($booking['status']) ?></span><?php if ($booking['rejection_reason']): ?><br><small><?= htmlspecialchars($booking['rejection_reason']) ?></small><?php endif; ?></td><td><div class="actions"><?php if ($booking['status'] === 'Pending'): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="id" value="<?= (int) $booking['id'] ?>"><button name="action" value="approve">APPROVE</button><input class="reason" name="rejection_reason" maxlength="500" placeholder="Rejection reason"><button class="reject" name="action" value="reject">REJECT</button></form><?php endif; ?><?php if (in_array($booking['status'], ['Pending','Approved'], true)): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="id" value="<?= (int) $booking['id'] ?>"><button class="reject" name="action" value="cancel">CANCEL</button></form><?php endif; ?></div><?php if ($booking['approved_by']): ?><small>Approved by <?= htmlspecialchars($booking['approver_name'] ?: 'Admin') ?></small><?php endif; ?></td></tr><?php endforeach; endif; ?></tbody></table></div></main></div></body></html>
