<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/config.php';

$userId = (int) $_SESSION['user_id'];
$errors = [];
$successMessage = '';
$uploadDirectory = [
    'profile_picture' => __DIR__ . '/uploads/profiles/',
    'cover_photo' => __DIR__ . '/uploads/covers/'
];
$maxFileSize = 5 * 1024 * 1024;
$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];

$profileStatement = $conn->prepare(
    'SELECT first_name, middle_name, last_name, username, email, bio, profile_picture, cover_photo
	 FROM users WHERE id = ? LIMIT 1'
);

if (!$profileStatement) {
    header('Location: logout.php');
    exit;
}

$profileStatement->bind_param('i', $userId);
$profileStatement->execute();
$profile = $profileStatement->get_result()->fetch_assoc();
$profileStatement->close();

if (!$profile) {
    header('Location: logout.php');
    exit;
}

$formData = [
    'first_name' => (string) $profile['first_name'],
    'middle_name' => (string) ($profile['middle_name'] ?? ''),
    'last_name' => (string) $profile['last_name'],
    'username' => (string) $profile['username'],
    'email' => (string) $profile['email'],
    'bio' => (string) ($profile['bio'] ?? '')
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $field => $value) {
        $formData[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if ($formData['first_name'] === '' || $formData['last_name'] === '') {
        $errors[] = 'First name and last name are required.';
    }

    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $formData['username'])) {
        $errors[] = 'Username must be 3 to 50 characters and use only letters, numbers, dots, underscores, or hyphens.';
    }

    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (strlen($formData['bio']) > 5000) {
        $errors[] = 'Bio must be 5,000 characters or fewer.';
    }

    if (!$errors) {
        $duplicateStatement = $conn->prepare(
            'SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1'
        );

        if (!$duplicateStatement) {
            $errors[] = 'Your profile could not be checked. Please try again later.';
        } else {
            $duplicateStatement->bind_param('ssi', $formData['username'], $formData['email'], $userId);
            $duplicateStatement->execute();
            $duplicate = $duplicateStatement->get_result()->fetch_assoc();
            $duplicateStatement->close();

            if ($duplicate) {
                $errors[] = 'That username or email address is already in use.';
            }
        }
    }

    $newFiles = [];
    foreach (['profile_picture', 'cover_photo'] as $imageField) {
        $file = $_FILES[$imageField] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'The selected ' . ($imageField === 'profile_picture' ? 'profile picture' : 'cover photo') . ' is invalid.';
            continue;
        }

        if ($file['size'] > $maxFileSize) {
            $errors[] = 'Uploaded images must be 5 MB or smaller.';
            continue;
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        $mimeType = $imageInfo['mime'] ?? '';
        if (!$imageInfo || !isset($allowedMimeTypes[$mimeType])) {
            $errors[] = 'Upload only JPG, PNG, or WEBP images.';
            continue;
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $allowedMimeTypes[$mimeType];
        $destination = $uploadDirectory[$imageField] . $filename;
        if (!is_dir($uploadDirectory[$imageField]) || !is_writable($uploadDirectory[$imageField]) || !move_uploaded_file($file['tmp_name'], $destination)) {
            $errors[] = 'The new image could not be saved. Please try again later.';
            continue;
        }

        $newFiles[$imageField] = [
            'path' => 'uploads/' . ($imageField === 'profile_picture' ? 'profiles' : 'covers') . '/' . $filename,
            'file' => $destination
        ];
    }

    if (!$errors) {
        $profilePicture = $newFiles['profile_picture']['path'] ?? ($profile['profile_picture'] ?: null);
        $coverPhoto = $newFiles['cover_photo']['path'] ?? ($profile['cover_photo'] ?: null);
        $updateStatement = $conn->prepare(
            'UPDATE users
			 SET first_name = ?, middle_name = ?, last_name = ?, username = ?, email = ?, bio = ?,
				 profile_picture = ?, cover_photo = ?, updated_at = CURRENT_TIMESTAMP
			 WHERE id = ?'
        );

        if (!$updateStatement) {
            $errors[] = 'Your profile could not be saved. Please try again later.';
        } else {
            $updateStatement->bind_param(
                'ssssssssi',
                $formData['first_name'],
                $formData['middle_name'],
                $formData['last_name'],
                $formData['username'],
                $formData['email'],
                $formData['bio'],
                $profilePicture,
                $coverPhoto,
                $userId
            );

            if ($updateStatement->execute()) {
                $updateStatement->close();
                if (isset($newFiles['profile_picture']) && !empty($profile['profile_picture'])) {
                    @unlink(__DIR__ . '/' . basename($profile['profile_picture']));
                }
                if (isset($newFiles['cover_photo']) && !empty($profile['cover_photo'])) {
                    @unlink(__DIR__ . '/' . basename($profile['cover_photo']));
                }
                $successMessage = 'Your profile has been updated successfully.';
                $profile['profile_picture'] = $profilePicture;
                $profile['cover_photo'] = $coverPhoto;
            } else {
                $updateStatement->close();
                $errors[] = 'Your profile could not be saved. Please try again later.';
            }
        }
    }

    if ($errors) {
        foreach ($newFiles as $newFile) {
            if (is_file($newFile['file'])) {
                @unlink($newFile['file']);
            }
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
    <title>Edit profile | Smart Medic</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="post-page edit-profile-page">
    <main class="post-page-shell edit-profile-shell">
        <section class="post-composer edit-profile-card" aria-labelledby="edit-profile-title">
            <a class="back-link" href="profile.php">&larr; Back to profile</a>
            <header class="post-header">
                <p class="eyebrow">Your Smart Medic identity</p>
                <h1 id="edit-profile-title">Edit profile</h1>
                <p>Keep your healthcare community profile current.</p>
            </header>

            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success" role="status">
                    <?= e($successMessage) ?>
                    <a href="profile.php">View your profile</a>
                </div>
            <?php endif; ?>

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

            <form method="POST" action="edit_profile.php" enctype="multipart/form-data" class="edit-profile-form">
                <div class="edit-profile-preview">
                    <div class="edit-cover-preview">
                        <?php if (!empty($profile['cover_photo'])): ?>
                            <img src="<?= e($profile['cover_photo']) ?>" alt="Current cover photo">
                        <?php else: ?>
                            <span>Smart Medic</span>
                        <?php endif; ?>
                    </div>
                    <div class="edit-avatar-preview">
                        <?php if (!empty($profile['profile_picture'])): ?>
                            <img src="<?= e($profile['profile_picture']) ?>" alt="Current profile picture">
                        <?php else: ?>
                            <?= e(strtoupper(substr($formData['first_name'], 0, 1))) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="edit-form-grid">
                    <label for="first_name">First name <span>*</span></label>
                    <input type="text" id="first_name" name="first_name" value="<?= e($formData['first_name']) ?>" maxlength="100" required>
                    <label for="middle_name">Middle name</label>
                    <input type="text" id="middle_name" name="middle_name" value="<?= e($formData['middle_name']) ?>" maxlength="100">
                    <label for="last_name">Last name <span>*</span></label>
                    <input type="text" id="last_name" name="last_name" value="<?= e($formData['last_name']) ?>" maxlength="100" required>
                    <label for="username">Username <span>*</span></label>
                    <input type="text" id="username" name="username" value="<?= e($formData['username']) ?>" maxlength="50" required>
                    <label for="email">Email <span>*</span></label>
                    <input type="email" id="email" name="email" value="<?= e($formData['email']) ?>" maxlength="255" required>
                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" rows="5" maxlength="5000"><?= e($formData['bio']) ?></textarea>
                </div>

                <div class="edit-upload-grid">
                    <label for="profile_picture">New profile picture</label>
                    <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/webp">
                    <label for="cover_photo">New cover photo</label>
                    <input type="file" id="cover_photo" name="cover_photo" accept="image/jpeg,image/png,image/webp">
                    <small>JPG, PNG or WEBP · maximum 5 MB per image</small>
                </div>

                <button type="submit" class="button-primary">Save profile</button>
            </form>
        </section>
    </main>
</body>

</html>