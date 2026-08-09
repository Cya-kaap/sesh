# Architecture Audit & Security Review

## 1. Current State Assessment

### Authentication Flow & Session Handling
- **Entry Points:** `login.php`, `logout.php`, `index.php` act as open endpoints before authentication.
- **Session Resolution:** Session state is evaluated via `$_SESSION['role']` and `$_SESSION['user_id']`.
- **Session Vulnerabilities:** Direct reads of `$_SESSION` occur across various controller scripts without going through `currentUser()`, creating inconsistency across identity evaluations.

### Protected Surface Analysis
- **Total Protected Pages:** 32 of 32 routable pages across `admin/`, `coordinator/`, `faculty/`, and `modules/` enforce early execution guards.
- **Central Gatekeeper:** `includes/auth_check.php` is included via `require_once` across all 32 protected files prior to business logic execution.
- **Helper Scripts:** `includes/functions.php` and `modules/bookings/conflict_check.php` act as unrouted library dependencies.

### Existing Authorization Primitives
`includes/auth_check.php` exposes three primary primitives:
1. `requireLogin()`: Ensures an active session exists.
2. `requireRole(array $roles)`: Checks if the active session's role exists inside a hardcoded list.
3. `currentUser()`: Retrieves the logged-in user profile from the session.

---

## 2. Identified Vulnerabilities & Architectural Gaps

### Binary Role Checks
`requireRole()` only checks exact string membership inside an array. It lacks native concepts for access tiering such as:
- **Full Access**
- **Read Only**
- **No Access**
- **System Override**
- **Assigned Scope / Department Scope**

### Policy Divergence
Because `requireRole()` does not enforce record-level scoping, individual pages manually attach SQL `WHERE` conditions to restrict access. This led to conflicting policies across modules:
- `coordinator/bookings.php` and `modules/bookings/approve.php` enforce two contradictory rules for the same role and action due to duplicated, un-centralized scoping logic.

### Absence of Default-Deny Fallback
- Scoping relies entirely on opt-in developer discipline per file.
- Unscoped queries execute with full system visibility if custom `WHERE` clauses are omitted.

---

## 3. Target Centralized RBAC Architecture

### Scope Evaluation Order
Every authorization check must pass through a single 5-stage deterministic hierarchy:
1. **Admin Override:** Full system bypass.
2. **Ownership Scope:** User ID comparison against resource creator.
3. **Department Scope:** User department matching target resource department.
4. **Role Permission Matrix:** Evaluation of feature capabilities ($Role \times Feature \rightarrow Level$).
5. **Default Deny:** Rejection if no explicit grant condition is met.

### Unified Gate Chokepoint
Upgrading `includes/auth_check.php` turns it into the system's single source of truth for identity, role capabilities, and query scoping.