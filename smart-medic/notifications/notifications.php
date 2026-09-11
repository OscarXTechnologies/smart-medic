<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/notifications.php';

$userId = (int) $_SESSION['user_id'];
$notifications = [];
$unreadCount = getUnreadNotificationCount($conn, $userId);

$notificationStatement = $conn->prepare(
    ' SELECT n.id, n.type, n.reference_id, n.message, n.is_read, n.created_at,
                 sender.first_name AS sender_first_name, sender.last_name AS sender_last_name,
                 sender.username AS sender_username
	  FROM notifications AS n
             LEFT JOIN users AS sender ON sender.id = n.reference_id AND n.type = ?
	  WHERE n.user_id = ?
	  ORDER BY n.created_at DESC, n.id DESC'
);

if ($notificationStatement) {
    $messageType = 'message';
    $notificationStatement->bind_param('si', $messageType, $userId);
    $notificationStatement->execute();
    $result = $notificationStatement->get_result();
    while ($notification = $result->fetch_assoc()) {
        $notifications[] = $notification;
    }
    $notificationStatement->close();
}

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
    <title>Notifications | Smart Medic</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="dashboard-body notifications-page">
    <header class="dashboard-header">
        <a class="brand-mark" href="../dashboard.php" aria-label="Smart Medic home">
            <span class="brand-symbol" aria-hidden="true">+</span>
            <span>Smart Medic</span>
        </a>
        <nav class="desktop-main-nav" aria-label="Main navigation">
            <a href="../dashboard.php">Home</a>
            <a href="../community/community.php">Community</a>
            <a href="../support/support.php">Support</a>
            <a href="../bookings/bookings.php">Bookings</a>
            <a href="../account/account.php">Account</a>
        </nav>
        <a class="messages-header-link" href="../messages/messages.php" aria-label="Messages">
            <span aria-hidden="true">□</span>
        </a>
        <a class="header-avatar" href="../profile.php" aria-label="Open profile"><?= e($currentInitial) ?></a>
    </header>

    <main class="notifications-shell">
        <section class="notifications-card" aria-labelledby="notifications-title">
            <header class="notifications-heading">
                <div>
                    <p class="eyebrow">Stay informed</p>
                    <h1 id="notifications-title">Notifications<?php if ($unreadCount > 0): ?> <span class="notification-count"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span><?php endif; ?></h1>
                    <p>Updates about your Smart Medic activity.</p>
                </div>
                <?php if ($unreadCount > 0): ?>
                    <form method="POST" action="mark_all_read.php">
                        <button type="submit" class="mark-all-read-button">Mark all as read</button>
                    </form>
                <?php endif; ?>
            </header>

            <?php if (!$notifications): ?>
                <div class="notifications-empty">You have no notifications yet.</div>
            <?php else: ?>
                <div class="notification-list">
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                        $senderId = (int) ($notification['sender_id'] ?? 0);
                        $notificationText = (string) $notification['message'];
                        $notificationType = (string) $notification['type'];
                        $referenceId = (int) ($notification['reference_id'] ?? 0);
                        if ($notificationType === 'message' && $senderId > 0) {
                            $notificationLink = '../messages/messages.php?user_id=' . $senderId;
                        } elseif ($notificationType === 'follow' && $referenceId > 0) {
                            $notificationLink = '../users/profile.php?user_id=' . $referenceId;
                        } elseif (in_array($notificationType, ['post', 'like', 'comment', 'share'], true) && $referenceId > 0) {
                            $notificationLink = '../dashboard.php#post-' . $referenceId;
                        } elseif ($notificationType === 'consultation' && $referenceId > 0) {
                            $notificationLink = '../support/telemedicine.php?consultation_id=' . $referenceId;
                        } else {
                            $notificationLink = '../dashboard.php';
                        }
                        ?>
                        <div class="notification-item <?= (int) $notification['is_read'] === 0 ? 'is-unread' : '' ?>">
                            <span class="notification-icon" aria-hidden="true">✉</span>
                            <span class="notification-copy">
                                <a href="<?= e($notificationLink) ?>"><strong><?= e($notificationText) ?></strong></a>
                                <small><?= e(date('M j, Y g:i A', strtotime($notification['created_at']))) ?></small>
                            </span>
                            <?php if ((int) $notification['is_read'] === 0): ?>
                                <form method="POST" action="mark_read.php">
                                    <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                                    <button type="submit" class="notification-read-button">Mark read</button>
                                </form>
                            <?php endif; ?>
                        </div>
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
        <a href="../account/account.php"><span aria-hidden="true">◎</span><small>Account</small></a>
    </nav>
</body>

</html>