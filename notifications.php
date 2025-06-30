<?php
require_once __DIR__ . '/includes/auth.php';


$user_id = $_SESSION['user_id'];

// Get all notifications
$stmt = $conn->prepare("
    SELECT n.*, 
           TIMESTAMPDIFF(HOUR, n.created_at, NOW()) AS hours_ago
    FROM notifications n
    WHERE user_id = ? 
      AND deleted_at IS NULL
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    // Determine time ago text
    if ($row['hours_ago'] < 1) {
        $time_ago = 'Just now';
    } elseif ($row['hours_ago'] < 24) {
        $time_ago = $row['hours_ago'] . ' hours ago';
    } else {
        $time_ago = floor($row['hours_ago'] / 24) . ' days ago';
    }
    
    // Map notification types to icons
    $icons = [
        'assignment_submitted' => 'bi-upload text-primary',
        'assignment_grade' => 'bi-check-circle text-success',
        'new_assignment' => 'bi-journal-plus text-info',
        'deadline_reminder' => 'bi-clock text-warning',
        'course_enrollment' => 'bi-person-plus text-purple',
        'system_alert' => 'bi-exclamation-triangle text-danger'
    ];
    
    $notifications[] = [
        'id' => $row['id'],
        'message' => $row['message'],
        'link' => $row['link'],
        'icon' => $icons[$row['type']] ?? 'bi-bell',
        'time_ago' => $time_ago,
        'unread' => $row['is_read'] == 0
    ];
}

$pageTitle = "My Notifications";
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0">My Notifications</h5>
    </div>
    <div class="card-body">
        <div class="d-flex justify-content-between mb-3">
            <a href="/soams/mark_all_read.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-check-all"></i> Mark All as Read
            </a>
            <a href="/soams/delete_all_read.php" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-trash"></i> Delete All Read
            </a>
        </div>
        
        <?php if (!empty($notifications)): ?>
            <div class="list-group">
                <?php foreach ($notifications as $notification): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <a href="/soams/mark_notification_read.php?id=<?php echo $notification['id']; ?>&redirect=<?php echo urlencode($notification['link']); ?>" 
                               class="text-decoration-none flex-grow-1 <?php echo $notification['unread'] ? 'fw-bold' : 'text-dark'; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="bi <?php echo $notification['icon']; ?> fs-4"></i>
                                    </div>
                                    <div>
                                        <?php echo $notification['message']; ?>
                                        <div class="text-muted small"><?php echo $notification['time_ago']; ?></div>
                                    </div>
                                </div>
                            </a>
                            <a href="/soams/delete_notification.php?id=<?php echo $notification['id']; ?>" 
                               class="btn btn-sm text-danger ms-2" title="Delete notification">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">You have no notifications.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>