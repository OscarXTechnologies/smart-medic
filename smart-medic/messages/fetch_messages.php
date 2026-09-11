<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$currentUserId = (int) $_SESSION['user_id'];
$selectedUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if ($selectedUserId === false || $selectedUserId === null || $selectedUserId === $currentUserId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid conversation.']);
    exit;
}

$userStatement = $conn->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
if (!$userStatement) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Messages are temporarily unavailable.']);
    exit;
}

$userStatement->bind_param('i', $selectedUserId);
$userStatement->execute();
$selectedUser = $userStatement->get_result()->fetch_assoc();
$userStatement->close();

if (!$selectedUser) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Conversation user not found.']);
    exit;
}

$messageStatement = $conn->prepare(
    'SELECT id, sender_id, receiver_id, message, created_at
	 FROM messages
	 WHERE (sender_id = ? AND receiver_id = ?)
		OR (sender_id = ? AND receiver_id = ?)
	 ORDER BY created_at ASC, id ASC'
);

if (!$messageStatement) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Messages are temporarily unavailable.']);
    exit;
}

$messageStatement->bind_param('iiii', $currentUserId, $selectedUserId, $selectedUserId, $currentUserId);
$messageStatement->execute();
$result = $messageStatement->get_result();
$messages = [];

while ($message = $result->fetch_assoc()) {
    $messages[] = [
        'id' => (int) $message['id'],
        'sender_id' => (int) $message['sender_id'],
        'receiver_id' => (int) $message['receiver_id'],
        'message' => $message['message'],
        'created_at' => $message['created_at']
    ];
}

$messageStatement->close();
$unreadStatement = $conn->prepare('SELECT COUNT(*) AS unread_count FROM messages WHERE receiver_id = ? AND is_read = 0');
$unreadCount = 0;
if ($unreadStatement) {
    $unreadStatement->bind_param('i', $currentUserId);
    $unreadStatement->execute();
    $unreadRow = $unreadStatement->get_result()->fetch_assoc();
    $unreadCount = (int) ($unreadRow['unread_count'] ?? 0);
    $unreadStatement->close();
}

echo json_encode(['success' => true, 'messages' => $messages, 'unread_count' => $unreadCount], JSON_UNESCAPED_UNICODE);
