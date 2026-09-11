<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/config.php';

$userId = (int) $_SESSION['user_id'];
$statement = $conn->prepare(
    'SELECT first_name, last_name, username, profile_picture
	 FROM users
	 WHERE id = ?
	 LIMIT 1'
);

if (!$statement) {
    header('Location: logout.php');
    exit;
}

$statement->bind_param('i', $userId);
$statement->execute();
$result = $statement->get_result();
$user = $result->fetch_assoc();
$statement->close();

if (!$user) {
    header('Location: logout.php');
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$fullName = trim($user['first_name'] . ' ' . $user['last_name']);
$profilePicture = trim((string) ($user['profile_picture'] ?? ''));
$profilePictureUrl = $profilePicture !== '' ? e($profilePicture) : '';

$feedStatement = $conn->prepare(
    'SELECT feed.feed_event_id, feed.original_post_id, feed.is_shared,
         feed.shared_at, feed.sharer_first_name, feed.sharer_last_name,
         feed.sharer_username, feed.sharer_profile_picture,
            p.user_id AS original_author_id, p.content, p.image, p.created_at AS original_created_at,
         author.first_name, author.last_name, author.username, author.profile_picture,
         (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS like_count,
         (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count,
         CASE WHEN EXISTS (
          SELECT 1 FROM post_likes
          WHERE post_id = p.id AND user_id = ?
         ) THEN 1 ELSE 0 END AS user_liked,
         (SELECT COUNT(*) FROM post_shares WHERE post_id = p.id) AS share_count
     FROM (
     SELECT p.id AS feed_event_id, p.id AS original_post_id, 0 AS is_shared,
         p.created_at AS shared_at,
         NULL AS sharer_first_name, NULL AS sharer_last_name,
         NULL AS sharer_username, NULL AS sharer_profile_picture
     FROM posts AS p
     UNION ALL
     SELECT s.id AS feed_event_id, s.post_id AS original_post_id, 1 AS is_shared,
         s.created_at AS shared_at,
         sharer.first_name, sharer.last_name, sharer.username, sharer.profile_picture
     FROM post_shares AS s
     INNER JOIN users AS sharer ON sharer.id = s.user_id
     ) AS feed
     INNER JOIN posts AS p ON p.id = feed.original_post_id
     INNER JOIN users AS author ON author.id = p.user_id
     ORDER BY feed.shared_at DESC, feed.feed_event_id DESC'
);
$feedPosts = [];

if ($feedStatement) {
    $feedStatement->bind_param('i', $userId);
    $feedStatement->execute();
    $feedResult = $feedStatement->get_result();
    while ($post = $feedResult->fetch_assoc()) {
        $feedPosts[] = $post;
    }
    $feedStatement->close();
}

$commentsByPost = [];
foreach ($feedPosts as $feedPost) {
    if (isset($commentsByPost[$feedPost['original_post_id']])) {
        continue;
    }

    $commentStatement = $conn->prepare(
        'SELECT c.user_id, c.comment, c.created_at,
                commenter.first_name, commenter.last_name, commenter.username, commenter.profile_picture
         FROM comments AS c
         INNER JOIN users AS commenter ON commenter.id = c.user_id
         WHERE c.post_id = ?
         ORDER BY c.created_at ASC, c.id ASC'
    );

    if (!$commentStatement) {
        continue;
    }

    $commentStatement->bind_param('i', $feedPost['original_post_id']);
    $commentStatement->execute();
    $commentResult = $commentStatement->get_result();
    $commentsByPost[$feedPost['original_post_id']] = [];

    while ($comment = $commentResult->fetch_assoc()) {
        $commentsByPost[$feedPost['original_post_id']][] = $comment;
    }

    $commentStatement->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home | Smart Medic</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="dashboard-body">
    <header class="dashboard-header">
        <a class="brand-mark" href="dashboard.php" aria-label="Smart Medic home">
            <span class="brand-symbol" aria-hidden="true">+</span>
            <span>Smart Medic</span>
        </a>
        <label class="dashboard-search">
            <span class="sr-only">Search Smart Medic</span>
            <input type="search" placeholder="Search Smart Medic...">
        </label>
        <nav class="desktop-main-nav" aria-label="Main navigation">
            <a class="is-active" href="dashboard.php">Home</a>
            <a href="community/community.php">Community</a>
            <a href="support/support.php">Support</a>
            <a href="bookings/bookings.php">Bookings</a>
            <a href="account/account.php">Account</a>
        </nav>
        <a class="header-avatar" href="profile.php" aria-label="Open profile">
            <?php if ($profilePictureUrl !== ''): ?>
                <img src="<?= $profilePictureUrl ?>" alt="">
            <?php else: ?>
                <?= e(strtoupper(substr($user['first_name'], 0, 1))) ?>
            <?php endif; ?>
        </a>
    </header>

    <main class="dashboard-shell">
        <aside class="dashboard-sidebar" aria-label="Personal navigation">
            <div class="sidebar-profile">
                <div class="profile-avatar profile-avatar-large">
                    <?php if ($profilePictureUrl !== ''): ?>
                        <img src="<?= $profilePictureUrl ?>" alt="">
                    <?php else: ?>
                        <?= e(strtoupper(substr($user['first_name'], 0, 1))) ?>
                    <?php endif; ?>
                </div>
                <strong><?= e($fullName) ?></strong>
                <span>@<?= e($user['username']) ?></span>
            </div>
            <nav class="sidebar-links" aria-label="Account shortcuts">
                <a class="is-active" href="dashboard.php"><span aria-hidden="true">⌂</span> Home</a>
                <a href="profile.php"><span aria-hidden="true">◎</span> My Profile</a>
                <a href="community/community.php"><span aria-hidden="true">◌</span> Communities</a>
                <a href="#"><span aria-hidden="true">☆</span> Saved Posts</a>
                <a href="#"><span aria-hidden="true">＋</span> Health Records</a>
                <a href="account/account.php"><span aria-hidden="true">⚙</span> Settings</a>
            </nav>
            <a class="sidebar-logout" href="logout.php">Log out</a>
        </aside>

        <section class="dashboard-content" aria-labelledby="dashboard-title">
            <div class="welcome-panel">
                <div>
                    <p class="eyebrow">Your home space</p>
                    <h1 id="dashboard-title">Welcome to Smart Medic, <?= e($user['first_name']) ?>.</h1>
                    <p>Stay close to the support, services, and care planning that matter to you.</p>
                </div>
                <div class="welcome-mark" aria-hidden="true">+</div>
            </div>

            <section class="dashboard-create-post" aria-labelledby="create-post-title">
                <div class="dashboard-post-avatar profile-avatar" aria-hidden="true">
                    <?php if ($profilePictureUrl !== ''): ?>
                        <img src="<?= $profilePictureUrl ?>" alt="">
                    <?php else: ?>
                        <?= e(strtoupper(substr($user['first_name'], 0, 1))) ?>
                    <?php endif; ?>
                </div>
                <div class="dashboard-post-content">
                    <div class="dashboard-post-heading">
                        <div>
                            <p class="eyebrow">Community</p>
                            <h2 id="create-post-title">Create Post</h2>
                        </div>
                        <span class="dashboard-post-author">@<?= e($user['username']) ?></span>
                    </div>
                    <form method="POST" action="posts/create_post.php" enctype="multipart/form-data" class="dashboard-create-form">
                        <label class="sr-only" for="dashboard-post-content">Write a post</label>
                        <textarea id="dashboard-post-content" name="content" rows="4" maxlength="10000" placeholder="Share something about health..." required></textarea>
                        <div class="dashboard-create-actions">
                            <label class="dashboard-image-button" for="dashboard-post-image">
                                <span aria-hidden="true">＋</span> Add image
                            </label>
                            <input class="sr-only" type="file" id="dashboard-post-image" name="image" accept="image/jpeg,image/png,image/webp">
                            <span class="dashboard-file-name" id="dashboard-file-name">Optional · JPG, PNG or WEBP</span>
                            <button type="submit" class="button-primary">Publish</button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="dashboard-feed" aria-labelledby="feed-title">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="eyebrow">Community feed</p>
                        <h2 id="feed-title">Latest posts</h2>
                    </div>
                </div>

                <?php if (!$feedPosts): ?>
                    <div class="empty-feed">No posts yet. Be the first to share something.</div>
                <?php else: ?>
                    <div class="post-list">
                        <?php foreach ($feedPosts as $post): ?>
                            <?php
                            $authorName = trim($post['first_name'] . ' ' . $post['last_name']);
                            $authorPicture = trim((string) ($post['profile_picture'] ?? ''));
                            $postImage = trim((string) ($post['image'] ?? ''));
                            $originalPostId = (int) $post['original_post_id'];
                            ?>
                            <article class="feed-post" id="post-<?= $originalPostId ?>">
                                <?php if ((int) $post['is_shared'] === 1): ?>
                                    <div class="shared-post-label">
                                        <span aria-hidden="true">↗</span>
                                        <?= e(trim($post['sharer_first_name'] . ' ' . $post['sharer_last_name'])) ?>
                                        (@<?= e($post['sharer_username']) ?>) shared this post
                                        <time datetime="<?= e($post['shared_at']) ?>">· <?= e(date('M j, Y g:i A', strtotime($post['shared_at']))) ?></time>
                                    </div>
                                <?php endif; ?>
                                <header class="feed-post-header">
                                    <a class="profile-avatar feed-post-avatar" href="users/profile.php?user_id=<?= (int) $post['original_author_id'] ?>" aria-label="View <?= e($authorName) ?>'s profile">
                                        <?php if ($authorPicture !== ''): ?>
                                            <img src="<?= e($authorPicture) ?>" alt="">
                                        <?php else: ?>
                                            <?= e(strtoupper(substr($post['first_name'], 0, 1))) ?>
                                        <?php endif; ?>
                                    </a>
                                    <div>
                                        <strong><a href="users/profile.php?user_id=<?= (int) $post['original_author_id'] ?>"><?= e($authorName) ?></a></strong>
                                        <span><a href="users/profile.php?user_id=<?= (int) $post['original_author_id'] ?>">@<?= e($post['username']) ?></a> · <?= e(date('M j, Y g:i A', strtotime($post['original_created_at']))) ?></span>
                                    </div>
                                </header>
                                <?php if (trim($post['content']) !== ''): ?>
                                    <p class="feed-post-text"><?= nl2br(e($post['content'])) ?></p>
                                <?php endif; ?>
                                <?php if ($postImage !== ''): ?>
                                    <img class="feed-post-image" src="<?= e($postImage) ?>" alt="Image shared by <?= e($authorName) ?>">
                                <?php endif; ?>
                                <footer class="feed-post-actions">
                                    <form method="POST" action="posts/like_post.php">
                                        <input type="hidden" name="post_id" value="<?= $originalPostId ?>">
                                        <button type="submit" class="post-like-button <?= (int) $post['user_liked'] === 1 ? 'is-liked' : '' ?>">
                                            <?= (int) $post['user_liked'] === 1 ? 'Unlike' : 'Like' ?>
                                        </button>
                                    </form>
                                    <span><?= (int) $post['like_count'] ?> <?= (int) $post['like_count'] === 1 ? 'like' : 'likes' ?></span>
                                    <span><?= (int) $post['comment_count'] ?> <?= (int) $post['comment_count'] === 1 ? 'comment' : 'comments' ?></span>
                                    <form method="POST" action="posts/share_post.php">
                                        <input type="hidden" name="post_id" value="<?= $originalPostId ?>">
                                        <button type="submit" class="post-share-button">Share</button>
                                    </form>
                                    <span><?= (int) $post['share_count'] ?> <?= (int) $post['share_count'] === 1 ? 'share' : 'shares' ?></span>
                                    <?php if ((int) $post['is_shared'] === 0 && (int) $post['original_author_id'] === $userId): ?>
                                        <a class="post-management-button" href="posts/edit_post.php?id=<?= $originalPostId ?>">Edit</a>
                                        <form method="POST" action="posts/delete_post.php" onsubmit="return confirm('Delete this post permanently?');">
                                            <input type="hidden" name="post_id" value="<?= $originalPostId ?>">
                                            <button type="submit" class="post-management-button post-delete-button">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </footer>
                                <section class="post-comments" aria-label="Comments on this post">
                                    <?php foreach ($commentsByPost[$originalPostId] ?? [] as $comment): ?>
                                        <?php
                                        $commenterName = trim($comment['first_name'] . ' ' . $comment['last_name']);
                                        $commenterPicture = trim((string) ($comment['profile_picture'] ?? ''));
                                        ?>
                                        <article class="post-comment">
                                            <a class="profile-avatar comment-avatar" href="users/profile.php?user_id=<?= (int) $comment['user_id'] ?>" aria-label="View <?= e($commenterName) ?>'s profile">
                                                <?php if ($commenterPicture !== ''): ?>
                                                    <img src="<?= e($commenterPicture) ?>" alt="">
                                                <?php else: ?>
                                                    <?= e(strtoupper(substr($comment['first_name'], 0, 1))) ?>
                                                <?php endif; ?>
                                            </a>
                                            <div class="comment-body">
                                                <strong><a href="users/profile.php?user_id=<?= (int) $comment['user_id'] ?>"><?= e($commenterName) ?></a></strong>
                                                <span><a href="users/profile.php?user_id=<?= (int) $comment['user_id'] ?>">@<?= e($comment['username']) ?></a> · <?= e(date('M j, Y g:i A', strtotime($comment['created_at']))) ?></span>
                                                <p><?= nl2br(e($comment['comment'])) ?></p>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                    <form method="POST" action="posts/comment.php" class="comment-form">
                                        <input type="hidden" name="post_id" value="<?= $originalPostId ?>">
                                        <label class="sr-only" for="comment-<?= $originalPostId ?>-<?= (int) $post['feed_event_id'] ?>">Write a comment</label>
                                        <input type="text" id="comment-<?= $originalPostId ?>-<?= (int) $post['feed_event_id'] ?>" name="comment" maxlength="2000" placeholder="Write a comment..." required>
                                        <button type="submit" class="comment-submit">Comment</button>
                                    </form>
                                </section>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <div class="dashboard-section-heading">
                <div>
                    <p class="eyebrow">Your overview</p>
                    <h2>What would you like to explore?</h2>
                </div>
            </div>

            <div class="dashboard-cards">
                <a class="dashboard-card card-services" href="support/support.php">
                    <span class="card-icon" aria-hidden="true">✚</span>
                    <span>
                        <strong>Health Services</strong>
                        <small>Explore available support and care resources.</small>
                    </span>
                    <span class="card-arrow" aria-hidden="true">→</span>
                </a>
                <a class="dashboard-card card-community" href="community/community.php">
                    <span class="card-icon" aria-hidden="true">◌</span>
                    <span>
                        <strong>Community</strong>
                        <small>Connect with people and health conversations.</small>
                    </span>
                    <span class="card-arrow" aria-hidden="true">→</span>
                </a>
                <a class="dashboard-card card-bookings" href="bookings/bookings.php">
                    <span class="card-icon" aria-hidden="true">□</span>
                    <span>
                        <strong>Upcoming Bookings</strong>
                        <small>Appointments and services will appear here.</small>
                    </span>
                    <span class="card-arrow" aria-hidden="true">→</span>
                </a>
                <a class="dashboard-card card-reminders" href="account/account.php">
                    <span class="card-icon" aria-hidden="true">◇</span>
                    <span>
                        <strong>Health Reminders</strong>
                        <small>Your personal reminders will appear here.</small>
                    </span>
                    <span class="card-arrow" aria-hidden="true">→</span>
                </a>
            </div>

            <div class="dashboard-lower-grid">
                <section class="dashboard-placeholder">
                    <p class="eyebrow">Home feed</p>
                    <h2>Your health space is ready.</h2>
                    <p>Community updates and personal activity will appear here as you use Smart Medic.</p>
                </section>
                <aside class="tip-card">
                    <p class="eyebrow">Health tip</p>
                    <h2>Make room for your next step.</h2>
                    <p>Use your dashboard to keep important care plans and support options easy to find.</p>
                </aside>
            </div>
        </section>

        <aside class="dashboard-rightbar" aria-label="Dashboard information">
            <section class="side-panel">
                <div class="panel-heading">
                    <h2>Health reminders</h2><span aria-hidden="true">◇</span>
                </div>
                <p>Your personal reminders will appear here.</p>
            </section>
            <section class="side-panel">
                <div class="panel-heading">
                    <h2>Suggested users</h2><span aria-hidden="true">◎</span>
                </div>
                <p>People and care professionals you may know will appear here.</p>
            </section>
            <section class="side-panel">
                <div class="panel-heading">
                    <h2>Suggested communities</h2><span aria-hidden="true">◌</span>
                </div>
                <p>Find communities that match your interests.</p>
            </section>
            <section class="side-panel side-tip">
                <div class="panel-heading">
                    <h2>Health tips</h2><span aria-hidden="true">✦</span>
                </div>
                <p>Personalized guidance will appear here as this space grows.</p>
            </section>
        </aside>
    </main>

    <nav class="mobile-bottom-nav" aria-label="Main navigation">
        <a class="is-active" href="dashboard.php"><span aria-hidden="true">⌂</span><small>Home</small></a>
        <a href="community/community.php"><span aria-hidden="true">◌</span><small>Community</small></a>
        <a href="support/support.php"><span aria-hidden="true">✚</span><small>Support</small></a>
        <a href="bookings/bookings.php"><span aria-hidden="true">□</span><small>Bookings</small></a>
        <a href="account/account.php"><span aria-hidden="true">◎</span><small>Account</small></a>
    </nav>
    <script>
        document.getElementById('dashboard-post-image').addEventListener('change', function() {
            var fileName = this.files.length ? this.files[0].name : 'Optional · JPG, PNG or WEBP';
            document.getElementById('dashboard-file-name').textContent = fileName;
        });
    </script>
</body>

</html>