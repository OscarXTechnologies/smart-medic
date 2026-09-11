<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

$redirect = 'profile.php';
$currentUserId = (int) $_SESSION['user_id'];
$targetUserId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $targetUserId === false || $targetUserId === null || $targetUserId === $currentUserId) {
    header('Location: ' . $redirect);
    exit;
}

$userCheck = $conn->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
if (!$userCheck) {
    header('Location: ' . $redirect . '?user_id=' . (int) $targetUserId);
    exit;
}

$userCheck->bind_param('i', $targetUserId);
$userCheck->execute();
$targetExists = $userCheck->get_result()->fetch_assoc();
$userCheck->close();

$redirect = 'profile.php?user_id=' . (int) $targetUserId;
if (!$targetExists) {
    header('Location: ' . $redirect);
    exit;
}

$relationshipCheck = $conn->prepare(
    'SELECT id FROM followers WHERE follower_id = ? AND following_id = ? LIMIT 1'
);
if ($relationshipCheck) {
    $relationshipCheck->bind_param('ii', $currentUserId, $targetUserId);
    $relationshipCheck->execute();
    $exists = $relationshipCheck->get_result()->fetch_assoc();
    $relationshipCheck->close();

    if (!$exists) {
        $insert = $conn->prepare('INSERT INTO followers (follower_id, following_id) VALUES (?, ?)');
        if ($insert) {
            $insert->bind_param('ii', $currentUserId, $targetUserId);
            $followed = $insert->execute();
            $insertedRows = $insert->affected_rows;
            $insert->close();

            if ($followed && $insertedRows === 1) {
                $actorStatement = $conn->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
                $actorUsername = 'A Smart Medic user';
                if ($actorStatement) {
                    $actorStatement->bind_param('i', $currentUserId);
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
                    $notificationType = 'follow';
                    $notificationMessage = $actorUsername . ' started following you.';
                    $notificationStatement->bind_param('isis', $targetUserId, $notificationType, $currentUserId, $notificationMessage);
                    $notificationStatement->execute();
                    $notificationStatement->close();
                }
            }
        }
    }
}

header('Location: ' . $redirect);
exit;
