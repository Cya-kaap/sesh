<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole([ROLE_ADMIN]);
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT b.*, u.full_name AS faculty_name, u.email AS faculty_email, r.room_code, r.name AS room_name, r.type AS room_type, r.capacity, p.name AS programme_name, s.number AS semester_number, approver.full_name AS approver_name, canceller.full_name AS canceller_name FROM bookings b JOIN users u ON u.id = b.user_id JOIN rooms r ON r.id = b.room_id JOIN programmes p ON p.id = b.programme_id JOIN semesters s ON s.id = b.semester_id LEFT JOIN users approver ON approver.id = b.approved_by LEFT JOIN users canceller ON canceller.id = b.cancelled_by WHERE b.id = :id");
$stmt->execute([':id' => $id]);
$booking = $stmt->fetch();
if (!$booking) { setFlash('error', 'Booking not found.'); header('Location: manage.php'); exit; }
$examStmt = $pdo->prepare('SELECT * FROM exam_details WHERE booking_id = :id');
$examStmt->execute([':id' => $id]);
$exam = $examStmt->fetch();
$auditStmt = $pdo->prepare('SELECT a.*, u.full_name FROM booking_audit a JOIN users u ON u.id = a.actor_id WHERE a.booking_id = :id ORDER BY a.created_at DESC');
$auditStmt->execute([':id' => $id]);
$audit = $auditStmt->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Booking Details | SESH</title><link rel="stylesheet" href="../../assets/css/style.css"><style>.page{width:90%;max-width:900px;margin:auto;padding:50px 0}.card{background:#fff;border:1px solid #ddd;padding:22px;margin:18px 0}.row{display:grid;grid-template-columns:180px 1fr;padding:9px 0;border-bottom:1px solid #eee}.audit{padding:10px 0;border-bottom:1px solid #eee}</style></head><body><main class="page"><p><a href="manage.php">&larr; Booking Management</a></p><h1>Booking #<?= (int) $booking['id'] ?></h1><section class="card"><div class="row"><strong>Status</strong><span><?= htmlspecialchars($booking['status']) ?></span></div><div class="row"><strong>Faculty</strong><span><?= htmlspecialchars($booking['faculty_name'] . ' (' . $booking['faculty_email'] . ')') ?></span></div><div class="row"><strong>Room</strong><span><?= htmlspecialchars($booking['room_code'] . ' ' . ($booking['room_name'] ?: '') . ' (' . $booking['room_type'] . ')') ?></span></div><div class="row"><strong>Date/time</strong><span><?= htmlspecialchars($booking['date'] . ' ' . $booking['start_time'] . ' - ' . $booking['end_time']) ?></span></div><div class="row"><strong>Programme</strong><span><?= htmlspecialchars($booking['programme_name']) ?> / Semester <?= (int) $booking['semester_number'] ?></span></div><div class="row"><strong>Type</strong><span><?= htmlspecialchars($booking['type']) ?></span></div><div class="row"><strong>Purpose</strong><span><?= htmlspecialchars($booking['purpose'] ?: '') ?></span></div><?php if ($exam): ?><div class="row"><strong>Exam name</strong><span><?= htmlspecialchars($exam['exam_name']) ?></span></div><div class="row"><strong>Subject</strong><span><?= htmlspecialchars($exam['subject']) ?></span></div><div class="row"><strong>Invigilator</strong><span><?= htmlspecialchars($exam['invigilator_name']) ?></span></div><div class="row"><strong>Expected students</strong><span><?= (int) $exam['num_students'] ?></span></div><?php endif; ?><?php if ($booking['rejection_reason']): ?><div class="row"><strong>Rejection reason</strong><span><?= htmlspecialchars($booking['rejection_reason']) ?></span></div><?php endif; ?></section><section class="card"><h2>Audit Trail</h2><?php if (!$audit): ?><p>No audit entries recorded.</p><?php else: foreach ($audit as $entry): ?><div class="audit"><strong><?= htmlspecialchars(ucfirst($entry['action'])) ?></strong> by <?= htmlspecialchars($entry['full_name']) ?><br><small><?= htmlspecialchars($entry['created_at']) ?><?= $entry['notes'] ? ' - ' . htmlspecialchars($entry['notes']) : '' ?></small></div><?php endforeach; endif; ?></section></main></body></html>
