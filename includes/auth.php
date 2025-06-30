<?php
session_start();

require_once __DIR__ . '/../vendor/config/database.php';

require_once __DIR__ . '/../vendor/config/email.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user has a specific role
function hasRole($role) {
    if (!isLoggedIn()) return false;
    return $_SESSION['role'] === $role;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /soams/login.php");
        exit();
    }
}

// Redirect if doesn't have required role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header("Location: /soams/unauthorized.php");
        exit();
    }
}

// Password hashing
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Sanitize input
function sanitizeInput($data) {
    global $conn;
    return htmlspecialchars(strip_tags(trim($conn->real_escape_string($data))));
}

// Generate random token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}



//notifications

function getNotificationCount() {
    if (!isset($_SESSION['user_id'])) return 0;
    
    global $conn;
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS unread_count 
        FROM notifications 
        WHERE user_id = ? 
          AND is_read = 0 
          AND deleted_at IS NULL
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['unread_count'] ?? 0;
}

function getRecentNotifications($limit = 5) {
    if (!isset($_SESSION['user_id'])) return [];
    
    global $conn;
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT n.*, 
               TIMESTAMPDIFF(HOUR, n.created_at, NOW()) AS hours_ago
        FROM notifications n
        WHERE user_id = ? 
          AND deleted_at IS NULL
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $user_id, $limit);
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
        
        // Map notification types to icons and colors
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
    
    return $notifications;
}



function addNotification($user_id, $type, $message, $link = '#') {
    global $conn;
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, message, link)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isss", $user_id, $type, $message, $link);
    return $stmt->execute();
}

function notifyLecturerOnSubmission($lecturer_id, $student_name, $course_code, $assignment_title, $assignment_id) {
    $message = "$student_name submitted assignment: $assignment_title for $course_code";
    $link = "/soams/lecturer/submissions.php?assignment_id=$assignment_id";
    addNotification($lecturer_id, 'assignment_submitted', $message, $link);
}

function notifyStudentOnEnrollment($student_id, $course_code, $course_name) {
    $message = "You've been enrolled in $course_code: $course_name";
    $link = "/soams/student/courses.php";
    addNotification($student_id, 'course_enrollment', $message, $link);
}

function notifyStudentOnGrade($student_id, $course_code, $assignment_title, $grade, $max_grade) {
    $message = "Assignment graded: $assignment_title ($course_code) - $grade/$max_grade";
    $link = "/soams/student/submissions.php";
    addNotification($student_id, 'assignment_grade', $message, $link);
}

function notifyNewAssignment($course_id, $assignment_title, $course_code) {
    global $conn;
    
    // Get all enrolled students
    $stmt = $conn->prepare("
        SELECT e.student_id 
        FROM enrollments e
        WHERE e.course_id = ?
    ");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $students = $stmt->get_result();
    
    while ($student = $students->fetch_assoc()) {
        $message = "New assignment: $assignment_title for $course_code";
        $link = "/soams/student/assignments.php";
        addNotification($student['student_id'], 'new_assignment', $message, $link);
    }
}

function notifyDeadlineReminder($assignment_id, $hours_before = 24) {
    global $conn;
    
    // Get assignment details
    $stmt = $conn->prepare("
        SELECT a.title, c.course_code, c.lecturer_id
        FROM assignments a
        JOIN courses c ON a.course_id = c.course_id
        WHERE a.assignment_id = ?
    ");
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();
    
    if (!$assignment) return;
    
    // Notify lecturer
    $message = "Deadline approaching: {$assignment['title']} for {$assignment['course_code']}";
    $link = "/soams/lecturer/assignments.php";
    addNotification($assignment['lecturer_id'], 'deadline_reminder', $message, $link);
    
    // Notify enrolled students
    $stmt = $conn->prepare("
        SELECT e.student_id
        FROM enrollments e
        WHERE e.course_id = (SELECT course_id FROM assignments WHERE assignment_id = ?)
    ");
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $students = $stmt->get_result();
    
    while ($student = $students->fetch_assoc()) {
        $message = "Deadline soon: {$assignment['title']} for {$assignment['course_code']}";
        $link = "/soams/student/assignments.php";
        addNotification($student['student_id'], 'deadline_reminder', $message, $link);
    }
}
?>