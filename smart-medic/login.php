<?php
session_start();
require_once __DIR__ . '/includes/config.php';

$errorMessage = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errorMessage = 'Enter your email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Invalid email or password.';
    } else {
        $statement = $conn->prepare('SELECT id, username, role, password FROM users WHERE email = ? LIMIT 1');

        if ($statement) {
            $statement->bind_param('s', $email);
            $statement->execute();
            $result = $statement->get_result();
            $user = $result->fetch_assoc();
            $statement->close();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                header('Location: dashboard.php');
                exit;
            }
        }

        $errorMessage = 'Invalid email or password.';
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
    <title>Log in | Smart Medic</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <main class="auth-page login-page">
        <section class="login-layout" aria-label="Smart Medic login">
            <div class="login-visual" aria-hidden="true">
                <div class="visual-copy">
                    <p class="eyebrow">Smart Medic</p>
                    <h1>Care that stays connected.</h1>
                    <p>Bring your health conversations, support, and next steps into one trusted space.</p>
                </div>
                <div class="health-illustration">
                    <span class="illustration-circle"></span>
                    <span class="illustration-cross"></span>
                    <span class="illustration-heart">+</span>
                    <span class="illustration-line"></span>
                </div>
            </div>

            <section class="auth-card login-card" aria-labelledby="login-title">
                <header class="auth-header">
                    <p class="eyebrow">Welcome back</p>
                    <h2 id="login-title">Log in to Smart Medic</h2>
                    <p>Access your healthcare community.</p>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <div class="alert alert-error" role="alert"><?= e($errorMessage) ?></div>
                <?php endif; ?>

                <form method="post" action="login.php" class="login-form">
                    <label for="email">Email <span aria-hidden="true">*</span></label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus placeholder=">

                    <label for=" login-password">Password <span aria-hidden="true">*</span></label>
                    <div class="password-field">
                        <input type="password" id="login-password" name="password" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" data-password-target="login-password" aria-label="Show password">Show</button>
                    </div>

                    
                    <div class="login-options">
                        <label class="remember-option" for="remember-me">
                            <input type="checkbox" id="remember-me" name="remember_me" value="1">
                            <span>Remember me</span>
                        </label>
                        <a href="#">Forgot password?</a>
                    </div>

                    <button type="submit" class="button-primary">Log in</button>
                </form>

                <p class="auth-footer">New to Smart Medic? <a href="register.php">Create an account</a></p>
            </section>
        </section>
    </main>
    <script src="js/main.js"></script>
</body>

</html>