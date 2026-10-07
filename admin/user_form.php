<?php // filename: admin/user_form.php
require "../config/constants.php";
$required_role = ROLE_ADMIN;
require "../includes/role_check.php";
require "../config/database.php";
global $conn;
require "../includes/functions.php";
require "../includes/csrf.php";

$user_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$user = ['full_name' => '', 'email' => '', 'role_id' => '', 'department_id' => ''];

// Load the selected user when this page is being used for editing.
if ($user_id > 0) {
	$user_result = mysqli_query(
		$conn,
		"SELECT full_name, email, role_id, department_id FROM user WHERE user_id = $user_id"
	);
	$user = mysqli_fetch_assoc($user_result);
}

$email_username = isset($user['email']) ? $user['email'] : '';
if (str_ends_with($email_username, '@sesh.local')) {
	$email_username = substr($email_username, 0, -strlen('@sesh.local'));
}

$roles = mysqli_query($conn, 'SELECT role_id, role_name FROM role ORDER BY role_id');
$departments = mysqli_query($conn, 'SELECT department_id, department_name FROM department ORDER BY department_name');
$page_title = $user_id > 0 ? 'Edit User' : 'Add User';
require "../includes/header.php";
?>
<section>
	<h1><?php echo $user_id > 0 ? 'Edit' : 'Add'; ?> user.</h1>
	<!-- The same form is used for both adding and editing a user. -->
	<form class="panel" id="userForm" method="post" action="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/process/user_process.php" onsubmit="return validateUserForm()">
		<input type="hidden" name="action" value="save">
		<input type="hidden" id="userId" name="user_id" value="<?php echo (int) $user_id; ?>">
		<label>
			Full name
			<input name="full_name" value="<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
			<small>Example: Rahul Sharma</small>
		</label>

		<label>
			Email username
			<div class="email-input">
				<input name="email_username" value="<?php echo htmlspecialchars($email_username, ENT_QUOTES, 'UTF-8'); ?>" required pattern="[A-Za-z0-9._%+-]+">
				<span>@sesh.local</span>
			</div>
		</label>

		<label>
			Password
			<?php if ($user_id > 0): ?>
				<small>Leave empty to keep the current password.</small>
			<?php endif; ?>
			<div class="password-input">
				<input type="password" id="passwordInput" name="password" <?php echo $user_id > 0 ? '' : 'required'; ?> minlength="8" oninput="checkPasswordStrength()">
				<button type="button" id="passwordToggle" class="button light password-toggle" aria-label="Show password" title="Show password" onclick="togglePasswordVisibility()">
					<svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path>
						<circle cx="12" cy="12" r="3"></circle>
						<line class="eye-slash" x1="3" y1="3" x2="21" y2="21"></line>
					</svg>
				</button>
			</div>
			<span class="password-strength" id="passwordStrength">Enter a password.</span>
			<small>Use at least 8 characters and two character types.</small>
		</label>

		<label>
			Role
			<select name="role_id" required>
				<?php if (mysqli_num_rows($roles) > 0): ?>
				<?php while ($role = mysqli_fetch_assoc($roles)): ?>
					<option value="<?php echo (int) $role['role_id']; ?>" <?php echo $user['role_id'] == $role['role_id'] ? 'selected' : ''; ?>>
						<?php echo htmlspecialchars($role['role_name'], ENT_QUOTES, 'UTF-8'); ?>
					</option>
				<?php endwhile; ?>
				<?php else: ?>
				<option disabled>No roles found</option>
				<?php endif; ?>
			</select>
		</label>

		<label>
			Department
			<select name="department_id">
				<option value="">None</option>
				<?php if (mysqli_num_rows($departments) > 0): ?>
				<?php while ($department = mysqli_fetch_assoc($departments)): ?>
					<option value="<?php echo (int) $department['department_id']; ?>" <?php echo $user['department_id'] == $department['department_id'] ? 'selected' : ''; ?>>
						<?php echo htmlspecialchars($department['department_name'], ENT_QUOTES, 'UTF-8'); ?>
					</option>
				<?php endwhile; ?>
				<?php else: ?>
				<option disabled>No departments found</option>
				<?php endif; ?>
			</select>
		</label>

		<?php csrf_field(); ?>
		<button type="submit">Save user</button>
	</form>
</section>
<?php require "../includes/footer.php"; ?>
