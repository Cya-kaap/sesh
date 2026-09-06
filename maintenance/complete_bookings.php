<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit('CLI execution required.');
}

// Run this script from Windows Task Scheduler or another trusted scheduler.
require_once __DIR__ . '/../config/db.php';

$updated = $pdo->exec("
    UPDATE bookings
    SET status = 'Completed', updated_at = NOW()
    WHERE status = 'Approved'
      AND (date < CURDATE() OR (date = CURDATE() AND end_time <= CURTIME()))
");

header('Content-Type: text/plain; charset=utf-8');
echo 'Completed bookings: ' . (int) $updated;
