<?php
// filename: includes/header.php
require "auth_check.php";
global $conn;

$header_flash = get_flash();
$header_photo = profile_photo_url((int) $_SESSION['user_id']);

$header_user_id = (int) $_SESSION['user_id'];
$header_user_result = mysqli_query($conn, "SELECT email FROM user WHERE user_id = $header_user_id");
$header_user = mysqli_fetch_assoc($header_user_result);
$header_email = isset($header_user['email']) ? (string) $header_user['email'] : '';

?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo htmlspecialchars(isset($page_title) ? $page_title : SESH_NAME, ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars(SESH_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
	<link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/css/layout.css?v=10">
	<link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/css/style.css?v=6">
</head>
<body>
	<div class="app">
		<aside><?php include "sidebar.php"; ?></aside>
		<main>
			<header class="topbar">
				<span class="eyebrow">Smart Educational Space Hub</span>

				<!-- The avatar opens the account menu without leaving the current page. -->
				<div class="account-menu">
					<button type="button" id="accountToggle" class="account" onclick="toggleAccountMenu()" title="Open account menu" aria-label="Open account menu">
						<?php if ($header_photo !== null): ?>
							<img class="avatar" src="<?php echo htmlspecialchars($header_photo, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile photo">
						<?php else: ?>
							<span class="avatar avatar-fallback" aria-hidden="true"></span>
						<?php endif; ?>
					</button>

					<div class="account-panel" id="accountPanel" style="display:none;">
						<div class="account-heading">
							<?php if ($header_photo !== null): ?>
								<img class="avatar avatar-large" src="<?php echo htmlspecialchars($header_photo, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile photo">
							<?php else: ?>
								<span class="avatar avatar-fallback avatar-large" aria-hidden="true"></span>
							<?php endif; ?>
							<div>
								<strong><?php echo htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
								<small><?php echo htmlspecialchars($header_email, ENT_QUOTES, 'UTF-8'); ?></small>
							</div>
						</div>

						<button type="button" class="profile-menu-button" onclick="openProfileModal()">
							Profile
						</button>
						<a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/logout.php" class="menu-signout">
							<span aria-hidden="true">&#x21E5;</span> Sign out
						</a>
					</div>
				</div>
			</header>

			<?php if ($header_flash !== null): ?>
				<div class="flash <?php echo htmlspecialchars($header_flash['type'], ENT_QUOTES, 'UTF-8'); ?>">
					<?php echo htmlspecialchars($header_flash['message'], ENT_QUOTES, 'UTF-8'); ?>
				</div>
			<?php endif; ?>

			<div class="profile-modal" id="profileModal" style="display:none;">
				<div class="profile-modal-content">
					<button type="button" class="modal-close" onclick="closeProfileModal()" aria-label="Close profile">&times;</button>
					<div class="profile-modal-heading">
						<?php if ($header_photo !== null): ?>
							<img class="avatar avatar-profile" src="<?php echo htmlspecialchars($header_photo, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile photo">
						<?php else: ?>
							<span class="avatar avatar-fallback avatar-profile" aria-hidden="true"></span>
						<?php endif; ?>
						<div>
							<h2><?php echo htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
							<p><?php echo htmlspecialchars($header_email, ENT_QUOTES, 'UTF-8'); ?></p>
							<p><?php echo htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8'); ?></p>
						</div>
					</div>
					<?php if ($_SESSION['role'] === 'Admin'): ?>
						<a class="button" href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/profile.php">Edit profile</a>
					<?php elseif ($_SESSION['role'] === 'Coordinator'): ?>
						<a class="button" href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/coordinator/profile.php">Edit profile</a>
					<?php else: ?>
						<a class="button" href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/faculty/profile.php">Edit profile</a>
					<?php endif; ?>
				</div>
			</div>