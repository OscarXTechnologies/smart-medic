<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

$statement = $conn->prepare(
    'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0'
);

if ($statement) {
    $userId = (int) $_SESSION['user_id'];
    $statement->bind_param('i', $userId);
    $statement->execute();
    $statement->close();
}

header('Location: notifications.php');
exit;