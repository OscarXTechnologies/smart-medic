<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

$communityId = filter_input(INPUT_POST, 'community_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$memberId = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$communityId = $communityId === false || $communityId === null ? 0 : (int) $communityId;
$memberId = $memberId === false || $memberId === null ? 0 : (int) $memberId;
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $communityId < 1 || $memberId < 1) {
    header('Location: community.php');
    exit;
}

$communityStatement = $conn->prepare('SELECT created_by FROM communities WHERE id = ? LIMIT 1');
if (!$communityStatement) {
    header('Location: community.php?status=remove-error');
    exit;
}
$communityStatement->bind_param('i', $communityId);
$communityStatement->execute();
$community = $communityStatement->get_result()->fetch_assoc();
$communityStatement->close();

if (!$community) {
    header('Location: community.php?status=community-not-found');
    exit;
}

$ownerId = (int) $community['created_by'];
if ($ownerId !== $userId) {
    header('Location: community.php?community_id=' . $communityId . '&status=remove-forbidden');
    exit;
}

if ($memberId === $userId || $memberId === $ownerId) {
    header('Location: community.php?community_id=' . $communityId . '&status=cannot-remove-owner');
    exit;
}

$memberStatement = $conn->prepare(
    'SELECT id FROM community_members WHERE community_id = ? AND user_id = ? LIMIT 1'
);
if (!$memberStatement) {
    header('Location: community.php?community_id=' . $communityId . '&status=remove-error');
    exit;
}
$memberStatement->bind_param('ii', $communityId, $memberId);
$memberStatement->execute();
$member = $memberStatement->get_result()->fetch_assoc();
$memberStatement->close();

if (!$member) {
    header('Location: community.php?community_id=' . $communityId . '&status=member-not-found');
    exit;
}

$deleteStatement = $conn->prepare(
    'DELETE FROM community_members WHERE community_id = ? AND user_id = ? LIMIT 1'
);
if (!$deleteStatement) {
    header('Location: community.php?community_id=' . $communityId . '&status=remove-error');
    exit;
}
$deleteStatement->bind_param('ii', $communityId, $memberId);
$removed = $deleteStatement->execute();
$deleteStatement->close();

header('Location: community.php?community_id=' . $communityId . '&status=' . ($removed ? 'member-removed' : 'remove-error'));
exit;
