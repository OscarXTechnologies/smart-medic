document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-password-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            var passwordInput = document.getElementById(button.dataset.passwordTarget);
            var isPassword = passwordInput.type === 'password';

            passwordInput.type = isPassword ? 'text' : 'password';
            button.textContent = isPassword ? 'Hide' : 'Show';
            button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    });

    var passwordInput = document.getElementById('password');
    var confirmPasswordInput = document.getElementById('confirm_password');
    var strengthBar = document.getElementById('strength-bar');
    var strengthText = document.getElementById('strength-text');

    if (!passwordInput || !confirmPasswordInput || !strengthBar || !strengthText) {
        return;
    }

    var passwordMatch = document.getElementById('password-match');
    var registrationForm = document.querySelector('.registration-form');

    function updatePasswordMatch() {
        if (confirmPasswordInput.value === '') {
            passwordMatch.textContent = '';
            passwordMatch.className = 'password-match';
            return true;
        }

        var passwordsMatch = passwordInput.value === confirmPasswordInput.value;
        passwordMatch.textContent = passwordsMatch ? 'Passwords match.' : 'Passwords do not match.';
        passwordMatch.className = passwordsMatch ? 'password-match is-valid' : 'password-match is-invalid';
        return passwordsMatch;
    }

    passwordInput.addEventListener('input', function () {
        var password = passwordInput.value;
        var score = 0;

        if (password.length >= 8) {
            score += 1;
        }
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) {
            score += 1;
        }
        if (/\d/.test(password)) {
            score += 1;
        }
        if (/[^A-Za-z0-9]/.test(password)) {
            score += 1;
        }

        var widths = ['0%', '25%', '50%', '75%', '100%'];
        var colors = ['#d17a35', '#d17a35', '#c39b2f', '#19704b', '#19704b'];
        var labels = ['Use at least 8 characters.', 'Weak password', 'Fair password', 'Good password', 'Strong password'];

        strengthBar.style.width = widths[score];
        strengthBar.style.backgroundColor = colors[score];
        strengthText.textContent = labels[score];
        updatePasswordMatch();
    });

    confirmPasswordInput.addEventListener('input', updatePasswordMatch);

    if (registrationForm) {
        registrationForm.addEventListener('submit', function (event) {
            if (!updatePasswordMatch()) {
                event.preventDefault();
                confirmPasswordInput.focus();
            }
        });
    }
});
