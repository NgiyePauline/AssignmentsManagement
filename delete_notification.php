<?php
require_once __DIR__ . '/includes/auth.php';


if (!isset($_GET['id'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

$notification_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Soft delete notification
$stmt = $conn->prepare("
    UPDATE notifications 
    SET deleted_at = NOW() 
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $notification_id, $user_id);
$stmt->execute();

// Redirect back to where user came from
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;