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

$communityStatement = $conn->prepare('SELECT id FROM communities WHERE id = ? LIMIT 1');
$communityExists = false;
if ($communityStatement) {
    $communityStatement->bind_param('i', $communityId);
    $communityStatement->execute();
    $communityExists = (bool) $communityStatement->get_result()->fetch_assoc();
    $communityStatement->close();
}

if (!$communityExists) {
    header('Location: community.php?status=community-not-found');
    exit;
}

$membershipStatement = $conn->prepare(
    'SELECT id FROM community_members WHERE community_id = ? AND user_id = ? LIMIT 1'
);
$alreadyMember = false;
if ($membershipStatement) {
    $membershipStatement->bind_param('ii', $communityId, $userId);
    $membershipStatement->execute();
    $alreadyMember = (bool) $membershipStatement->get_result()->fetch_assoc();
    $membershipStatement->close();
}

if (!$alreadyMember) {
    $insertStatement = $conn->prepare(
        'INSERT INTO community_members (community_id, user_id) VALUES (?, ?)'
    );
    if ($insertStatement) {
        $insertStatement->bind_param('ii', $communityId, $userId);
        $insertStatement->execute();
        $insertStatement->close();
    }
}

header('Location: community.php?community_id=' . $communityId . '&status=' . ($alreadyMember ? 'already-joined' : 'joined'));
exit;
