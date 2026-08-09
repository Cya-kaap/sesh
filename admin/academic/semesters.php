<?php

/**
 * SESH - Admin: Academic Setup (Semesters for one Programme)
 *
 * Access: Admin only.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['Admin']);

$programmeId = (int) ($_GET['programme_id'] ?? $_POST['programme_id'] ?? 0);

$progStmt = $pdo->prepare("SELECT id, name FROM programmes WHERE id = :id");
$progStmt->execute([':id' => $programmeId]);
$programme = $progStmt->fetch();

if (!$programme) {
    setFlash('error', 'Programme not found.');
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Add Semester
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_semester') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        setFlash('error', 'Your session expired. Please try again.');

    } else {

        $number = (int) ($_POST['number'] ?? 0);

        if ($number < 1 || $number > 20) {
            setFlash('error', 'Semester number must be between 1 and 20.');
        } else {

            $dup = $pdo->prepare("SELECT id FROM semesters WHERE programme_id = :pid AND number = :num");
            $dup->execute([':pid' => $programmeId, ':num' => $number]);

            if ($dup->fetch()) {
                setFlash('error', "Semester $number already exists for this programme.");
            } else {
                $ins = $pdo->prepare("INSERT INTO semesters (programme_id, number) VALUES (:pid, :num)");
                $ins->execute([':pid' => $programmeId, ':num' => $number]);
                setFlash('success', "Semester $number added.");
            }
        }
    }

    header('Location: semesters.php?programme_id=' . $programmeId);
    exit;
}

/*
|--------------------------------------------------------------------------
| Delete Semester
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_semester') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        setFlash('error', 'Your session expired. Please try again.');

    } else {

        $semesterId = (int) ($_POST['id'] ?? 0);

        $bookingCheck = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE semester_id = :sid");
        $bookingCheck->execute([':sid' => $semesterId]);

        if ((int) $bookingCheck->fetchColumn() > 0) {
            setFlash('error', 'Cannot delete: this semester has existing bookings.');
        } else {
            $del = $pdo->prepare("DELETE FROM semesters WHERE id = :sid AND programme_id = :pid");
            $del->execute([':sid' => $semesterId, ':pid' => $programmeId]);
            setFlash('success', 'Semester removed.');
        }
    }

    header('Location: semesters.php?programme_id=' . $programmeId);
    exit;
}

$flash = getFlash();

$semStmt = $pdo->prepare("
    SELECT s.id, s.number,
           (SELECT COUNT(*) FROM bookings b WHERE b.semester_id = s.id) AS booking_count
    FROM semesters s
    WHERE s.programme_id = :pid
    ORDER BY s.number
");
$semStmt->execute([':pid' => $programmeId]);
$semesters = $semStmt->fetchAll();

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semesters | SESH Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .admin-page { min-height: 100vh; background: #f4f3ef; color: #111; }
        .admin-nav { min-height: 80px; padding: 0 6vw; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #d5d5d0; background: #f4f3ef; }
        .admin-logo { font-size: 28px; font-weight: 900; letter-spacing: -0.08em; color: #111; text-decoration: none; }
        .admin-nav-links a { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: #555; text-decoration: none; }
        .admin-content { width: 90%; max-width: 700px; margin: 0 auto; padding: 60px 0; }
        .page-label { margin-bottom: 12px; font-size: 10px; font-weight: 700; letter-spacing: 0.2em; color: #777; }
        .admin-content h1 { margin: 0 0 10px 0; font-size: clamp(30px, 5vw, 46px); letter-spacing: -0.05em; }
        .flash { margin-top: 20px; padding: 14px 18px; font-size: 13px; }
        .flash-success { background: #111; color: #fff; }
        .flash-error { background: #a33; color: #fff; }
        .add-row { margin-top: 30px; display: flex; gap: 10px; align-items: flex-end; }
        .add-row input { padding: 12px; border: 1px solid #ccc; width: 100px; }
        .btn { display: inline-block; padding: 13px 18px; background: #111; color: #fff; text-decoration: none; font-size: 10px; font-weight: 800; letter-spacing: 0.12em; border: none; cursor: pointer; }
        .sem-list { margin-top: 30px; list-style: none; padding: 0; }
        .sem-list li { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid #ddd; font-size: 14px; }
        .sem-list button { font-size: 10px; font-weight: 700; padding: 6px 10px; border: 1px solid #c9302c; color: #c9302c; background: #fff; cursor: pointer; }
        .muted { color: #999; font-size: 11px; }
    </style>
</head>
<body>
<div class="admin-page">
    <header class="admin-nav">
        <a href="../index.php" class="admin-logo">SESH</a>
        <nav class="admin-nav-links"><a href="index.php">← BACK TO PROGRAMMES</a></nav>
    </header>

    <main class="admin-content">
        <p class="page-label">ACADEMIC SETUP</p>
        <h1><?= htmlspecialchars($programme['name']) ?> — Semesters</h1>

        <?php if (!empty($flash['success'])): ?>
            <div class="flash flash-success"><?= htmlspecialchars($flash['success']) ?></div>
        <?php endif; ?>
        <?php if (!empty($flash['error'])): ?>
            <div class="flash flash-error"><?= htmlspecialchars($flash['error']) ?></div>
        <?php endif; ?>

        <form method="POST" action="semesters.php?programme_id=<?= $programmeId ?>" class="add-row">
            <input type="hidden" name="action" value="add_semester">
            <input type="hidden" name="programme_id" value="<?= $programmeId ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div>
                <label style="display:block;font-size:10px;font-weight:700;margin-bottom:6px;">SEMESTER NUMBER</label>
                <input type="number" name="number" min="1" max="20" required>
            </div>
            <button type="submit" class="btn">+ ADD SEMESTER</button>
        </form>

        <ul class="sem-list">
            <?php if (empty($semesters)): ?>
                <li>No semesters configured for this programme.</li>
            <?php else: ?>
                <?php foreach ($semesters as $s): ?>
                    <li>
                        Semester <?= (int) $s['number'] ?>
                        <?php if ((int) $s['booking_count'] > 0): ?>
                            <span class="muted"><?= (int) $s['booking_count'] ?> booking(s) — cannot delete</span>
                        <?php else: ?>
                            <form method="POST" action="semesters.php?programme_id=<?= $programmeId ?>" onsubmit="return confirm('Remove this semester?');">
                                <input type="hidden" name="action" value="delete_semester">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <input type="hidden" name="programme_id" value="<?= $programmeId ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <button type="submit">REMOVE</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </main>
</div>
</body>
</html>