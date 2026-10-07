// filename: js/script.js

function togglePasswordVisibility()
{
	var passwordInput = document.getElementById('passwordInput');
	var toggleButton = document.getElementById('passwordToggle');

	if (passwordInput.type === 'password') {
		passwordInput.type = 'text';
		toggleButton.classList.add('password-visible');
		toggleButton.setAttribute('aria-label', 'Hide password');
		toggleButton.setAttribute('title', 'Hide password');
	} else {
		passwordInput.type = 'password';
		toggleButton.classList.remove('password-visible');
		toggleButton.setAttribute('aria-label', 'Show password');
		toggleButton.setAttribute('title', 'Show password');
	}
}

function checkPasswordStrength()
{
	var passwordInput = document.getElementById('passwordInput');
	var strengthMessage = document.getElementById('passwordStrength');

	if (passwordInput.value.length < 8) {
		strengthMessage.textContent = 'Password must be at least 8 characters.';
		strengthMessage.style.color = 'red';
	} else {
		strengthMessage.textContent = 'Minimum length met; use two character types.';
		strengthMessage.style.color = 'green';
	}
}

function validateUserForm()
{
	var passwordInput = document.getElementById('passwordInput');
	var userIdInput = document.getElementById('userId');
	var editingUser = userIdInput.value !== '0';

	if (editingUser && passwordInput.value === '') {
		return true;
	}

	if (passwordInput.value.length < 8) {
		window.alert('Password must be at least 8 characters.');
		return false;
	}

	return true;
}

function openProfileModal()
{
	document.getElementById('profileModal').style.display = 'block';
}

function closeProfileModal()
{
	document.getElementById('profileModal').style.display = 'none';
}

function toggleAccountMenu()
{
	var accountPanel = document.getElementById('accountPanel');
	if (accountPanel.style.display === 'block') {
		accountPanel.style.display = 'none';
	} else {
		accountPanel.style.display = 'block';
	}
}

function openLoginModal()
{
	var loginModal = document.getElementById('loginModal');
	loginModal.style.display = 'grid';
	document.getElementById('loginEmail').focus();
}

function closeLoginModal()
{
	document.getElementById('loginModal').style.display = 'none';
}
