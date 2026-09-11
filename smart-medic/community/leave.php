<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

$communityId = filter_input(INPUT_POST, 'community_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$communityId = $communityId === false || $communityId === null ? 0 : (int) $communityId;
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $communityId < 1) {
    header('Location: community.php');
    exit;
}

$communityStatement = $conn->prepare('SELECT created_by FROM communities WHERE id = ? LIMIT 1');
$creatorId = 0;
if ($communityStatement) {
    $communityStatement->bind_param('i', $communityId);
    $communityStatement->execute();
    $community = $communityStatement->get_result()->fetch_assoc();
    $creatorId = $community ? (int) $community['created_by'] : 0;
    $communityStatement->close();
}

if ($creatorId < 1) {
    header('Location: community.php?status=community-not-found');
    exit;
}

if ($creatorId === $userId) {
    header('Location: community.php?community_id=' . $communityId . '&status=creator-cannot-leave');
    exit;
}

$deleteStatement = $conn->prepare(
    'DELETE FROM community_members WHERE community_id = ? AND user_id = ? LIMIT 1'
);
if ($deleteStatement) {
    $deleteStatement->bind_param('ii', $communityId, $userId);
    $deleteStatement->execute();
    $deleteStatement->close();
}

header('Location: community.php?community_id=' . $communityId . '&status=left');
exit;
