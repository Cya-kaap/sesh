<?php
// filename: admin/users.php
require "../config/constants.php";
$required_role = ROLE_ADMIN;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../includes/functions.php";
require "../includes/csrf.php";

$reset_requests = mysqli_query(
	$conn,
	"SELECT l.created_at, u.user_id, u.full_name, u.email
	 FROM systemlog AS l
	 INNER JOIN user AS u ON u.user_id = l.user_id
	 WHERE l.action = 'Password reset requested'
	 ORDER BY l.created_at DESC
	 LIMIT 20"
);

$users = mysqli_query(
	$conn,
	'SELECT u.user_id, u.full_name, u.email, r.role_name, d.department_name
	 FROM user AS u
	 INNER JOIN role AS r ON r.role_id = u.role_id
	 LEFT JOIN department AS d ON d.department_id = u.department_id
	 ORDER BY u.full_name'
);

$page_title = 'Users';
require "../includes/header.php";
?>
<section>
	<div class="page-head">
		<h1>Users.</h1>
		<a class="button" href="user_form.php">Add user</a>
	</div>

	<div class="panel">
		<h2>Password reset requests</h2>
		<?php if (mysqli_num_rows($reset_requests) > 0): ?>
			<table border="1" cellpadding="5" cellspacing="0">
				<tr>
					<th>Requested</th>
					<th>User</th>
					<th>Email</th>
					<th></th>
				</tr>
				<?php while ($request = mysqli_fetch_assoc($reset_requests)): ?>
					<tr>
						<td><?php echo htmlspecialchars($request['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars($request['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo htmlspecialchars($request['email'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td><a class="button light" href="user_form.php?id=<?php echo (int) $request['user_id']; ?>">Open user</a></td>
					</tr>
				<?php endwhile; ?>
			</table>
		<?php else: ?>
			<p class="muted">No password reset requests found.</p>
		<?php endif; ?>
	</div>

	<h2>All users</h2>
	<table border="1" cellpadding="5" cellspacing="0">
		<tr>
			<th>Name</th>
			<th>Email</th>
			<th>Role</th>
			<th>Department</th>
			<th></th>
		</tr>
		<?php if (mysqli_num_rows($users) > 0): ?>
		<?php while ($user = mysqli_fetch_assoc($users)): ?>
			<tr>
				<td><?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
				<td><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
				<td><?php echo htmlspecialchars($user['role_name'], ENT_QUOTES, 'UTF-8'); ?></td>
				<td><?php echo htmlspecialchars(isset($user['department_name']) ? $user['department_name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
				<td>
					<a class="button light" href="user_form.php?id=<?php echo (int) $user['user_id']; ?>">Edit</a>
					<form class="inline" method="post" action="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/process/user_process.php">
						<?php csrf_field(); ?>
						<input type="hidden" name="action" value="reset_password">
						<input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>">
						<button class="button" onclick="return confirm('Do you want to reset this user\'s password?')">Reset password</button>
					</form>
					<form class="inline" method="post" action="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/process/user_process.php">
						<?php csrf_field(); ?>
						<input type="hidden" name="action" value="delete">
						<input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>">
						<button class="button alt" onclick="return confirm('Delete this user?')">Delete</button>
					</form>
				</td>
			</tr>
		<?php endwhile; ?>
		<?php else: ?>
			<tr><td colspan="5">No users found.</td></tr>
		<?php endif; ?>
	</table>
</section>
<?php include "../includes/footer.php"; ?>
