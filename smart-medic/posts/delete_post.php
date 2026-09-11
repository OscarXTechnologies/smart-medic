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

if ($postId === false || $postId === null) {
    header('Location: ' . $redirect);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$returnTo = trim((string) ($_POST['return_to'] ?? ''));
if (preg_match('/^community\.php\?community_id=([1-9][0-9]*)$/', $returnTo, $matches)) {
    $redirect = '../' . $returnTo;
}
$postStatement = $conn->prepare('SELECT image, community_id FROM posts WHERE id = ? AND user_id = ? LIMIT 1');
if (!$postStatement) {
    header('Location: ' . $redirect);
    exit;
}

$postStatement->bind_param('ii', $postId, $userId);
$postStatement->execute();
$post = $postStatement->get_result()->fetch_assoc();
$postStatement->close();

if (!$post) {
    header('Location: ' . $redirect);
    exit;
}

if (!empty($post['community_id'])) {
    $communityId = (int) $post['community_id'];
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

$deleteStatement = $conn->prepare('DELETE FROM posts WHERE id = ? AND user_id = ?');
if ($deleteStatement) {
    $deleteStatement->bind_param('ii', $postId, $userId);
    $deleted = $deleteStatement->execute();
    $deleteStatement->close();

    if ($deleted && !empty($post['image'])) {
        @unlink(__DIR__ . '/../uploads/posts/' . basename($post['image']));
    }
}

header('Location: ' . $redirect);
exit;
