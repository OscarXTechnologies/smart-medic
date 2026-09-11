<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'])
        && is_numeric($_SESSION['user_id'])
        && (int) $_SESSION['user_id'] > 0;
}

function requireLogin(): void
{
    if (isLoggedIn()) {
        return;
    }

    header('Location: /smart-medic/login.php');
    exit;
}
