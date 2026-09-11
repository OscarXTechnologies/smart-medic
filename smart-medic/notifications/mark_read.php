<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

$notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $notificationId !== false && $notificationId !== null) {
    $statement = $conn->prepare(
        'UPDATE notifications
         SET is_read = 1
         WHERE id = ? AND user_id = ?'
    );

    if ($statement) {
        $userId = (int) $_SESSION['user_id'];
        $statement->bind_param('ii', $notificationId, $userId);
        $statement->execute();
        $statement->close();
    }
}

header('Location: notifications.php');
exit;