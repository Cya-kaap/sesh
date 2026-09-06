<?php
/**
 * SESH - Legacy conflict_check.php (shim)
 *
 * This file previously contained its own hasBookingConflict() that used
 * lowercase status literals and conflicted with the canonical
 * implementation in includes/functions.php.
 *
 * It is retained only so that any remaining require statements do not
 * produce a fatal error.  All new code must use the version in
 * includes/functions.php and must NOT require this file.
 *
 * @deprecated
 */

require_once __DIR__ . '/../../includes/functions.php';

// Intentionally empty – the real function is already defined.