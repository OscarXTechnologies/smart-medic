<?php
session_start();
require_once __DIR__ . '/includes/config.php';

$errors = [];
$successMessage = '';

$formData = [
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'username' => '',
    'email' => '',
    'phone_number' => '',
    'date_of_birth' => '',
    'gender' => '',
    'role' => 'patient'
];

$allowedRoles = [
    'patient',
    'caregiver',
    'health_worker',
    'doctor'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $field => $value) {
        $formData[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($formData['first_name'] === '') {
        $errors[] = 'First name is required.';
    }

    if ($formData['last_name'] === '') {
        $errors[] = 'Last name is required.';
    }

    if ($formData['username'] === '') {
        $errors[] = 'Username is required.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $formData['username'])) {
        $errors[] = 'Username must be 3 to 50 characters and use only letters, numbers, dots, underscores, or hyphens.';
    }

    if ($formData['email'] === '' || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if ($formData['phone_number'] !== '' && !preg_match('/^[0-9+() .-]{7,25}$/', $formData['phone_number'])) {
        $errors[] = 'Enter a valid phone number.';
    }

    if ($formData['date_of_birth'] !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $formData['date_of_birth']);
        $dateIsValid = $date && $date->format('Y-m-d') === $formData['date_of_birth'];
        if (!$dateIsValid || $formData['date_of_birth'] > date('Y-m-d')) {
            $errors[] = 'Enter a valid date of birth.';
        }
    }

    $allowedGenders = ['female', 'male', 'other', 'prefer_not_to_say'];
    if ($formData['gender'] !== '' && !in_array($formData['gender'], $allowedGenders, true)) {
        $errors[] = 'Select a valid gender option.';
    }

    if (!in_array($formData['role'], $allowedRoles, true)) {
        $errors[] = 'Select a valid user role.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password and confirm password must match.';
    }

    if (!$errors) {
        $duplicateCheck = $conn->prepare('SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1');

        if (!$duplicateCheck) {
            $errors[] = 'Registration could not be completed. Please try again later.';
        } else {
            $duplicateCheck->bind_param('ss', $formData['username'], $formData['email']);
            $duplicateCheck->execute();
            $duplicateResult = $duplicateCheck->get_result();
            $existingUser = $duplicateResult->fetch_assoc();
            $duplicateCheck->close();

            if ($existingUser) {
                if (strcasecmp($existingUser['username'], $formData['username']) === 0) {
                    $errors[] = 'That username is already registered.';
                }

                if (strcasecmp($existingUser['email'], $formData['email']) === 0) {
                    $errors[] = 'That email address is already registered.';
                }
            }
        }
    }

    if (!$errors) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $insert = $conn->prepare(
            'INSERT INTO users (username, email, first_name, middle_name, last_name, password, role)
			 VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$insert) {
            $errors[] = 'Registration could not be completed. Please try again later.';
        } else {
            $insert->bind_param(
                'sssssss',
                $formData['username'],
                $formData['email'],
                $formData['first_name'],
                $formData['middle_name'],
                $formData['last_name'],
                $hashedPassword,
                $formData['role']
            );

            if ($insert->execute()) {
                $successMessage = 'Your Smart Medic account has been created successfully.';
                $formData = array_fill_keys(array_keys($formData), '');
            } elseif ($insert->errno === 1062) {
                $errors[] = 'That username or email address is already registered.';
            } else {
                $errors[] = 'Registration could not be completed. Please try again later.';
            }

            $insert->close();
        }
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create account | Smart Medic</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <main class="auth-page">
        <section class="auth-card" aria-labelledby="registration-title">
            <header class="auth-header">
                <p class="eyebrow">Smart Medic</p>
                <h1 id="registration-title">Create your account</h1>
                <p>Join a connected healthcare community.</p>
            </header>

            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success" role="status">
                    <?= e($successMessage) ?>
                    <a href="login.php">Continue to login</a>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-error" role="alert">
                    <p>Please correct the following:</p>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($successMessage === ''): ?>
                <form method="post" action="register.php" class="registration-form" novalidate>
                    <p class="required-note"><span aria-hidden="true">*</span> Required field</p>

                    <fieldset>
                        <legend>Personal details</legend>
                        <div class="form-grid">
                            <label for="first_name">First name <span aria-hidden="true">*</span></label>
                            <input type="text" id="first_name" name="first_name" value="<?= e($formData['first_name']) ?>" maxlength="100" autocomplete="given-name" required>

                            <label for="middle_name">Middle name</label>
                            <input type="text" id="middle_name" name="middle_name" value="<?= e($formData['middle_name']) ?>" maxlength="100" autocomplete="additional-name">

                            <label for="last_name">Last name <span aria-hidden="true">*</span></label>
                            <input type="text" id="last_name" name="last_name" value="<?= e($formData['last_name']) ?>" maxlength="100" autocomplete="family-name" required>

                            <label for="phone_number">Phone number</label>
                            <input type="tel" id="phone_number" name="phone_number" value="<?= e($formData['phone_number']) ?>" maxlength="25" autocomplete="tel">

                            <label for="date_of_birth">Date of birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" value="<?= e($formData['date_of_birth']) ?>" autocomplete="bday">

                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">Prefer not to say</option>
                                <option value="female" <?= $formData['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                                <option value="male" <?= $formData['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                                <option value="other" <?= $formData['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Account details</legend>
                        <div class="form-grid">
                            <label for="username">Username <span aria-hidden="true">*</span></label>
                            <input type="text" id="username" name="username" value="<?= e($formData['username']) ?>" maxlength="50" autocomplete="username" required>

                            <label for="email">Email address <span aria-hidden="true">*</span></label>
                            <input type="email" id="email" name="email" value="<?= e($formData['email']) ?>" maxlength="255" autocomplete="email" required>

                            <label for="role">User role <span aria-hidden="true">*</span></label>
                            <select id="role" name="role" required>
                                <option value="patient" <?= $formData['role'] === 'patient' ? 'selected' : '' ?>>Patient</option>
                                <option value="caregiver" <?= $formData['role'] === 'caregiver' ? 'selected' : '' ?>>Caregiver</option>
                                <option value="health_worker" <?= $formData['role'] === 'health_worker' ? 'selected' : '' ?>>Community Health Worker</option>
                                <option value="doctor" <?= $formData['role'] === 'doctor' ? 'selected' : '' ?>>Doctor / Healthcare Worker</option>
                            </select>

                            <label for="password">Password <span aria-hidden="true">*</span></label>
                            <div class="password-field">
                                <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required>
                                <button type="button" class="password-toggle" data-password-target="password" aria-label="Show password">Show</button>
                            </div>
                            <div class="password-strength" aria-live="polite">
                                <div class="strength-meter"><span id="strength-bar"></span></div>
                                <span id="strength-text">Use at least 8 characters.</span>
                            </div>

                            <label for="confirm_password">Confirm password <span aria-hidden="true">*</span></label>
                            <div class="password-field">
                                <input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                                <button type="button" class="password-toggle" data-password-target="confirm_password" aria-label="Show password">Show</button>
                            </div>
                            <p id="password-match" class="password-match" aria-live="polite"></p>
                        </div>
                    </fieldset>

                    <button type="submit" class="button-primary">Create account</button>
                </form>
            <?php endif; ?>

            <p class="auth-footer">Already have an account? <a href="login.php">Log in</a></p>
        </section>
    </main>
    <script src="js/main.js"></script>
</body>

</html>