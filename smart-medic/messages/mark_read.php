<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$currentUserId = (int) $_SESSION['user_id'];
$selectedUserId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if ($selectedUserId === false || $selectedUserId === null || $selectedUserId === $currentUserId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid conversation.']);
    exit;
}

$userCheck = $conn->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
if (!$userCheck) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Messages are temporarily unavailable.']);
    exit;
}

$userCheck->bind_param('i', $selectedUserId);
$userCheck->execute();
$selectedUser = $userCheck->get_result()->fetch_assoc();
$userCheck->close();

if (!$selectedUser) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Conversation user not found.']);
    exit;
}

$markRead = $conn->prepare(
    'UPDATE messages
     SET is_read = 1
     WHERE sender_id = ? AND receiver_id = ? AND is_read = 0'
);

if (!$markRead) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Messages are temporarily unavailable.']);
    exit;
}

$markRead->bind_param('ii', $selectedUserId, $currentUserId);
$success = $markRead->execute();
$markRead->close();

if (!$success) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Messages are temporarily unavailable.']);
    exit;
}

echo json_encode(['success' => true]);
