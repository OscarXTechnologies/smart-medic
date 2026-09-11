<?php
session_start();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $sessionCookie = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $sessionCookie['path'],
        $sessionCookie['domain'],
        $sessionCookie['secure'],
        $sessionCookie['httponly']
    );
}

session_destroy();

header('Location: login.php');
exit;
