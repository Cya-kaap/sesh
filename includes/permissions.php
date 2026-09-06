<?php
/**
 * SESH – Compatibility shim for the old permissions.php
 *
 * This file previously contained a full (but incomplete) RBAC implementation.
 * All permission logic has been moved into includes/auth_check.php, which is
 * now the single source of truth.
 *
 * This file exists only so that any legacy pages that still do:
 *     require_once __DIR__ . '/permissions.php';
 * continue to work without modification.
 *
 * Do NOT add new permission logic here. Extend auth_check.php instead.
 */

declare(strict_types=1);

// Ensure the real implementation is loaded exactly once.
require_once __DIR__ . '/auth_check.php';