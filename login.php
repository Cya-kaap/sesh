<?php // filename: login.php

/*
 * Login is a public page, so it deliberately does not include auth_check.php
 * or sidebar.php. Including either would make an unauthenticated login page
 * depend on protected navigation and could create a redirect loop.
 */
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
require "config/constants.php";
require "includes/functions.php";
require "includes/csrf.php";

$flash_message = get_flash();
$csrf_token = generate_csrf_token();
$login_display = $flash_message !== null ? 'grid' : 'none';
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?> | College Space Access</title>
	<link rel="stylesheet" href="/sesh/css/login.css?v=9">
</head>
<body>
	<main class="landing-page" id="top">
		<header class="landing-nav">
			<a class="landing-brand" href="/sesh/login.php#top" aria-label="SESH home">
				<strong><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></strong>
			</a>
			<a class="landing-about" href="#about">About SESH</a>
			<button class="nav-sign-in" type="button" onclick="openLoginModal()">Sign In</button>
		</header>

		<section class="hero-content">
			<p class="hero-kicker">College space access</p>
			<h1>SESH</h1>
			<p class="hero-copy">Find and book the spaces your college needs.</p>
			<button class="enter-button" type="button" onclick="openLoginModal()">Enter Now</button>
		</section>
	</main>

	<section class="about-section" id="about">
		<div class="about-layout">
			<div class="about-intro">
				<p class="about-kicker">About SESH</p>
				<h2>Campus spaces, easier to use.</h2>
			</div>
			<div class="about-copy">
				<p>SESH helps colleges organize shared learning spaces. Students can find available rooms and request bookings, while coordinators review requests and administrators manage campus resources.</p>
				<div class="about-points">
					<div><span>01</span><h3>Find a room</h3><p>Check room availability and campus facilities.</p></div>
					<div><span>02</span><h3>Request a booking</h3><p>Submit a room request for your class or activity.</p></div>
					<div><span>03</span><h3>Manage campus use</h3><p>Review schedules and coordinate shared spaces.</p></div>
				</div>
			</div>
		</div>
	</section>

	<div class="login-overlay" id="loginModal" role="dialog" aria-modal="true" aria-labelledby="loginTitle" style="display:<?php echo $login_display; ?>;">
		<section class="login-panel">
			<div class="login-brand"><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></div>
			<p class="kicker">College account access</p>
			<h2 id="loginTitle">Sign in</h2>

			<?php if ($flash_message !== null): ?>
				<div class="flash <?php echo htmlspecialchars($flash_message['type'], ENT_QUOTES, 'UTF-8'); ?>">
					<?php echo htmlspecialchars($flash_message['message'], ENT_QUOTES, 'UTF-8'); ?>
				</div>
			<?php endif; ?>

			<form method="post" action="/sesh/process/login_process.php">
				<label>
					Email
					<input type="email" id="loginEmail" name="email" autocomplete="username" required>
				</label>
				<label>
					Password
					<div class="password-field">
						<input type="password" id="passwordInput" name="password" autocomplete="current-password" required>
						<button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password" title="Show password" onclick="togglePasswordVisibility()">
							<svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
								<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path>
								<circle cx="12" cy="12" r="3"></circle>
								<line class="eye-slash" x1="3" y1="3" x2="21" y2="21"></line>
							</svg>
						</button>
					</div>
				</label>
				<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
				<button class="sign-in-submit" type="submit">Sign in</button>
			</form>
			<button class="login-close" type="button" aria-label="Close sign in" onclick="closeLoginModal()">&times;</button>
		</section>
	</div>
	<script src="/sesh/js/script.js?v=9"></script>
</body>
</html>
