<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/notifications.php';

$userId = (int) $_SESSION['user_id'];
$selectedUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$selectedUser = null;
$conversations = [];
$conversationMessages = [];
$availableUsers = [];
$unreadCount = 0;
$unreadNotificationCount = getUnreadNotificationCount($conn, $userId);

$usersStatement = $conn->prepare(
    'SELECT id, first_name, last_name, username, role, profile_picture
     FROM users
     WHERE id <> ?
     ORDER BY first_name ASC, last_name ASC, username ASC'
);

if ($usersStatement) {
    $usersStatement->bind_param('i', $userId);
    $usersStatement->execute();
    $usersResult = $usersStatement->get_result();
    while ($availableUser = $usersResult->fetch_assoc()) {
        $availableUsers[] = $availableUser;
    }
    $usersStatement->close();
}

$conversationStatement = $conn->prepare(
    'SELECT partner.id, partner.first_name, partner.last_name, partner.username, partner.profile_picture,
			latest_message.message AS latest_message, latest_message.created_at AS latest_created_at
            , (SELECT COUNT(*) FROM messages AS unread
               WHERE unread.sender_id = partner.id
                 AND unread.receiver_id = ?
                 AND unread.is_read = 0) AS unread_count
	 FROM users AS partner
	 INNER JOIN (
		 SELECT conversation.partner_id, conversation.latest_id
		 FROM (
			 SELECT receiver_id AS partner_id, MAX(id) AS latest_id
			 FROM messages
			 WHERE sender_id = ?
			 GROUP BY receiver_id
			 UNION ALL
			 SELECT sender_id AS partner_id, MAX(id) AS latest_id
			 FROM messages
			 WHERE receiver_id = ?
			 GROUP BY sender_id
		 ) AS conversation
		 INNER JOIN (
			 SELECT partner_id, MAX(latest_id) AS latest_id
			 FROM (
				 SELECT receiver_id AS partner_id, MAX(id) AS latest_id
				 FROM messages WHERE sender_id = ? GROUP BY receiver_id
				 UNION ALL
				 SELECT sender_id AS partner_id, MAX(id) AS latest_id
				 FROM messages WHERE receiver_id = ? GROUP BY sender_id
			 ) AS grouped_conversations
			 GROUP BY partner_id
		 ) AS latest_per_partner ON latest_per_partner.partner_id = conversation.partner_id
			 AND latest_per_partner.latest_id = conversation.latest_id
	 ) AS conversation_users ON conversation_users.partner_id = partner.id
	 INNER JOIN messages AS latest_message ON latest_message.id = conversation_users.latest_id
	 ORDER BY latest_message.created_at DESC, latest_message.id DESC'
);

if ($conversationStatement) {
    $conversationStatement->bind_param('iiiii', $userId, $userId, $userId, $userId, $userId);
    $conversationStatement->execute();
    $conversationResult = $conversationStatement->get_result();
    while ($conversation = $conversationResult->fetch_assoc()) {
        $conversations[] = $conversation;
    }
    $conversationStatement->close();
}

if ($selectedUserId !== false && $selectedUserId !== null && $selectedUserId !== $userId) {
    $selectedStatement = $conn->prepare(
        'SELECT id, first_name, last_name, username, profile_picture
		 FROM users WHERE id = ? LIMIT 1'
    );

    if ($selectedStatement) {
        $selectedStatement->bind_param('i', $selectedUserId);
        $selectedStatement->execute();
        $selectedUser = $selectedStatement->get_result()->fetch_assoc();
        $selectedStatement->close();
    }

    if ($selectedUser) {
        $markReadStatement = $conn->prepare(
            'UPDATE messages SET is_read = 1
             WHERE sender_id = ? AND receiver_id = ? AND is_read = 0'
        );
        if ($markReadStatement) {
            $markReadStatement->bind_param('ii', $selectedUserId, $userId);
            $markReadStatement->execute();
            $markReadStatement->close();
        }

        $messageStatement = $conn->prepare(
            'SELECT sender_id, receiver_id, message, created_at
			 FROM messages
			 WHERE (sender_id = ? AND receiver_id = ?)
				OR (sender_id = ? AND receiver_id = ?)
			 ORDER BY created_at ASC, id ASC'
        );

        if ($messageStatement) {
            $messageStatement->bind_param('iiii', $userId, $selectedUserId, $selectedUserId, $userId);
            $messageStatement->execute();
            $messageResult = $messageStatement->get_result();
            while ($message = $messageResult->fetch_assoc()) {
                $conversationMessages[] = $message;
            }
            $messageStatement->close();
        }
    }
}

$unreadStatement = $conn->prepare(
    'SELECT COUNT(*) AS unread_count FROM messages WHERE receiver_id = ? AND is_read = 0'
);
if ($unreadStatement) {
    $unreadStatement->bind_param('i', $userId);
    $unreadStatement->execute();
    $unreadRow = $unreadStatement->get_result()->fetch_assoc();
    $unreadCount = (int) ($unreadRow['unread_count'] ?? 0);
    $unreadStatement->close();
}

foreach ($conversations as &$conversation) {
    if ($selectedUserId !== false && (int) $conversation['id'] === (int) $selectedUserId) {
        $conversation['unread_count'] = 0;
    }
}
unset($conversation);


function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$currentInitial = strtoupper(substr((string) ($_SESSION['username'] ?? 'S'), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Smart Medic</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="dashboard-body messages-page">
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
        <a class="messages-header-link" href="messages.php" aria-label="Messages<?= $unreadCount > 0 ? ', ' . $unreadCount . ' unread' : '' ?>">
            <span aria-hidden="true">□</span>
            <?php if ($unreadCount > 0): ?><b><?= $unreadCount > 99 ? '99+' : $unreadCount ?></b><?php endif; ?>
        </a>
        <a class="messages-header-link notification-header-link" href="../notifications/notifications.php" aria-label="Notifications<?= $unreadNotificationCount > 0 ? ', ' . $unreadNotificationCount . ' unread' : '' ?>">
            <span aria-hidden="true">◇</span>
            <?php if ($unreadNotificationCount > 0): ?><b><?= $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount ?></b><?php endif; ?>
        </a>
        <a class="header-avatar" href="../profile.php" aria-label="Open profile"><?= e($currentInitial) ?></a>
    </header>

    <main class="messages-shell">
        <section class="messages-card" aria-label="Private messages">
            <aside class="conversation-list">
                <div class="messages-section-heading">
                    <p class="eyebrow">Private space</p>
                    <h1>Messages <span id="messages-unread-count" class="messages-unread-total" <?= $unreadCount > 0 ? '' : 'hidden' ?>><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span></h1>
                    <button type="button" class="new-message-button" id="new-message-button" aria-expanded="true" aria-controls="new-message-panel">
                        <span aria-hidden="true">＋</span> New Message
                    </button>
                </div>

                <div class="new-message-panel" id="new-message-panel">
                    <label for="user-search">Find a Smart Medic user</label>
                    <input type="search" id="user-search" placeholder="Search by name or username..." autocomplete="off">
                    <?php if (!$availableUsers): ?>
                        <p class="messages-empty-list">No other users are available yet.</p>
                    <?php else: ?>
                        <div class="available-user-list" id="available-user-list">
                            <?php foreach ($availableUsers as $availableUser): ?>
                                <?php $availableName = trim($availableUser['first_name'] . ' ' . $availableUser['last_name']); ?>
                                <a class="available-user" data-user-search="<?= e(strtolower($availableName . ' ' . $availableUser['username'])) ?>" href="messages.php?user_id=<?= (int) $availableUser['id'] ?>">
                                    <div class="profile-avatar conversation-avatar">
                                        <?php if (!empty($availableUser['profile_picture'])): ?>
                                            <img src="../<?= e($availableUser['profile_picture']) ?>" alt="">
                                        <?php else: ?>
                                            <?= e(strtoupper(substr($availableUser['first_name'], 0, 1))) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="conversation-copy">
                                        <strong><?= e($availableName) ?></strong>
                                        <span>@<?= e($availableUser['username']) ?> · <?= e(ucwords(str_replace('_', ' ', $availableUser['role']))) ?></span>
                                    </div>
                                    <span class="available-user-action">Message</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <p class="messages-no-results" id="messages-no-results" hidden>No users match your search.</p>
                    <?php endif; ?>
                </div>

                <?php if (!$conversations): ?>
                    <div class="messages-empty-list">
                        <p>No conversations yet.</p>
                        <p>Start a new conversation with a Smart Medic user.</p>
                        <button type="button" class="start-conversation-button" data-open-new-message>Start Conversation</button>
                    </div>
                <?php else: ?>
                    <div class="conversation-items">
                        <?php foreach ($conversations as $conversation): ?>
                            <?php $conversationName = trim($conversation['first_name'] . ' ' . $conversation['last_name']); ?>
                            <a class="conversation-item <?= $selectedUserId === (int) $conversation['id'] ? 'is-selected' : '' ?> <?= (int) $conversation['unread_count'] > 0 ? 'has-unread' : '' ?>" href="messages.php?user_id=<?= (int) $conversation['id'] ?>">
                                <div class="profile-avatar conversation-avatar">
                                    <?php if (!empty($conversation['profile_picture'])): ?>
                                        <img src="../<?= e($conversation['profile_picture']) ?>" alt="">
                                    <?php else: ?>
                                        <?= e(strtoupper(substr($conversation['first_name'], 0, 1))) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-copy">
                                    <strong><?= e($conversationName) ?></strong>
                                    <span>@<?= e($conversation['username']) ?></span>
                                    <small><?= e(mb_strimwidth($conversation['latest_message'], 0, 48, '...')) ?></small>
                                </div>
                                <?php if ((int) $conversation['unread_count'] > 0): ?>
                                    <strong class="conversation-unread-count"><?= (int) $conversation['unread_count'] > 99 ? '99+' : (int) $conversation['unread_count'] ?></strong>
                                <?php endif; ?>
                                <time datetime="<?= e($conversation['latest_created_at']) ?>"><?= e(date('M j', strtotime($conversation['latest_created_at']))) ?></time>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </aside>

            <section class="message-panel" aria-labelledby="conversation-title">
                <?php if ($selectedUser): ?>
                    <?php $selectedName = trim($selectedUser['first_name'] . ' ' . $selectedUser['last_name']); ?>
                    <header class="message-panel-header">
                        <div class="profile-avatar conversation-avatar">
                            <?php if (!empty($selectedUser['profile_picture'])): ?>
                                <img src="../<?= e($selectedUser['profile_picture']) ?>" alt="">
                            <?php else: ?>
                                <?= e(strtoupper(substr($selectedUser['first_name'], 0, 1))) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h2 id="conversation-title">Conversation with <?= e($selectedName) ?></h2>
                            <span>@<?= e($selectedUser['username']) ?></span>
                        </div>
                    </header>
                    <div class="message-history" id="message-history" data-user-id="<?= (int) $selectedUser['id'] ?>" data-current-user-id="<?= $userId ?>" data-fetch-url="fetch_messages.php" data-mark-read-url="mark_read.php" data-unread-count="<?= $unreadCount ?>">
                        <?php if (!$conversationMessages): ?>
                            <p class="message-empty-state">No messages yet. Start a conversation.</p>
                        <?php else: ?>
                            <?php foreach ($conversationMessages as $message): ?>
                                <div class="message-bubble <?= (int) $message['sender_id'] === $userId ? 'is-sent' : 'is-received' ?>">
                                    <p><?= nl2br(e($message['message'])) ?></p>
                                    <time datetime="<?= e($message['created_at']) ?>"><?= e(date('M j, Y g:i A', strtotime($message['created_at']))) ?></time>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <form class="message-composer" method="POST" action="send_message.php" aria-label="Message composer">
                        <input type="hidden" name="receiver_id" value="<?= (int) $selectedUser['id'] ?>">
                        <label class="sr-only" for="message-text">Write a message</label>
                        <textarea id="message-text" name="message" rows="1" maxlength="2000" placeholder="Type a message..." required></textarea>
                        <button type="submit">Send</button>
                    </form>
                <?php else: ?>
                    <div class="message-welcome">
                        <span class="message-welcome-icon" aria-hidden="true">✉</span>
                        <h2 id="conversation-title">Choose a conversation</h2>
                        <p>Select someone from your previous conversations to view your messages.</p>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </main>

    <nav class="mobile-bottom-nav" aria-label="Main navigation">
        <a href="../dashboard.php"><span aria-hidden="true">⌂</span><small>Home</small></a>
        <a href="../community/community.php"><span aria-hidden="true">◌</span><small>Community</small></a>
        <a href="../support/support.php"><span aria-hidden="true">✚</span><small>Support</small></a>
        <a href="../bookings/bookings.php"><span aria-hidden="true">□</span><small>Bookings</small></a>
        <a href="../account/account.php"><span aria-hidden="true">◎</span><small>Account</small></a>
    </nav>
    <script src="../js/messages.js"></script>
</body>

</html>