<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

$errors = [];
$content = '';
$maxFileSize = 5 * 1024 * 1024;
$uploadDirectory = __DIR__ . '/../uploads/posts/';
$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim((string) ($_POST['content'] ?? ''));
    $imagePath = null;
    $uploadedFile = $_FILES['image'] ?? null;
    $hasUpload = $uploadedFile && $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE;

    if ($content === '' && !$hasUpload) {
        $errors[] = 'Write something or choose an image before publishing.';
    }

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

    if (!$errors && $hasUpload) {
        if (!is_dir($uploadDirectory) || !is_writable($uploadDirectory)) {
            $errors[] = 'Images are temporarily unavailable. Please try again later.';
        } else {
            $extension = $allowedMimeTypes[$mimeType];
            $filename = bin2hex(random_bytes(16)) . '.' . $extension;
            $destination = $uploadDirectory . $filename;

            if (!move_uploaded_file($uploadedFile['tmp_name'], $destination)) {
                $errors[] = 'The image could not be saved. Please try again.';
            } else {
                $imagePath = 'uploads/posts/' . $filename;
            }
        }
    }

    if (!$errors) {
        $statement = $conn->prepare('INSERT INTO posts (user_id, content, image) VALUES (?, ?, ?)');

        if (!$statement) {
            if ($imagePath !== null) {
                @unlink($uploadDirectory . basename($imagePath));
            }
            $errors[] = 'Your post could not be published. Please try again later.';
        } else {
            $userId = (int) $_SESSION['user_id'];
            $statement->bind_param('iss', $userId, $content, $imagePath);

            if ($statement->execute()) {
                $statement->close();
                header('Location: ../dashboard.php');
                exit;
            }

            $statement->close();
            if ($imagePath !== null) {
                @unlink($uploadDirectory . basename($imagePath));
            }
            $errors[] = 'Your post could not be published. Please try again later.';
        }
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
    <title>Create post | Smart Medic</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="post-page">
    <main class="post-page-shell">
        <section class="post-composer" aria-labelledby="create-post-title">
            <a class="back-link" href="../dashboard.php">&larr; Back to Home</a>
            <header class="post-header">
                <p class="eyebrow">Smart Medic community</p>
                <h1 id="create-post-title">Share with your community</h1>
                <p>Start a helpful health conversation or share an image.</p>
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

            <form method="post" action="create_post.php" enctype="multipart/form-data" class="post-form">
                <label for="content">Your post</label>
                <textarea id="content" name="content" rows="8" maxlength="10000" placeholder="Share something about health..."><?= e($content) ?></textarea>

                <div class="post-form-footer">
                    <label class="image-picker" for="image">
                        <span aria-hidden="true">＋</span> Add image
                    </label>
                    <input class="sr-only" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
                    <span id="image-name" class="file-name">JPG, PNG or WEBP, up to 5 MB</span>
                    <button type="submit" class="button-primary">Publish post</button>
                </div>
            </form>
        </section>
    </main>
    <script>
        document.getElementById('image').addEventListener('change', function() {
            var name = this.files.length ? this.files[0].name : 'JPG, PNG or WEBP, up to 5 MB';
            document.getElementById('image-name').textContent = name;
        });
    </script>
</body>

</html>