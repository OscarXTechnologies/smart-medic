<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

$errors = [];
$communityName = '';
$communityDescription = '';
$imagePath = null;
$imageFilePath = null;
$maxImageSize = 5 * 1024 * 1024;
$uploadDirectory = __DIR__ . '/../uploads/covers/';
$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $communityName = trim((string) ($_POST['name'] ?? ''));
    $communityDescription = trim((string) ($_POST['description'] ?? ''));
    $uploadedFile = $_FILES['image'] ?? null;
    $hasUpload = $uploadedFile && $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE;
    $mimeType = '';

    if ($communityName === '') {
        $errors[] = 'Community name is required.';
    } elseif (strlen($communityName) > 150) {
        $errors[] = 'Community name must be 150 characters or fewer.';
    }

    if ($communityDescription === '') {
        $errors[] = 'Community description is required.';
    } elseif (strlen($communityDescription) > 10000) {
        $errors[] = 'Community description must be 10,000 characters or fewer.';
    }

    if ($hasUpload) {
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($uploadedFile['tmp_name'])) {
            $errors[] = 'The selected image is invalid.';
        } elseif ($uploadedFile['size'] > $maxImageSize) {
            $errors[] = 'Images must be 5 MB or smaller.';
        } else {
            $imageInfo = @getimagesize($uploadedFile['tmp_name']);
            $mimeType = '';
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
                $imagePath = 'uploads/covers/' . $filename;
            }
        }
    }

    if (!$errors) {
        $userId = (int) $_SESSION['user_id'];
        $conn->begin_transaction();
        $communityStatement = $conn->prepare(
            'INSERT INTO communities (name, description, image, created_by) VALUES (?, ?, ?, ?)'
        );

        if (!$communityStatement) {
            $conn->rollback();
            $errors[] = 'The community could not be created. Please try again later.';
        } else {
            $communityStatement->bind_param('sssi', $communityName, $communityDescription, $imagePath, $userId);

            if (!$communityStatement->execute()) {
                $communityStatement->close();
                $conn->rollback();
                $errors[] = 'The community could not be created. Please try again later.';
            } else {
                $communityId = (int) $conn->insert_id;
                $communityStatement->close();
                $memberStatement = $conn->prepare(
                    'INSERT INTO community_members (community_id, user_id) VALUES (?, ?)'
                );

                if (!$memberStatement) {
                    $conn->rollback();
                    $errors[] = 'The community could not be created. Please try again later.';
                } else {
                    $memberStatement->bind_param('ii', $communityId, $userId);

                    if (!$memberStatement->execute() || !$conn->commit()) {
                        $memberStatement->close();
                        $conn->rollback();
                        $errors[] = 'The community could not be created. Please try again later.';
                    } else {
                        $memberStatement->close();
                        header('Location: community.php?community_id=' . $communityId);
                        exit;
                    }
                }
            }
        }
    }

    if ($errors && $imageFilePath !== null && is_file($imageFilePath)) {
        @unlink($imageFilePath);
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
    <title>Create Community | Smart Medic</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="post-page community-create-page">
    <main class="post-page-shell community-create-shell">
        <section class="post-composer community-create-card" aria-labelledby="create-community-title">
            <a class="back-link" href="community.php">&larr; Back to Communities</a>
            <header class="post-header">
                <p class="eyebrow">Bring people together</p>
                <h1 id="create-community-title">Create a Community</h1>
                <p>Start a space for people to connect, learn, and support one another.</p>
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

            <form method="post" action="create.php" enctype="multipart/form-data" class="community-form">
                <label for="community-name">Community name <span aria-hidden="true">*</span></label>
                <input type="text" id="community-name" name="name" value="<?= e($communityName) ?>" maxlength="150" required autofocus>

                <label for="community-description">Description <span aria-hidden="true">*</span></label>
                <textarea id="community-description" name="description" rows="7" maxlength="10000" required><?= e($communityDescription) ?></textarea>

                <label for="community-image">Community image</label>
                <input type="file" id="community-image" name="image" accept="image/jpeg,image/png,image/webp">
                <small class="community-form-note">Optional. JPG, PNG, or WEBP up to 5 MB.</small>

                <button type="submit" class="button-primary">Create Community</button>
            </form>
        </section>
    </main>
</body>

</html>