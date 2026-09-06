<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole([ROLE_ADMIN]);
$user = currentUser();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'settings') {
                $settings = [
                    'max_duration_minutes' => (int) ($_POST['max_duration_minutes'] ?? 0),
                    'advance_days' => (int) ($_POST['advance_days'] ?? 0),
                    'approval_required' => ($_POST['approval_required'] ?? '1') === '1' ? '1' : '0',
                ];
                if ($settings['max_duration_minutes'] < 15 || $settings['max_duration_minutes'] > 1440) throw new RuntimeException('Maximum duration must be between 15 minutes and 24 hours.');
                if ($settings['advance_days'] < 0 || $settings['advance_days'] > 365) throw new RuntimeException('Advance booking days must be between 0 and 365.');
                $stmt = $pdo->prepare('INSERT INTO booking_settings (setting_key, setting_value, updated_by) VALUES (:key, :value, :actor) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)');
                foreach ($settings as $key => $value) $stmt->execute([':key' => $key, ':value' => (string) $value, ':actor' => $user['id']]);
                setFlash('success', 'Booking rules updated.');
            } elseif ($action === 'blackout') {
                $starts = trim($_POST['starts_at'] ?? '');
                $ends = trim($_POST['ends_at'] ?? '');
                $reason = trim($_POST['reason'] ?? '');
                if ($starts === '' || $ends === '' || $reason === '' || strtotime($ends) <= strtotime($starts)) throw new RuntimeException('Provide a valid blackout window and reason.');
                $stmt = $pdo->prepare('INSERT INTO booking_blackouts (starts_at, ends_at, reason, created_by) VALUES (:starts, :ends, :reason, :actor)');
                $stmt->execute([':starts' => str_replace('T', ' ', $starts) . ':00', ':ends' => str_replace('T', ' ', $ends) . ':00', ':reason' => $reason, ':actor' => $user['id']]);
                setFlash('success', 'Blackout period added.');
            } elseif ($action === 'delete_blackout') {
                $stmt = $pdo->prepare('DELETE FROM booking_blackouts WHERE id = :id');
                $stmt->execute([':id' => (int) ($_POST['blackout_id'] ?? 0)]);
                setFlash('success', 'Blackout period removed.');
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$maxDuration = getBookingSetting($pdo, 'max_duration_minutes', '180');
$advanceDays = getBookingSetting($pdo, 'advance_days', '90');
$approvalRequired = getBookingSetting($pdo, 'approval_required', '1');
$blackouts = $pdo->query('SELECT id, starts_at, ends_at, reason FROM booking_blackouts ORDER BY starts_at')->fetchAll();
$flash = getFlash();
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Booking Rules | SESH</title><link rel="stylesheet" href="../../assets/css/style.css"><style>.page{width:90%;max-width:1000px;margin:auto;padding:50px 0}.panel{background:#fff;border:1px solid #ddd;padding:24px;margin:20px 0}.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}.form-grid label{font-size:10px;font-weight:700;letter-spacing:.1em}.form-grid input,.form-grid select{width:100%;padding:11px;border:1px solid #ccc}.btn{padding:12px 18px;background:#111;color:#fff;border:0}.flash{padding:14px;margin:15px 0}.flash-success{background:#d4edda}.flash-error{background:#f8d7da}.blackout{display:flex;justify-content:space-between;border-bottom:1px solid #ddd;padding:12px 0;gap:12px}</style></head><body><main class="page"><p><a href="../index.php">&larr; Admin Dashboard</a></p><h1>Booking Rules</h1><?php foreach ($errors as $error): ?><div class="flash flash-error"><?= htmlspecialchars($error) ?></div><?php endforeach; ?><?php if (!empty($flash['success'])): ?><div class="flash flash-success"><?= htmlspecialchars($flash['success']) ?></div><?php endif; ?><section class="panel"><h2>Booking policy</h2><form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="settings"><div class="form-grid"><label>Maximum duration (minutes)<input type="number" name="max_duration_minutes" min="15" max="1440" value="<?= htmlspecialchars($maxDuration) ?>"></label><label>Advance booking days<input type="number" name="advance_days" min="0" max="365" value="<?= htmlspecialchars($advanceDays) ?>"></label><label>Approval required<select name="approval_required"><option value="1" <?= $approvalRequired === '1' ? 'selected' : '' ?>>Yes</option><option value="0" <?= $approvalRequired === '0' ? 'selected' : '' ?>>No</option></select></label></div><p><button class="btn">SAVE RULES</button></p></form></section><section class="panel"><h2>Add blackout period</h2><form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="blackout"><div class="form-grid"><label>Starts<input type="datetime-local" name="starts_at" required></label><label>Ends<input type="datetime-local" name="ends_at" required></label><label>Reason<input name="reason" maxlength="255" required></label></div><p><button class="btn">ADD BLACKOUT</button></p></form></section><section class="panel"><h2>Blackout periods</h2><?php if (!$blackouts): ?><p>No blackout periods configured.</p><?php else: foreach ($blackouts as $blackout): ?><div class="blackout"><span><?= htmlspecialchars($blackout['starts_at'] . ' - ' . $blackout['ends_at']) ?><br><?= htmlspecialchars($blackout['reason']) ?></span><form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="delete_blackout"><input type="hidden" name="blackout_id" value="<?= (int) $blackout['id'] ?>"><button class="btn">REMOVE</button></form></div><?php endforeach; endif; ?></section></main></body></html>
