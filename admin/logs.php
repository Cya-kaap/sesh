<?php // filename: admin/logs.php

/*
 * Audit logs are an immutable read-only trail. Admin review can filter them,
 * but this page intentionally has no delete form or log-mutating processor.
 */
require "../includes/auth_check.php";
$required_role = ROLE_ADMIN;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../includes/functions.php";

$search = trim(isset($_GET['search']) ? (string) $_GET['search'] : '');

// Search the audit trail by action, details, or user name.
$escaped_search = db_escape($conn, $search);
$query = "SELECT l.created_at, l.action, l.description, u.full_name
	 FROM systemlog AS l
	 LEFT JOIN user AS u ON u.user_id = l.user_id
	 WHERE 1=1";
if ($search !== '') {
	$query .= " AND (l.action LIKE '%$escaped_search%'
		OR l.description LIKE '%$escaped_search%'
		OR u.full_name LIKE '%$escaped_search%')";
}
$query .= ' ORDER BY l.created_at DESC';
$logs = mysqli_query($conn, $query);

$page_title = 'System Logs';
require "../includes/header.php";
?>
<section>
	<div class="page-head">
		<div>
			<h1>System logs.</h1>
			<p class="muted">Immutable audit history for administrative review.</p>
		</div>
	</div>

	<form class="searchbar" method="get">
		<label>Search action, details, or user
			<input type="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
		</label>
		<button type="submit">Search</button>
	</form>

	<div class="table-wrap">
		<table border="1" cellpadding="5" cellspacing="0">
			<tr>
				<th>Time</th>
				<th>User</th>
				<th>Action</th>
				<th>Details</th>
			</tr>

			<?php if (mysqli_num_rows($logs) > 0): ?>
			<?php while ($log = mysqli_fetch_assoc($logs)): ?>
				<tr>
					<td><?php echo htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars(isset($log['full_name']) ? $log['full_name'] : 'System', ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars($log['description'], ENT_QUOTES, 'UTF-8'); ?></td>
				</tr>
			<?php endwhile; ?>
			<?php else: ?>
				<tr><td colspan="4">No log records found.</td></tr>
			<?php endif; ?>
		</table>
	</div>
</section>
<?php require "../includes/footer.php"; ?>
