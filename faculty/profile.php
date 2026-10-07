<?php // filename: faculty/profile.php
require "../config/constants.php";
$required_role = ROLE_FACULTY;
require "../includes/role_check.php";
require "../includes/functions.php";
require "../includes/csrf.php";

$page_title = 'My Profile';
require "../includes/header.php";
?>
<section>
	<h1>My profile.</h1>
	<!-- Photo upload and password changes use separate processors. -->
	<form class="panel profile-photo-form" method="post" action="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/process/profile_photo_process.php" enctype="multipart/form-data">
		<label>
			Profile photo
			<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" required>
		</label>
		<?php csrf_field(); ?>
		<button type="submit">Upload photo</button>
	</form>

	<p class="muted">Change your password if you know the current password. Otherwise send a reset request to an administrator.</p>
	<form class="panel" method="post" action="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/process/password_process.php">
		<label>
			Current password
			<input type="password" name="current_password">
			<small>Required only when changing the password yourself.</small>
		</label>
		<label>
			New password
			<input type="password" name="new_password" minlength="8">
			<small>Use at least 8 characters and two character types.</small>
		</label>
		<?php csrf_field(); ?>
		<button name="action" value="change">Change password</button>
		<button class="button alt" name="action" value="request_reset" onclick="return confirm('Do you want to send a password reset request to the administrator?')">Request reset</button>
	</form>
</section>
<?php require "../includes/footer.php"; ?>
