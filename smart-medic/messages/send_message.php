<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

$senderId = (int) $_SESSION['user_id'];
$receiverId = filter_input(INPUT_POST, 'receiver_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$message = trim((string) ($_POST['message'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $receiverId === false || $receiverId === null || $receiverId === $senderId || $message === '' || strlen($message) > 2000) {
    header('Location: messages.php');
    exit;
}

$receiverCheck = $conn->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
if (!$receiverCheck) {
    header('Location: messages.php');
    exit;
}

$receiverCheck->bind_param('i', $receiverId);
$receiverCheck->execute();
$receiver = $receiverCheck->get_result()->fetch_assoc();
$receiverCheck->close();

if (!$receiver) {
    header('Location: messages.php');
    exit;
}

$statement = $conn->prepare('INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)');
if ($statement) {
    $statement->bind_param('iis', $senderId, $receiverId, $message);
    $messageSaved = $statement->execute();
    $statement->close();

    if ($messageSaved) {
        $senderName = $_SESSION['username'] ?? 'A Smart Medic user';
        $senderStatement = $conn->prepare('SELECT first_name, last_name, username FROM users WHERE id = ? LIMIT 1');
        if ($senderStatement) {
            $senderStatement->bind_param('i', $senderId);
            $senderStatement->execute();
            $sender = $senderStatement->get_result()->fetch_assoc();
            $senderStatement->close();
            if ($sender) {
                $senderName = trim($sender['first_name'] . ' ' . $sender['last_name']);
                if ($senderName === '') {
                    $senderName = $sender['username'];
                }
            }
        }

        $notificationStatement = $conn->prepare(
            'INSERT INTO notifications (user_id, type, reference_id, message)
             VALUES (?, ?, ?, ?)'
        );
        if ($notificationStatement) {
            $notificationType = 'message';
            $notificationMessage = $senderName . ' sent you a new message.';
            $notificationStatement->bind_param('isis', $receiverId, $notificationType, $senderId, $notificationMessage);
            $notificationStatement->execute();
            $notificationStatement->close();
        }
    }
}

header('Location: messages.php?user_id=' . (int) $receiverId);
exit;
