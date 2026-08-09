<?php

/**
 * SESH - Admin: Academic Setup (Programmes)
 *
 * Purpose:
 *   CRUD for Programmes. Creating a Programme auto-seeds 8 semesters
 *   (matching the convention in database/seed.sql); individual
 *   semesters can then be added/removed per-programme on semesters.php
 *   if a programme needs a different count.
 *
 * Requires tables:
 *   - programmes, semesters, users, bookings
 *
 * Access:
 *   Admin only.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['Admin']);

$flash = getFlash();

/*
|--------------------------------------------------------------------------
| Create Programme
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_programme') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        setFlash('error', 'Your session expired. Please try again.');

    } else {

        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            setFlash('error', 'Programme name is required.');
        } elseif (mb_strlen($name) > 100) {
            setFlash('error', 'Programme name must be 100 characters or fewer.');
        } else {

            $dup = $pdo->prepare("SELECT id FROM programmes WHERE name = :name");
            $dup->execute([':name' => $name]);

            if ($dup->fetch()) {
                setFlash('error', 'A programme with this name already exists.');
            } else {

                try {
                    $pdo->beginTransaction();

                    $insert = $pdo->prepare("INSERT INTO programmes (name) VALUES (:name)");
                    $insert->execute([':name' => $name]);
                    $newProgrammeId = (int) $pdo->lastInsertId();

                    $semInsert = $pdo->prepare("INSERT INTO semesters (programme_id, number) VALUES (:pid, :num)");
                    for ($i = 1; $i <= 8; $i++) {
                        $semInsert->execute([':pid' => $newProgrammeId, ':num' => $i]);
                    }

                    $pdo->commit();
                    setFlash('success', 'Programme "' . $name . '" created with 8 semesters.');

                } catch (PDOException $e) {
                    $pdo->rollBack();
                    setFlash('error', 'Unable to create programme. Please try again.');
                }
            }
        }
    }

    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Delete Programme
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_programme') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        setFlash('error', 'Your session expired. Please try again.');

    } else {

        $programmeId = (int) ($_POST['id'] ?? 0);

        $userCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE programme_id = :pid");
        $userCheck->execute([':pid' => $programmeId]);

        $bookingCheck = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE programme_id = :pid");
        $bookingCheck->execute([':pid' => $programmeId]);

        if ((int) $userCheck->fetchColumn() > 0) {
            setFlash('error', 'Cannot delete: one or more Coordinators are assigned to this programme. Reassign them first.');
        } elseif ((int) $bookingCheck->fetchColumn() > 0) {
            setFlash('error', 'Cannot delete: this programme has existing bookings on record.');
        } else {

            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM semesters WHERE programme_id = :pid")->execute([':pid' => $programmeId]);
                $pdo->prepare("DELETE FROM programmes WHERE id = :pid")->execute([':pid' => $programmeId]);
                $pdo->commit();
                setFlash('success', 'Programme deleted.');
            } catch (PDOException $e) {
                $pdo->rollBack();
                setFlash('error', 'Unable to delete this programme — it may still be referenced elsewhere.');
            }
        }
    }

    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Programmes
|--------------------------------------------------------------------------
*/

$programmes = $pdo->query("
    SELECT
        p.id, p.name,
        (SELECT COUNT(*) FROM semesters s WHERE s.programme_id = p.id) AS semester_count,
        (SELECT COUNT(*) FROM users u WHERE u.programme_id = p.id) AS coordinator_count,
        (SELECT COUNT(*) FROM bookings b WHERE b.programme_id = p.id) AS booking_count
    FROM programmes p
    ORDER BY p.name
")->fetchAll();

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Setup | SESH Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .admin-page { min-height: 100vh; background: #f4f3ef; color: #111; }
        .admin-nav { min-height: 80px; padding: 0 6vw; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #d5d5d0; background: #f4f3ef; }
        .admin-logo { font-size: 28px; font-weight: 900; letter-spacing: -0.08em; color: #111; text-decoration: none; }
        .admin-nav-links a { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; color: #555; text-decoration: none; }
        .admin-content { width: 92%; max-width: 1100px; margin: 0 auto; padding: 60px 0; }
        .page-label { margin-bottom: 12px; font-size: 10px; font-weight: 700; letter-spacing: 0.2em; color: #777; }
        .admin-content h1 { margin: 0 0 10px 0; font-size: clamp(36px, 5vw, 56px); letter-spacing: -0.05em; }

        .flash { margin-top: 30px; padding: 14px 18px; font-size: 13px; }
        .flash-success { background: #111; color: #fff; }
        .flash-error { background: #a33; color: #fff; }

        .create-card { margin-top: 40px; padding: 25px; background: #fff; border: 1px solid #ddd; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .create-card label { display: block; font-size: 10px; font-weight: 700; letter-spacing: 0.1em; margin-bottom: 8px; }
        .create-card input { padding: 12px; border: 1px solid #ccc; font-size: 14px; min-width: 260px; }
        .btn { display: inline-block; padding: 13px 20px; background: #111; color: #fff; text-decoration: none; font-size: 10px; font-weight: 800; letter-spacing: 0.12em; border: none; cursor: pointer; }
        .btn:hover { background: #333; }
        .btn-danger { background: #fff; border: 1px solid #c9302c; color: #c9302c; }

        .data-table { width: 100%; border-collapse: collapse; margin-top: 40px; }
        .data-table th, .data-table td { padding: 16px 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 13px; }
        .data-table th { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; color: #777; background: #eae8e1; }
        .table-actions { display: flex; gap: 10px; align-items: center; }
        .table-actions a { font-size: 11px; font-weight: 700; text-decoration: none; color: #111; border-bottom: 1px solid #111; }
        .table-actions form { display: inline; }
        .table-actions button { font-size: 10px; font-weight: 700; padding: 6px 10px; border: none; background: none; cursor: pointer; }
        .muted { color: #999; font-size: 11px; }
    </style>
</head>
<body>
<div class="admin-page">

    <header class="admin-nav">
        <a href="../index.php" class="admin-logo">SESH</a>
        <nav class="admin-nav-links">
            <a href="../index.php">DASHBOARD</a>
            <a href="../../logout.php" class="logout">LOGOUT</a>
        </nav>
    </header>

    <main class="admin-content">

        <p class="page-label">ACADEMIC SETUP</p>
        <h1>Programmes</h1>

        <?php if (!empty($flash['success'])): ?>
            <div class="flash flash-success"><?= htmlspecialchars($flash['success']) ?></div>
        <?php endif; ?>
        <?php if (!empty($flash['error'])): ?>
            <div class="flash flash-error"><?= htmlspecialchars($flash['error']) ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php" class="create-card">
            <input type="hidden" name="action" value="create_programme">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div>
                <label for="name">New Programme Name</label>
                <input type="text" id="name" name="name" maxlength="100" required placeholder="e.g. BSc CSIT">
            </div>
            <button type="submit" class="btn">+ CREATE (WITH 8 SEMESTERS)</button>
        </form>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Programme</th>
                    <th>Semesters</th>
                    <th>Coordinators</th>
                    <th>Bookings</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($programmes)): ?>
                    <tr><td colspan="5">No programmes configured yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($programmes as $p): ?>
                        <?php $canDelete = (int) $p['coordinator_count'] === 0 && (int) $p['booking_count'] === 0; ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                            <td><?= (int) $p['semester_count'] ?></td>
                            <td><?= (int) $p['coordinator_count'] ?></td>
                            <td><?= (int) $p['booking_count'] ?></td>
                            <td class="table-actions">
                                <a href="semesters.php?programme_id=<?= (int) $p['id'] ?>">SEMESTERS</a>
                                <?php if ($canDelete): ?>
                                    <form method="POST" action="index.php" onsubmit="return confirm('Delete this programme? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete_programme">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <button type="submit" class="btn-danger" style="border:none;background:none;color:#c9302c;cursor:pointer;font-weight:700;">DELETE</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">In use — cannot delete</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    </main>

</div>
</body>
</html>