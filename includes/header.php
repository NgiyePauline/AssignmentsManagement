<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'SOAMS'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/soams/assets/css/style.css">
</head>
<body>
    <?php
    // Get notification data
    $unread_count = getNotificationCount();
    $notifications = getRecentNotifications();
    ?>
    
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/soams/">SOAMS</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if (isLoggedIn()): ?>
                        <?php if (hasRole('lecturer')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/soams/lecturer/dashboard.php">Lecturer Dashboard</a>
                            </li>
                        <?php elseif (hasRole('student')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/soams/student/dashboard.php">Student Dashboard</a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <!-- Notification Bell -->
                    
<li class="nav-item dropdown">
    <a class="nav-link position-relative" href="#" id="notificationDropdown" 
       role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-bell fs-5"></i>
        <?php if ($unread_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?php echo $unread_count; ?>
                <span class="visually-hidden">unread notifications</span>
            </span>
        <?php endif; ?>
    </a>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
        <li><h6 class="dropdown-header">Notifications</h6></li>
        <?php if (!empty($notifications)): ?>
            <?php foreach ($notifications as $notification): ?>
                <li>
                    <div class="d-flex justify-content-between align-items-start p-2">
                        <a class="dropdown-item flex-grow-1 <?php echo $notification['unread'] ? 'fw-bold' : ''; ?>" 
                           href="/soams/mark_notification_read.php?id=<?php echo $notification['id']; ?>&redirect=<?php echo urlencode($notification['link']); ?>">
                            <div class="d-flex">
                                <div class="me-2">
                                    <i class="bi <?php echo $notification['icon']; ?>"></i>
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
                </li>
            <?php endforeach; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-center small" href="/soams/notifications.php">View All Notifications</a></li>
        <?php else: ?>
            <li><a class="dropdown-item text-muted fst-italic">No notifications</a></li>
        <?php endif; ?>
    </ul>
</li> 
                        
                        <li class="nav-item">
                            <span class="nav-link">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/soams/logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/soams/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/soams/register.php">Register</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/soams/index.php">Home</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">