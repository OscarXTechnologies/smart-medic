<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function communityImageUrl(string $image): string
{
    return $image !== '' && preg_match('/^(https?:\/\/|\/)/i', $image)
        ? $image
        : '../' . ltrim($image, '/');
}

$userId = (int) $_SESSION['user_id'];
$detailCommunity = null;
$detailMembers = [];
$communityPosts = [];
$detailIsMember = false;
$detailIsCreator = false;
$status = (string) ($_GET['status'] ?? '');

$search = trim((string) ($_GET['q'] ?? ''));
if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

$requestedCommunityId = filter_input(INPUT_GET, 'community_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$requestedCommunityId = $requestedCommunityId === false || $requestedCommunityId === null
    ? 0
    : (int) $requestedCommunityId;

$communities = [];

if ($requestedCommunityId > 0) {
    $detailStatement = $conn->prepare(
        'SELECT c.id, c.name, c.description, c.image, c.created_at, c.created_by,
                creator.first_name AS creator_first_name, creator.last_name AS creator_last_name,
                creator.username AS creator_username,
                (SELECT COUNT(*) FROM community_members WHERE community_id = c.id) AS member_count,
                EXISTS (
                    SELECT 1 FROM community_members AS current_member
                    WHERE current_member.community_id = c.id AND current_member.user_id = ?
                ) AS is_member
         FROM communities AS c
         INNER JOIN users AS creator ON creator.id = c.created_by
         WHERE c.id = ?
         LIMIT 1'
    );

    if ($detailStatement) {
        $detailStatement->bind_param('ii', $userId, $requestedCommunityId);
        $detailStatement->execute();
        $detailResult = $detailStatement->get_result();
        $detailCommunity = $detailResult->fetch_assoc() ?: null;
        $detailStatement->close();
    }

    if ($detailCommunity) {
        $detailIsMember = (int) $detailCommunity['is_member'] === 1;
        $detailIsCreator = (int) $detailCommunity['created_by'] === $userId;
        $memberStatement = $conn->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.username, u.profile_picture,
                    cm.user_id = ? AS is_creator
             FROM community_members AS cm
             INNER JOIN users AS u ON u.id = cm.user_id
             INNER JOIN communities AS c ON c.id = cm.community_id
             WHERE cm.community_id = ?
             ORDER BY is_creator DESC, cm.joined_at ASC, cm.id ASC
             LIMIT 100'
        );

        if ($memberStatement) {
            $memberStatement->bind_param('ii', $detailCommunity['created_by'], $requestedCommunityId);
            $memberStatement->execute();
            $memberResult = $memberStatement->get_result();
            while ($member = $memberResult->fetch_assoc()) {
                $detailMembers[] = $member;
            }
            $memberStatement->close();
        }

        $postStatement = $conn->prepare(
            'SELECT p.id, p.user_id, p.content, p.image, p.created_at,
                    author.first_name, author.last_name, author.username, author.profile_picture,
                    (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS like_count,
                    (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count,
                    (SELECT COUNT(*) FROM post_shares WHERE post_id = p.id) AS share_count,
                    EXISTS (
                        SELECT 1 FROM post_likes AS current_like
                        WHERE current_like.post_id = p.id AND current_like.user_id = ?
                    ) AS user_liked
             FROM posts AS p
             INNER JOIN users AS author ON author.id = p.user_id
             WHERE p.community_id = ?
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT 100'
        );

        if ($postStatement) {
            $postStatement->bind_param('ii', $userId, $requestedCommunityId);
            $postStatement->execute();
            $postResult = $postStatement->get_result();
            while ($post = $postResult->fetch_assoc()) {
                $communityPosts[] = $post;
            }
            $postStatement->close();
        }
    }
} else {
    $searchTerm = '%' . $search . '%';
    $communityStatement = $conn->prepare(
        'SELECT c.id, c.name, c.description, c.image, c.created_at,
                COUNT(cm.id) AS member_count,
                EXISTS (
                    SELECT 1 FROM community_members AS current_member
                    WHERE current_member.community_id = c.id AND current_member.user_id = ?
                ) AS is_member
         FROM communities AS c
         LEFT JOIN community_members AS cm ON cm.community_id = c.id
         WHERE c.name LIKE ? OR c.description LIKE ?
         GROUP BY c.id, c.name, c.description, c.image, c.created_at
         ORDER BY c.created_at DESC, c.id DESC'
    );

    if ($communityStatement) {
        $communityStatement->bind_param('iss', $userId, $searchTerm, $searchTerm);
        $communityStatement->execute();
        $communityResult = $communityStatement->get_result();

        while ($community = $communityResult->fetch_assoc()) {
            $communities[] = $community;
        }

        $communityStatement->close();
    }
}

$currentInitial = strtoupper(substr((string) ($_SESSION['username'] ?? 'S'), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communities | Smart Medic</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="dashboard-body community-page">
    <header class="dashboard-header">
        <a class="brand-mark" href="../dashboard.php" aria-label="Smart Medic home">
            <span class="brand-symbol" aria-hidden="true">+</span>
            <span>Smart Medic</span>
        </a>
        <nav class="desktop-main-nav" aria-label="Main navigation">
            <a href="../dashboard.php">Home</a>
            <a class="is-active" href="community.php">Community</a>
            <a href="../support/support.php">Support</a>
            <a href="../bookings/bookings.php">Bookings</a>
            <a href="../account/account.php">Account</a>
        </nav>
        <a class="header-avatar" href="../profile.php" aria-label="Open profile"><?= e($currentInitial) ?></a>
    </header>

    <main class="community-shell">
        <section class="community-card" aria-labelledby="community-title">
            <?php if ($requestedCommunityId > 0): ?>
                <?php if (!$detailCommunity): ?>
                    <header class="community-heading">
                        <div>
                            <p class="eyebrow">Community</p>
                            <h1 id="community-title">Community not found</h1>
                            <p>This community may have been removed or is no longer available.</p>
                        </div>
                        <a class="community-create-button" href="community.php">Back to Communities</a>
                    </header>
                <?php else: ?>
                    <?php
                    $detailImage = trim((string) ($detailCommunity['image'] ?? ''));
                    $detailDescription = trim((string) ($detailCommunity['description'] ?? ''));
                    $detailCreatorName = trim($detailCommunity['creator_first_name'] . ' ' . $detailCommunity['creator_last_name']);
                    ?>
                    <a class="back-link" href="community.php">&larr; Back to Communities</a>
                    <header class="community-detail-heading">
                        <?php if ($detailImage !== ''): ?>
                            <img class="community-detail-image" src="<?= e(communityImageUrl($detailImage)) ?>" alt="">
                        <?php else: ?>
                            <div class="community-detail-image community-image-placeholder" aria-hidden="true">+</div>
                        <?php endif; ?>
                        <div>
                            <p class="eyebrow">Community</p>
                            <h1 id="community-title"><?= e((string) $detailCommunity['name']) ?></h1>
                            <p><?= nl2br(e($detailDescription)) ?></p>
                            <div class="community-meta">
                                <span><?= (int) $detailCommunity['member_count'] ?> members</span>
                                <span>Created <?= e(date('M j, Y', strtotime((string) $detailCommunity['created_at']))) ?></span>
                                <span>Created by <a href="../users/profile.php?user_id=<?= (int) $detailCommunity['created_by'] ?>"><?= e($detailCreatorName) ?></a> (@<?= e((string) $detailCommunity['creator_username']) ?>)</span>
                            </div>
                        </div>
                    </header>

                    <?php if ($status === 'creator-cannot-leave'): ?>
                        <div class="alert alert-error" role="alert">Community creators cannot leave their own community.</div>
                    <?php elseif ($status === 'joined'): ?>
                        <div class="alert alert-success" role="status">You joined this community.</div>
                    <?php elseif ($status === 'left'): ?>
                        <div class="alert alert-success" role="status">You left this community.</div>
                    <?php elseif ($status === 'already-joined'): ?>
                        <div class="alert alert-success" role="status">You are already a member of this community.</div>
                    <?php elseif ($status === 'member-removed'): ?>
                        <div class="alert alert-success" role="status">The member was removed from this community.</div>
                    <?php elseif ($status === 'cannot-remove-owner'): ?>
                        <div class="alert alert-error" role="alert">Community owners cannot be removed from their own community.</div>
                    <?php elseif ($status === 'remove-forbidden'): ?>
                        <div class="alert alert-error" role="alert">Only the community owner can remove members.</div>
                    <?php elseif ($status === 'member-not-found'): ?>
                        <div class="alert alert-error" role="alert">That user is not a member of this community.</div>
                    <?php elseif ($status === 'remove-error'): ?>
                        <div class="alert alert-error" role="alert">The member could not be removed. Please try again.</div>
                    <?php endif; ?>

                    <div class="community-detail-actions">
                        <?php if ($detailIsCreator): ?>
                            <span class="community-owner-note">You are the community creator.</span>
                        <?php elseif ($detailIsMember): ?>
                            <form method="post" action="leave.php">
                                <input type="hidden" name="community_id" value="<?= $requestedCommunityId ?>">
                                <button type="submit" class="community-leave-button">Leave Community</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="join.php">
                                <input type="hidden" name="community_id" value="<?= $requestedCommunityId ?>">
                                <button type="submit" class="community-join-button">Join Community</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if ($detailIsMember): ?>
                        <section class="community-post-composer" aria-labelledby="community-post-title">
                            <div class="dashboard-section-heading">
                                <div>
                                    <p class="eyebrow">Share with this community</p>
                                    <h2 id="community-post-title">Create a community post</h2>
                                </div>
                            </div>
                            <form method="post" action="create_post.php" enctype="multipart/form-data" class="community-post-form">
                                <input type="hidden" name="community_id" value="<?= $requestedCommunityId ?>">
                                <label class="sr-only" for="community-post-content">Post text</label>
                                <textarea id="community-post-content" name="content" rows="4" maxlength="10000" placeholder="Share something with this community..." required></textarea>
                                <div class="community-post-form-actions">
                                    <label class="image-picker" for="community-post-image"><span aria-hidden="true">＋</span> Add image</label>
                                    <input class="sr-only" type="file" id="community-post-image" name="image" accept="image/jpeg,image/png,image/webp">
                                    <span class="file-name">Optional · JPG, PNG or WEBP</span>
                                    <button type="submit" class="button-primary">Post</button>
                                </div>
                            </form>
                        </section>
                    <?php else: ?>
                        <div class="community-participation-note">Join this community to participate in discussions.</div>
                    <?php endif; ?>

                    <section class="community-posts-section" aria-labelledby="community-posts-title">
                        <div class="dashboard-section-heading">
                            <div>
                                <p class="eyebrow">Community feed</p>
                                <h2 id="community-posts-title">Posts</h2>
                            </div>
                        </div>
                        <?php if (!$communityPosts): ?>
                            <div class="community-empty">No community posts yet.</div>
                        <?php else: ?>
                            <div class="post-list">
                                <?php foreach ($communityPosts as $post): ?>
                                    <?php
                                    $postId = (int) $post['id'];
                                    $authorName = trim($post['first_name'] . ' ' . $post['last_name']);
                                    $authorPicture = trim((string) ($post['profile_picture'] ?? ''));
                                    $postImage = trim((string) ($post['image'] ?? ''));
                                    $postReturn = 'community.php?community_id=' . $requestedCommunityId;
                                    ?>
                                    <article class="feed-post" id="community-post-<?= $postId ?>">
                                        <header class="feed-post-header">
                                            <a class="profile-avatar feed-post-avatar" href="../users/profile.php?user_id=<?= (int) $post['user_id'] ?>" aria-label="View <?= e($authorName) ?>'s profile">
                                                <?php if ($authorPicture !== ''): ?>
                                                    <img src="<?= e('../' . ltrim($authorPicture, '/')) ?>" alt="">
                                                <?php else: ?>
                                                    <?= e(strtoupper(substr((string) $post['first_name'], 0, 1))) ?>
                                                <?php endif; ?>
                                            </a>
                                            <div>
                                                <strong><a href="../users/profile.php?user_id=<?= (int) $post['user_id'] ?>"><?= e($authorName) ?></a></strong>
                                                <span><a href="../users/profile.php?user_id=<?= (int) $post['user_id'] ?>">@<?= e((string) $post['username']) ?></a> · <?= e(date('M j, Y g:i A', strtotime((string) $post['created_at']))) ?></span>
                                            </div>
                                        </header>
                                        <?php if (trim((string) $post['content']) !== ''): ?>
                                            <p class="feed-post-text"><?= nl2br(e((string) $post['content'])) ?></p>
                                        <?php endif; ?>
                                        <?php if ($postImage !== ''): ?>
                                            <img class="feed-post-image" src="<?= e('../' . ltrim($postImage, '/')) ?>" alt="Image shared by <?= e($authorName) ?>">
                                        <?php endif; ?>
                                        <footer class="feed-post-actions">
                                            <form method="post" action="../posts/like_post.php">
                                                <input type="hidden" name="post_id" value="<?= $postId ?>">
                                                <input type="hidden" name="return_to" value="<?= e($postReturn) ?>">
                                                <button type="submit" class="post-like-button <?= (int) $post['user_liked'] === 1 ? 'is-liked' : '' ?>">Like · <?= (int) $post['like_count'] ?></button>
                                            </form>
                                            <span>Comments · <?= (int) $post['comment_count'] ?></span>
                                            <form method="post" action="../posts/share_post.php">
                                                <input type="hidden" name="post_id" value="<?= $postId ?>">
                                                <input type="hidden" name="return_to" value="<?= e($postReturn) ?>">
                                                <button type="submit" class="post-share-button">Share · <?= (int) $post['share_count'] ?></button>
                                            </form>
                                            <?php if ((int) $post['user_id'] === $userId): ?>
                                                <a class="post-management-button" href="../posts/edit_post.php?id=<?= $postId ?>&return_to=<?= e(urlencode($postReturn)) ?>">Edit</a>
                                                <form method="post" action="../posts/delete_post.php" onsubmit="return confirm('Delete this post permanently?');">
                                                    <input type="hidden" name="post_id" value="<?= $postId ?>">
                                                    <input type="hidden" name="return_to" value="<?= e($postReturn) ?>">
                                                    <button type="submit" class="post-management-button post-delete-button">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </footer>
                                        <?php if ($detailIsMember): ?>
                                            <form method="post" action="../posts/comment.php" class="comment-form">
                                                <input type="hidden" name="post_id" value="<?= $postId ?>">
                                                <input type="hidden" name="return_to" value="<?= e($postReturn) ?>">
                                                <label class="sr-only" for="comment-<?= $postId ?>">Comment on this post</label>
                                                <input type="text" id="comment-<?= $postId ?>" name="comment" maxlength="2000" placeholder="Write a comment..." required>
                                                <button type="submit" class="comment-submit">Comment</button>
                                            </form>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="community-members-section" aria-labelledby="community-members-title">
                        <div class="dashboard-section-heading">
                            <div>
                                <p class="eyebrow">People here</p>
                                <h2 id="community-members-title">Members</h2>
                            </div>
                        </div>
                        <?php if (!$detailMembers): ?>
                            <div class="community-empty">No members found.</div>
                        <?php else: ?>
                            <div class="community-members-list">
                                <?php foreach ($detailMembers as $member): ?>
                                    <?php
                                    $memberName = trim($member['first_name'] . ' ' . $member['last_name']);
                                    $memberPicture = trim((string) ($member['profile_picture'] ?? ''));
                                    $memberPictureUrl = $memberPicture !== '' ? '../' . ltrim($memberPicture, '/') : '';
                                    ?>
                                    <a class="community-member" href="../users/profile.php?user_id=<?= (int) $member['id'] ?>">
                                        <span class="profile-avatar community-member-avatar">
                                            <?php if ($memberPictureUrl !== ''): ?>
                                                <img src="<?= e($memberPictureUrl) ?>" alt="">
                                            <?php else: ?>
                                                <?= e(strtoupper(substr((string) $member['first_name'], 0, 1))) ?>
                                            <?php endif; ?>
                                        </span>
                                        <span class="community-member-copy">
                                            <strong><?= e($memberName) ?></strong>
                                            <span>@<?= e((string) $member['username']) ?></span>
                                        </span>
                                        <?php if ((int) $member['is_creator'] === 1): ?>
                                            <span class="community-member-role">Community Owner</span>
                                        <?php elseif ($detailIsCreator): ?>
                                            <form method="post" action="remove_member.php" class="community-remove-member-form">
                                                <input type="hidden" name="community_id" value="<?= $requestedCommunityId ?>">
                                                <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
                                                <button type="submit" class="community-remove-member-button" onclick="return confirm('Remove this member from the community?');">Remove</button>
                                            </form>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            <?php else: ?>
                <header class="community-heading">
                    <div>
                        <p class="eyebrow">Find your people</p>
                        <h1 id="community-title">Communities</h1>
                        <p>Connect with people, share experiences and learn about health together.</p>
                    </div>
                    <a class="community-create-button" href="create.php">+ Create Community</a>
                </header>

                <form class="community-search" method="get" action="community.php" role="search">
                    <label class="sr-only" for="community-search-input">Search communities</label>
                    <input type="search" id="community-search-input" name="q" value="<?= e($search) ?>" placeholder="Search communities..." autocomplete="off">
                    <button type="submit" class="button-primary">Search</button>
                </form>

                <?php if (!$communities): ?>
                    <div class="community-empty">No communities found.</div>
                <?php else: ?>
                    <div class="community-grid">
                        <?php foreach ($communities as $community): ?>
                            <?php
                            $communityId = (int) $community['id'];
                            $communityImage = trim((string) ($community['image'] ?? ''));
                            $communityDescription = trim((string) ($community['description'] ?? ''));
                            ?>
                            <article class="community-item">
                                <?php if ($communityImage !== ''): ?>
                                    <img class="community-image" src="<?= e(communityImageUrl($communityImage)) ?>" alt="">
                                <?php else: ?>
                                    <div class="community-image community-image-placeholder" aria-hidden="true">+</div>
                                <?php endif; ?>
                                <div class="community-item-content">
                                    <h2><?= e((string) $community['name']) ?></h2>
                                    <?php if ($communityDescription !== ''): ?>
                                        <p><?= nl2br(e($communityDescription)) ?></p>
                                    <?php endif; ?>
                                    <div class="community-meta">
                                        <span><?= (int) $community['member_count'] ?> members</span>
                                        <time datetime="<?= e((string) $community['created_at']) ?>">Created <?= e(date('M j, Y', strtotime((string) $community['created_at']))) ?></time>
                                    </div>
                                    <?php if ((int) $community['is_member'] === 1): ?>
                                        <span class="community-joined-label">Joined</span>
                                    <?php else: ?>
                                        <a class="community-join-link" href="community.php?community_id=<?= $communityId ?>">Join Community</a>
                                    <?php endif; ?>
                                    <a class="community-view-link" href="community.php?community_id=<?= $communityId ?>">View Community</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>

    <nav class="mobile-bottom-nav" aria-label="Main navigation">
        <a href="../dashboard.php"><span aria-hidden="true">⌂</span><small>Home</small></a>
        <a class="is-active" href="community.php"><span aria-hidden="true">◌</span><small>Community</small></a>
        <a href="../support/support.php"><span aria-hidden="true">✚</span><small>Support</small></a>
        <a href="../bookings/bookings.php"><span aria-hidden="true">□</span><small>Bookings</small></a>
        <a href="../account/account.php"><span aria-hidden="true">◎</span><small>Account</small></a>
    </nav>
</body>

</html>