<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

$redirect = '../dashboard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

$postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$userId = (int) $_SESSION['user_id'];
$returnTo = trim((string) ($_POST['return_to'] ?? ''));
if (preg_match('/^community\.php\?community_id=([1-9][0-9]*)$/', $returnTo, $matches)) {
    $communityId = (int) $matches[1];
    $membershipStatement = $conn->prepare('SELECT id FROM community_members WHERE community_id = ? AND user_id = ? LIMIT 1');
    if (!$membershipStatement) {
        header('Location: ' . $redirect);
        exit;
    }
    $membershipStatement->bind_param('ii', $communityId, $userId);
    $membershipStatement->execute();
    $isMember = (bool) $membershipStatement->get_result()->fetch_assoc();
    $membershipStatement->close();
    if (!$isMember) {
        header('Location: ' . $redirect);
        exit;
    }
    $redirect = '../' . $returnTo;
}

if ($postId === false || $postId === null) {
    header('Location: ' . $redirect);
    exit;
}

$postCheck = $conn->prepare('SELECT id, user_id, community_id FROM posts WHERE id = ? LIMIT 1');
if (!$postCheck) {
    header('Location: ' . $redirect);
    exit;
}

$postCheck->bind_param('i', $postId);
$postCheck->execute();
$postExists = $postCheck->get_result()->fetch_assoc();
$postCheck->close();

if (!$postExists) {
    header('Location: ' . $redirect);
    exit;
}

if (!empty($postExists['community_id']) && !isset($matches)) {
    $communityId = (int) $postExists['community_id'];
    $membershipStatement = $conn->prepare('SELECT id FROM community_members WHERE community_id = ? AND user_id = ? LIMIT 1');
    if (!$membershipStatement) {
        header('Location: ' . $redirect);
        exit;
    }
    $membershipStatement->bind_param('ii', $communityId, $userId);
    $membershipStatement->execute();
    $isMember = (bool) $membershipStatement->get_result()->fetch_assoc();
    $membershipStatement->close();
    if (!$isMember) {
        header('Location: ' . $redirect);
        exit;
    }
}

if (!empty($postExists['community_id']) && isset($matches) && (int) $postExists['community_id'] !== (int) $matches[1]) {
    header('Location: ' . $redirect);
    exit;
}

$statement = $conn->prepare('INSERT INTO post_shares (post_id, user_id) VALUES (?, ?)');

if ($statement) {
    $statement->bind_param('ii', $postId, $userId);
    $shared = $statement->execute();
    $insertedRows = $statement->affected_rows;
    $statement->close();

    if ($shared && $insertedRows === 1 && (int) $postExists['user_id'] !== $userId) {
        $actorStatement = $conn->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $actorUsername = 'A Smart Medic user';
        if ($actorStatement) {
            $actorStatement->bind_param('i', $userId);
            $actorStatement->execute();
            $actor = $actorStatement->get_result()->fetch_assoc();
            $actorStatement->close();
            if ($actor) {
                $actorUsername = $actor['username'];
            }
        }

        $notificationStatement = $conn->prepare(
            'INSERT INTO notifications (user_id, type, reference_id, message, is_read)
             VALUES (?, ?, ?, ?, 0)'
        );
        if ($notificationStatement) {
            $notificationType = 'share';
            $notificationMessage = $actorUsername . ' shared your post.';
            $postOwnerId = (int) $postExists['user_id'];
            $notificationStatement->bind_param('isis', $postOwnerId, $notificationType, $postId, $notificationMessage);
            $notificationStatement->execute();
            $notificationStatement->close();
        }
    }
}

header('Location: ' . $redirect);
exit;
