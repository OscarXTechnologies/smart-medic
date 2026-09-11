<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

$targetUserId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT, [
	'options' => ['min_range' => 1]
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $targetUserId === false || $targetUserId === null) {
	header('Location: profile.php');
	exit;
}

$currentUserId = (int) $_SESSION['user_id'];
$statement = $conn->prepare(
	'DELETE FROM followers WHERE follower_id = ? AND following_id = ?'
);

if ($statement) {
	$statement->bind_param('ii', $currentUserId, $targetUserId);
	$statement->execute();
	$statement->close();
}

header('Location: profile.php?user_id=' . (int) $targetUserId);
exit;
