<?php
/**
 * Common utility functions for the Zouki admin dashboard
 */

/**
 * Get a human-readable time ago string from a datetime
 * 
 * @param string $datetime The datetime string to convert
 * @return string Human-readable time ago
 */
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ($mins == 1 ? ' minute ago' : ' minutes ago');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ($hours == 1 ? ' hour ago' : ' hours ago');
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ($days == 1 ? ' day ago' : ' days ago');
    } else {
        return date('M j, Y', $time);
    }
}

/**
 * Get the appropriate icon class for a notification type
 * 
 * @param string $type The notification type
 * @return string Bootstrap icon class
 */
function getNotificationIcon($type) {
    switch ($type) {
        case 'product':
            return 'bi-box';
        case 'system':
            return 'bi-gear';
        case 'user':
            return 'bi-person';
        default:
            return 'bi-bell';
    }
}

/**
 * Get the appropriate color class for a notification based on type and message
 * 
 * @param string $type The notification type
 * @param string $message The notification message
 * @return string Color class
 */
function getNotificationColor($type, $message = '') {
    switch ($type) {
        case 'product':
            if (strpos($message, 'Amber') !== false) {
                return 'amber';
            } elseif (strpos($message, 'Red') !== false) {
                return 'red';
            }
            return 'green';
        case 'system':
            return 'blue';
        case 'user':
            return 'purple';
        default:
            return 'primary';
    }
}

/**
 * Get unread notification count for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @return int Number of unread notifications
 */
function getUnreadNotificationCount($conn, $userId) {
    $count = 0;
    
    $unreadQuery = $conn->prepare("
        SELECT COUNT(*) as unread_count
        FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");
    
    if ($unreadQuery) {
        $unreadQuery->bind_param("i", $userId);
        $unreadQuery->execute();
        $result = $unreadQuery->get_result();
        if ($row = $result->fetch_assoc()) {
            $count = $row['unread_count'];
        }
    }
    
    return $count;
}

/**
 * Get recent notifications for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param int $limit Maximum number of notifications to retrieve
 * @return mysqli_result|false Result set or false on failure
 */
function getRecentNotifications($conn, $userId, $limit = 3) {
    $notificationsQuery = $conn->prepare("
        SELECT 
            notification_id,
            type,
            title,
            message,
            is_read,
            created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    
    if ($notificationsQuery) {
        $notificationsQuery->bind_param("ii", $userId, $limit);
        $notificationsQuery->execute();
        return $notificationsQuery->get_result();
    }
    
    return false;
}

/**
 * Get user details from username
 * 
 * @param mysqli $conn Database connection
 * @param string $username Username
 * @return array|null User details or null if not found
 */
function getUserDetails($conn, $username) {
    $userQuery = $conn->prepare("SELECT * FROM z_user WHERE username = ?");
    if ($userQuery) {
        $userQuery->bind_param("s", $username);
        $userQuery->execute();
        $result = $userQuery->get_result();
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
    }
    
    return null;
}

/**
 * Get HTML for a user avatar
 * 
 * @param string|null $userPhoto Path to user photo
 * @param string $userName Username
 * @param array $userDetails User details array
 * @return string HTML for user avatar
 */
function getUserAvatarHtml($userPhoto, $userName, $userDetails = []) {
    $initial = isset($userDetails['first_name']) ? 
               strtoupper(substr($userDetails['first_name'], 0, 1)) : 
               strtoupper(substr($userName, 0, 1));
    
    if (!empty($userPhoto) && file_exists($userPhoto)) {
        return '<img src="' . htmlspecialchars($userPhoto) . '" alt="Profile" class="user-avatar" style="object-fit: cover;">';
    } else {
        return '<div class="user-avatar">' . $initial . '</div>';
    }
}

/**
 * Format a date in a nice readable format
 * 
 * @param string $dateStr Date string
 * @param string $format Format (default: 'M d, Y')
 * @return string Formatted date
 */
function formatDate($dateStr, $format = 'M d, Y') {
    $timestamp = strtotime($dateStr);
    return date($format, $timestamp);
}

/**
 * Add a notification for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param string $type Notification type
 * @param string $title Notification title
 * @param string $message Notification message
 * @return bool Success status
 */
function addNotification($conn, $userId, $type, $title, $message) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
    if ($stmt) {
        $stmt->bind_param("isss", $userId, $type, $title, $message);
        return $stmt->execute();
    }
    return false;
}