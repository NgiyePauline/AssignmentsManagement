<?php
require_once __DIR__ . '/includes/auth.php';
//require_once __DIR__ . '/../includes/db.php';

if (!isset($_GET['id'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

$notification_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Mark notification as read
$stmt = $conn->prepare("
    UPDATE notifications 
    SET is_read = 1 
    WHERE id = ? AND user_id = ? AND deleted_at IS NULL
");
$stmt->bind_param("ii", $notification_id, $user_id);
$stmt->execute();

// Redirect back to where user came from
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;