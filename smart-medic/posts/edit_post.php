<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

$userId = (int) $_SESSION['user_id'];
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
$returnPath = '../dashboard.php';
if (preg_match('/^community\.php\?community_id=([1-9][0-9]*)$/', $returnTo, $returnMatches)) {
    $returnPath = '../' . $returnTo;
}
$errors = [];
$content = '';
$currentImage = null;
$postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
}

if ($postId === false || $postId === null) {
    header('Location: ../dashboard.php');
    exit;
}

$postStatement = $conn->prepare('SELECT id, user_id, community_id, content, image FROM posts WHERE id = ? LIMIT 1');
if (!$postStatement) {
    header('Location: ../dashboard.php');
    exit;
}

$postStatement->bind_param('i', $postId);
$postStatement->execute();
$post = $postStatement->get_result()->fetch_assoc();
$postStatement->close();

if (!$post || (int) $post['user_id'] !== $userId) {
    header('Location: ' . $returnPath);
    exit;
}

if (!empty($post['community_id'])) {
    $communityId = (int) $post['community_id'];
    $membershipStatement = $conn->prepare('SELECT id FROM community_members WHERE community_id = ? AND user_id = ? LIMIT 1');
    if (!$membershipStatement) {
        header('Location: ' . $returnPath);
        exit;
    }
    $membershipStatement->bind_param('ii', $communityId, $userId);
    $membershipStatement->execute();
    $isMember = (bool) $membershipStatement->get_result()->fetch_assoc();
    $membershipStatement->close();
    if (!$isMember) {
        header('Location: ' . $returnPath);
        exit;
    }
}

$content = (string) $post['content'];
$currentImage = trim((string) ($post['image'] ?? ''));
$uploadDirectory = __DIR__ . '/../uploads/posts/';
$maxFileSize = 5 * 1024 * 1024;
$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];
$mimeType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim((string) ($_POST['content'] ?? ''));
    $removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';
    $uploadedFile = $_FILES['image'] ?? null;
    $hasUpload = $uploadedFile && $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE;
    $newImagePath = null;
    $newImageFile = null;

    if ($hasUpload) {
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The image could not be uploaded. Please try again.';
        } elseif (!is_uploaded_file($uploadedFile['tmp_name'])) {
            $errors[] = 'The selected image is invalid.';
        } elseif ($uploadedFile['size'] > $maxFileSize) {
            $errors[] = 'Images must be 5 MB or smaller.';
        } else {
            $imageInfo = @getimagesize($uploadedFile['tmp_name']);
            $mimeType = $imageInfo['mime'] ?? '';
            if (!$imageInfo || !isset($allowedMimeTypes[$mimeType])) {
                $errors[] = 'Upload a valid JPG, PNG, or WEBP image.';
            }
        }
    }

    if ($content === '' && !$hasUpload && ($removeImage || $currentImage === '')) {
        $errors[] = 'A post must contain text or an image.';
    }

    if (!$errors && $hasUpload) {
        if (!is_dir($uploadDirectory) || !is_writable($uploadDirectory)) {
            $errors[] = 'Images are temporarily unavailable. Please try again later.';
        } else {
            $filename = bin2hex(random_bytes(16)) . '.' . $allowedMimeTypes[$mimeType];
            $newImageFile = $uploadDirectory . $filename;
            if (!move_uploaded_file($uploadedFile['tmp_name'], $newImageFile)) {
                $errors[] = 'The image could not be saved. Please try again.';
            } else {
                $newImagePath = 'uploads/posts/' . $filename;
            }
        }
    }

    $updatedImage = $newImagePath ?? (($removeImage) ? null : ($currentImage !== '' ? $currentImage : null));

    if (!$errors) {
        $updateStatement = $conn->prepare('UPDATE posts SET content = ?, image = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?');
        if (!$updateStatement) {
            $errors[] = 'The post could not be updated. Please try again later.';
        } else {
            $updateStatement->bind_param('ssii', $content, $updatedImage, $postId, $userId);
            if ($updateStatement->execute()) {
                $updateStatement->close();
                if (($newImagePath !== null || $removeImage) && $currentImage !== '') {
                    @unlink($uploadDirectory . basename($currentImage));
                }
                header('Location: ' . $returnPath);
                exit;
            }
            $updateStatement->close();
            $errors[] = 'The post could not be updated. Please try again later.';
        }
    }

    if ($newImageFile !== null && is_file($newImageFile)) {
        @unlink($newImageFile);
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit post | Smart Medic</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="post-page">
    <main class="post-page-shell">
        <section class="post-composer" aria-labelledby="edit-post-title">
            <a class="back-link" href="../dashboard.php">&larr; Back to Home</a>
            <header class="post-header">
                <p class="eyebrow">Smart Medic community</p>
                <h1 id="edit-post-title">Edit your post</h1>
                <p>Update your message or replace the image.</p>
            </header>

            <?php if ($errors): ?>
                <div class="alert alert-error" role="alert">
                    <p>Please correct the following:</p>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="edit_post.php?id=<?= (int) $postId ?>" enctype="multipart/form-data" class="post-form">
                <input type="hidden" name="post_id" value="<?= (int) $postId ?>">
                <?php if ($returnTo !== ''): ?><input type="hidden" name="return_to" value="<?= e($returnTo) ?>"><?php endif; ?>
                <label for="content">Your post</label>
                <textarea id="content" name="content" rows="8" maxlength="10000"><?= e($content) ?></textarea>

                <?php if ($currentImage !== ''): ?>
                    <div class="existing-post-image">
                        <p>Current image</p>
                        <img src="../<?= e($currentImage) ?>" alt="Current post image">
                    </div>
                    <label class="remove-image-option">
                        <input type="checkbox" name="remove_image" value="1">
                        Remove current image
                    </label>
                <?php endif; ?>

                <label for="image">Replace image</label>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
                <span class="file-name">JPG, PNG or WEBP, up to 5 MB</span>
                <button type="submit" class="button-primary">Save changes</button>
            </form>
        </section>
    </main>
</body>

</html>