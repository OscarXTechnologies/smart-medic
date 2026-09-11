<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

$communityId = filter_input(INPUT_POST, 'community_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$communityId = $communityId === false || $communityId === null ? 0 : (int) $communityId;
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $communityId < 1) {
    header('Location: community.php');
    exit;
}

$membershipStatement = $conn->prepare(
    'SELECT c.id
     FROM communities AS c
     INNER JOIN community_members AS cm ON cm.community_id = c.id
     WHERE c.id = ? AND cm.user_id = ?
     LIMIT 1'
);
$isMember = false;
if ($membershipStatement) {
    $membershipStatement->bind_param('ii', $communityId, $userId);
    $membershipStatement->execute();
    $isMember = (bool) $membershipStatement->get_result()->fetch_assoc();
    $membershipStatement->close();
}

if (!$isMember) {
    header('Location: community.php?community_id=' . $communityId . '&status=must-join');
    exit;
}

$content = trim((string) ($_POST['content'] ?? ''));
$uploadedFile = $_FILES['image'] ?? null;
$hasUpload = $uploadedFile && $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE;
$imagePath = null;
$imageFilePath = null;
$errors = [];
$maxFileSize = 5 * 1024 * 1024;
$uploadDirectory = __DIR__ . '/../uploads/posts/';
$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];
$mimeType = '';

if ($content === '') {
    $errors[] = 'Your post cannot be empty.';
}

if ($hasUpload) {
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($uploadedFile['tmp_name'])) {
        $errors[] = 'The selected image is invalid.';
    } elseif ($uploadedFile['size'] > $maxFileSize) {
        $errors[] = 'Images must be 5 MB or smaller.';
    } else {
        $imageInfo = @getimagesize($uploadedFile['tmp_name']);
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo) {
            $mimeType = (string) finfo_file($fileInfo, $uploadedFile['tmp_name']);
            finfo_close($fileInfo);
        }

        if (!$imageInfo || $imageInfo['mime'] !== $mimeType || !isset($allowedMimeTypes[$mimeType])) {
            $errors[] = 'Upload a valid JPG, PNG, or WEBP image.';
        }
    }
}

if (!$errors && $hasUpload) {
    if (!is_dir($uploadDirectory) || !is_writable($uploadDirectory)) {
        $errors[] = 'Images are temporarily unavailable. Please try again later.';
    } else {
        $filename = bin2hex(random_bytes(16)) . '.' . $allowedMimeTypes[$mimeType];
        $imageFilePath = $uploadDirectory . $filename;
        if (!move_uploaded_file($uploadedFile['tmp_name'], $imageFilePath)) {
            $errors[] = 'The image could not be saved. Please try again later.';
            $imageFilePath = null;
        } else {
            $imagePath = 'uploads/posts/' . $filename;
        }
    }
}

if (!$errors) {
    $statement = $conn->prepare(
        'INSERT INTO posts (user_id, community_id, content, image) VALUES (?, ?, ?, ?)'
    );

    if (!$statement) {
        $errors[] = 'Your community post could not be published. Please try again later.';
    } else {
        $statement->bind_param('iiss', $userId, $communityId, $content, $imagePath);
        if ($statement->execute()) {
            $statement->close();
            header('Location: community.php?community_id=' . $communityId);
            exit;
        }
        $statement->close();
        $errors[] = 'Your community post could not be published. Please try again later.';
    }
}

if ($imageFilePath !== null && is_file($imageFilePath)) {
    @unlink($imageFilePath);
}

header('Location: community.php?community_id=' . $communityId . '&status=post-error');
exit;
