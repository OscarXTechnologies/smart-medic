<?php

function getUnreadNotificationCount(mysqli $conn, int $userId): int
{
    $statement = $conn->prepare(
        'SELECT COUNT(*) AS unread_count
         FROM notifications
         WHERE user_id = ? AND is_read = 0'
    );

    if (!$statement) {
        return 0;
    }

    $statement->bind_param('i', $userId);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();

    return (int) ($row['unread_count'] ?? 0);
}