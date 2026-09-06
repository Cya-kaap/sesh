<?php
/**
 * SESH – LEGACY config.php (DEPRECATED – DO NOT USE)
 *
 * This file belongs to an older, abandoned SRFMS codebase.
 * The live application uses config/db.php for all database
 * and application configuration.
 *
 * DO NOT include this file.
 */

declare(strict_types=1);

http_response_code(500);
header('Content-Type: text/plain; charset=utf-8');
echo "LEGACY config/config.php is deprecated and must not be included.\n";
echo "Use config/db.php instead.\n";
exit;