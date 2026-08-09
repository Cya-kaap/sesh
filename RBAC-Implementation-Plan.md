# RBAC Implementation Plan — Phase 2

## Selected Target File
- **First File to Modify:** `includes/auth_check.php`
- **Dependencies:** `includes/functions.php` (Database connection and base utilities)

---

## Technical Justification

1. **100% Application Surface Coverage**  
   `includes/auth_check.php` is already included at the absolute top of all 32 active protected pages (`admin/`, `coordinator/`, `faculty/`, `modules/`, and `dashboard.php`). Modifying this existing file enforces updated security logic across the entire application instantly, avoiding zero-coverage vulnerabilities during rollout.

2. **Resolves Policy Contradictions**  
   Upgrading `includes/auth_check.php` directly addresses discrepancies between `coordinator/` and `modules/` (e.g., booking approvals) without requiring simultaneous rewrites of isolated controllers.

3. **Identity Normalization**  
   Moving identity extractions inside `currentUser()` within this file prevents individual controllers from reading raw `$_SESSION['role']` parameters.

---

## Action Plan: Refactoring `includes/auth_check.php`

### Step 1: Expand `currentUser()` Context
Extend the session identity object to supply both user ID, role, and department scope:
```php
function currentUser(): array {
    return [
        'id'            => $_SESSION['user_id'] ?? null,
        'role'          => $_SESSION['role'] ?? null,
        'department_id' => $_SESSION['department_id'] ?? null,
    ];
}