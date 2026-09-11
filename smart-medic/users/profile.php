<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

$currentUserId = (int) $_SESSION['user_id'];
$profileUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if ($profileUserId === false || $profileUserId === null) {
    header('Location: ../dashboard.php');
    exit;
}

$profileStatement = $conn->prepare(
    'SELECT u.id, u.first_name, u.middle_name, u.last_name, u.username,
			u.profile_picture, u.cover_photo, u.bio, u.role, u.created_at,
			(SELECT COUNT(*) FROM posts WHERE user_id = u.id) AS post_count,
			(SELECT COUNT(*) FROM followers WHERE following_id = u.id) AS follower_count,
			(SELECT COUNT(*) FROM followers WHERE follower_id = u.id) AS following_count,
			EXISTS (
				SELECT 1 FROM followers
				WHERE follower_id = ? AND following_id = u.id
			) AS is_following
	 FROM users AS u
	 WHERE u.id = ?
	 LIMIT 1'
);

if (!$profileStatement) {
    header('Location: ../dashboard.php');
    exit;
}

$profileStatement->bind_param('ii', $currentUserId, $profileUserId);
$profileStatement->execute();
$profile = $profileStatement->get_result()->fetch_assoc();
$profileStatement->close();

if (!$profile) {
    header('Location: ../dashboard.php');
    exit;
}

$postStatement = $conn->prepare(
    'SELECT p.id, p.content, p.image, p.created_at,
			(SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS like_count,
			(SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count,
			(SELECT COUNT(*) FROM post_shares WHERE post_id = p.id) AS share_count
	 FROM posts AS p
	 WHERE p.user_id = ?
	 ORDER BY p.created_at DESC'
);
$posts = [];

if ($postStatement) {
    $postStatement->bind_param('i', $profileUserId);
    $postStatement->execute();
    $postResult = $postStatement->get_result();
    while ($post = $postResult->fetch_assoc()) {
        $posts[] = $post;
    }
    $postStatement->close();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$fullName = trim($profile['first_name'] . ' ' . ($profile['middle_name'] ? $profile['middle_name'] . ' ' : '') . $profile['last_name']);
$profilePicture = trim((string) ($profile['profile_picture'] ?? ''));
$coverPhoto = trim((string) ($profile['cover_photo'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($fullName) ?> | Smart Medic</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="dashboard-body public-profile-page">
    <header class="dashboard-header">
        <a class="brand-mark" href="../dashboard.php" aria-label="Smart Medic home">
            <span class="brand-symbol" aria-hidden="true">+</span>
            <span>Smart Medic</span>
        </a>
        <label class="dashboard-search">
            <span class="sr-only">Search Smart Medic</span>
            <input type="search" placeholder="Search Smart Medic...">
        </label>
        <nav class="desktop-main-nav" aria-label="Main navigation">
            <a href="../dashboard.php">Home</a>
            <a href="../community/community.php">Community</a>
            <a href="../support/support.php">Support</a>
            <a href="../bookings/bookings.php">Bookings</a>
            <a href="../account/account.php">Account</a>
        </nav>
        <a class="header-avatar" href="../profile.php" aria-label="Open profile">
            <?php if ($currentUserId === (int) $profile['id'] && $profilePicture !== ''): ?>
                <img src="../<?= e($profilePicture) ?>" alt="">
            <?php else: ?>
                <?= e(strtoupper(substr($_SESSION['username'] ?? 'S', 0, 1))) ?>
            <?php endif; ?>
        </a>
    </header>

    <main class="profile-shell public-profile-shell">
        <section class="profile-card" aria-labelledby="public-profile-name">
            <div class="profile-cover <?= $coverPhoto === '' ? 'profile-cover-default' : '' ?>">
                <?php if ($coverPhoto !== ''): ?>
                    <img src="../<?= e($coverPhoto) ?>" alt="">
                <?php else: ?>
                    <span aria-hidden="true">Smart Medic</span>
                <?php endif; ?>
            </div>
            <div class="profile-summary">
                <div class="profile-avatar profile-page-avatar">
                    <?php if ($profilePicture !== ''): ?>
                        <img src="../<?= e($profilePicture) ?>" alt="Profile picture of <?= e($fullName) ?>">
                    <?php else: ?>
                        <?= e(strtoupper(substr($profile['first_name'], 0, 1))) ?>
                    <?php endif; ?>
                </div>
                <div class="profile-identity">
                    <p class="eyebrow">Smart Medic profile</p>
                    <h1 id="public-profile-name"><?= e($fullName) ?></h1>
                    <p>@<?= e($profile['username']) ?> <span class="profile-role">· <?= e(ucwords(str_replace('_', ' ', $profile['role']))) ?></span></p>
                </div>
                <?php if ($currentUserId !== (int) $profile['id']): ?>
                    <form method="POST" action="<?= (int) $profile['is_following'] === 1 ? 'unfollow.php' : 'follow.php' ?>">
                        <input type="hidden" name="user_id" value="<?= (int) $profile['id'] ?>">
                        <button type="submit" class="profile-follow-button <?= (int) $profile['is_following'] === 1 ? 'is-following' : '' ?>">
                            <?= (int) $profile['is_following'] === 1 ? 'Unfollow' : 'Follow' ?>
                        </button>
                    </form>
                <?php else: ?>
                    <a class="profile-edit-button" href="../edit_profile.php">Edit Profile</a>
                <?php endif; ?>
            </div>
            <div class="profile-details">
                <p><?= $profile['bio'] !== null && trim($profile['bio']) !== '' ? nl2br(e($profile['bio'])) : 'This user has not added a bio yet.' ?></p>
                <span>Member since <?= e(date('F Y', strtotime($profile['created_at']))) ?></span>
            </div>
            <div class="profile-stats" aria-label="Profile statistics">
                <div><strong><?= (int) $profile['post_count'] ?></strong><span>Posts</span></div>
                <div><strong><?= (int) $profile['follower_count'] ?></strong><span>Followers</span></div>
                <div><strong><?= (int) $profile['following_count'] ?></strong><span>Following</span></div>
            </div>
        </section>

        <section class="profile-posts" aria-labelledby="public-posts-title">
            <div class="profile-section-heading">
                <div>
                    <p class="eyebrow">Community activity</p>
                    <h2 id="public-posts-title"><?= e($profile['first_name']) ?>'s posts</h2>
                </div>
            </div>
            <?php if (!$posts): ?>
                <div class="empty-feed">This user has not shared any posts yet.</div>
            <?php else: ?>
                <div class="profile-post-list">
                    <?php foreach ($posts as $post): ?>
                        <article class="profile-post-card">
                            <div class="profile-post-meta">
                                <strong><?= e($fullName) ?></strong>
                                <span>@<?= e($profile['username']) ?> · <?= e(date('M j, Y g:i A', strtotime($post['created_at']))) ?></span>
                            </div>
                            <?php if (trim($post['content']) !== ''): ?><p><?= nl2br(e($post['content'])) ?></p><?php endif; ?>
                            <?php if (trim((string) $post['image']) !== ''): ?><img src="../<?= e($post['image']) ?>" alt="Image from <?= e($fullName) ?>'s post"><?php endif; ?>
                            <div class="profile-post-engagement">
                                <span><?= (int) $post['like_count'] ?> <?= (int) $post['like_count'] === 1 ? 'like' : 'likes' ?></span>
                                <span><?= (int) $post['comment_count'] ?> <?= (int) $post['comment_count'] === 1 ? 'comment' : 'comments' ?></span>
                                <span><?= (int) $post['share_count'] ?> <?= (int) $post['share_count'] === 1 ? 'share' : 'shares' ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <nav class="mobile-bottom-nav" aria-label="Main navigation">
        <a href="../dashboard.php"><span aria-hidden="true">⌂</span><small>Home</small></a>
        <a href="../community/community.php"><span aria-hidden="true">◌</span><small>Community</small></a>
        <a href="../support/support.php"><span aria-hidden="true">✚</span><small>Support</small></a>
        <a href="../bookings/bookings.php"><span aria-hidden="true">□</span><small>Bookings</small></a>
        <a class="is-active" href="../account/account.php"><span aria-hidden="true">◎</span><small>Account</small></a>
    </nav>
</body>

</html>