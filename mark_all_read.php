<?php
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: /soams/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Mark all notifications as read
$stmt = $conn->prepare("
    UPDATE notifications 
    SET is_read = 1 
    WHERE user_id = ? 
      AND is_read = 0
      AND deleted_at IS NULL
");
$stmt->bind_param("i", $user_id);
$stmt->execute();

header('Location: /soams/notifications.php');
exit;