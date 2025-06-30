<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('student');

$student_id = $_SESSION['user_id'];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $assignment_id = sanitizeInput($_POST['assignment_id']);
    
    // Check if assignment exists and deadline hasn't passed
    $stmt = $conn->prepare("
        SELECT a.assignment_id, a.deadline, c.course_code, a.title, a.max_marks, c.lecturer_id
        FROM assignments a
        JOIN courses c ON a.course_id = c.course_id
        JOIN enrollments e ON c.course_id = e.course_id
        WHERE a.assignment_id = ? AND e.student_id = ?
    ");
    $stmt->bind_param("ii", $assignment_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $assignment = $result->fetch_assoc();
        
        // Check if deadline has passed
        $current_time = date('Y-m-d H:i:s');
        $is_late = $current_time > $assignment['deadline'];
        
        // Handle file upload
        if (isset($_FILES['submission_file'])) {
            $file = $_FILES['submission_file'];
            
            // Check for errors
            if ($file['error'] === UPLOAD_ERR_OK) {
                // Validate file type and size
                $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $max_size = 5 * 1024 * 1024; 
                
                if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                    // Generate unique filename
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = "assignment_{$assignment_id}_student_{$student_id}_" . time() . ".$ext";
                    $upload_path = __DIR__ . "/../uploads/" . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        // Check if student already submitted
                        $stmt = $conn->prepare("
                            SELECT submission_id FROM submissions 
                            WHERE assignment_id = ? AND student_id = ?
                        ");
                        $stmt->bind_param("ii", $assignment_id, $student_id);
                        $stmt->execute();
                        $existing = $stmt->get_result();
                        
                        if ($existing->num_rows > 0) {
                            // Update existing submission
                            $sub = $existing->fetch_assoc();
                            $stmt = $conn->prepare("
                                UPDATE submissions 
                                SET file_path = ?, submitted_at = NOW(), status = ?
                                WHERE submission_id = ?
                            ");
                            $status = $is_late ? 'late' : 'submitted';
                            $stmt->bind_param("ssi", $filename, $status, $sub['submission_id']);
                        } else {
                            // Create new submission
                            $stmt = $conn->prepare("
                                INSERT INTO submissions (assignment_id, student_id, file_path, status)
                                VALUES (?, ?, ?, ?)
                            ");
                            $status = $is_late ? 'late' : 'submitted';
                            $stmt->bind_param("iiss", $assignment_id, $student_id, $filename, $status);
                        }
                        
                        if ($stmt->execute()) {
                            $success = "Assignment submitted successfully!";
                            
                            // EMAIL NOTIFICATION ONLY
                            $stmt = $conn->prepare("
                                SELECT u.email, u.full_name 
                                FROM assignments a
                                JOIN courses c ON a.course_id = c.course_id
                                JOIN users u ON c.lecturer_id = u.user_id
                                WHERE a.assignment_id = ?
                            ");
                            $stmt->bind_param("i", $assignment_id);
                            $stmt->execute();
                            $lecturer = $stmt->get_result()->fetch_assoc();
                            
                            $subject = "New Submission for: " . $assignment['title'];
                            $body = "
                                <h3>New Assignment Submission</h3>
                                <p>Hello {$lecturer['full_name']},</p>
                                <p>A student has submitted the assignment <strong>{$assignment['title']}</strong> for {$assignment['course_code']}.</p>
                                <p>Submitted by: {$_SESSION['full_name']}</p>
                                <p>Submitted at: " . date('M j, Y H:i') . "</p>
                                <p>Please log in to SOAMS to review the submission.</p>
                                <p>Best regards,<br>SOAMS Team</p>
                            ";
                            
                            sendEmail($lecturer['email'], $subject, $body);
                        } else {
                            $error = "Failed to record submission. Please try again.";
                        }
                    } else {
                        $error = "Failed to upload file. Please try again.";
                    }
                } else {
                    $error = "Invalid file type or size (max 5MB, only PDF/DOC/DOCX allowed).";
                }
            } else {
                $error = "File upload error. Please try again.";
            }
        } else {
            $error = "No file selected for submission.";
        }
    } else {
        $error = "Invalid assignment or you are not enrolled in this course.";
    }
}

// Get assignment details if viewing specific assignment
if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
    $assignment_id = sanitizeInput($_GET['id']);
    
    $stmt = $conn->prepare("
        SELECT a.*, c.course_code, c.course_name, u.full_name AS lecturer_name
        FROM assignments a
        JOIN courses c ON a.course_id = c.course_id
        JOIN users u ON c.lecturer_id = u.user_id
        JOIN enrollments e ON c.course_id = e.course_id
        WHERE a.assignment_id = ? AND e.student_id = ?
    ");
    $stmt->bind_param("ii", $assignment_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $assignment = $result->fetch_assoc();
        
        // Check if student has submitted
        $stmt = $conn->prepare("
            SELECT s.*, g.marks, g.feedback, g.graded_at, u.full_name AS graded_by
            FROM submissions s
            LEFT JOIN grades g ON s.submission_id = g.submission_id
            LEFT JOIN users u ON g.graded_by = u.user_id
            WHERE s.assignment_id = ? AND s.student_id = ?
        ");
        $stmt->bind_param("ii", $assignment_id, $student_id);
        $stmt->execute();
        $submission_result = $stmt->get_result();
        $submission = $submission_result->num_rows > 0 ? $submission_result->fetch_assoc() : null;
    } else {
        $error = "Assignment not found or you are not enrolled in this course.";
    }
} else {
    // List all assignments for student's courses
    $stmt = $conn->prepare("
        SELECT a.assignment_id, a.title, a.deadline, c.course_code, c.course_name,
               s.status, s.submitted_at, g.marks, a.max_marks
        FROM assignments a
        JOIN courses c ON a.course_id = c.course_id
        JOIN enrollments e ON c.course_id = e.course_id
        LEFT JOIN submissions s ON a.assignment_id = s.assignment_id AND s.student_id = ?
        LEFT JOIN grades g ON s.submission_id = g.submission_id
        WHERE e.student_id = ?
        ORDER BY a.deadline ASC
    ");
    $stmt->bind_param("ii", $student_id, $student_id);
    $stmt->execute();
    $assignments = $stmt->get_result();
    
    // DEADLINE REMINDER NOTIFICATIONS 
    // Check for upcoming deadlines (once per session)
    if (!isset($_SESSION['deadline_checked'])) {
        $stmt = $conn->prepare("
            SELECT a.assignment_id, a.title, c.course_code
            FROM assignments a
            JOIN courses c ON a.course_id = c.course_id
            JOIN enrollments e ON c.course_id = e.course_id
            WHERE e.student_id = ? 
              AND a.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
              AND NOT EXISTS (
                SELECT 1 FROM submissions s 
                WHERE s.assignment_id = a.assignment_id AND s.student_id = ?
              )
        ");
        $stmt->bind_param("ii", $student_id, $student_id);
        $stmt->execute();
        $upcoming = $stmt->get_result();
        
        while ($assignment = $upcoming->fetch_assoc()) {
            $message = "Deadline soon: {$assignment['title']} for {$assignment['course_code']}";
            $link = "/soams/student/assignments.php?action=view&id={$assignment['assignment_id']}";
            addNotification($student_id, 'deadline_reminder', $message, $link);
        }
        
        $_SESSION['deadline_checked'] = true;
    }
}

$pageTitle = isset($assignment) ? $assignment['title'] : "My Assignments";
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($assignment)): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">Assignment Details</h5>
        </div>
        <div class="card-body">
            <h4><?php echo $assignment['title']; ?></h4>
            <p><strong>Course:</strong> <?php echo $assignment['course_code']; ?> - <?php echo $assignment['course_name']; ?></p>
            <p><strong>Lecturer:</strong> <?php echo $assignment['lecturer_name']; ?></p>
            <p><strong>Deadline:</strong> <?php echo date('M j, Y H:i', strtotime($assignment['deadline'])); ?></p>
            <p><strong>Max Marks:</strong> <?php echo $assignment['max_marks']; ?></p>
            <p><strong>Description:</strong></p>
            <div class="border p-3 mb-3"><?php echo nl2br($assignment['description']); ?></div>
            
            <hr>
            
            <h5>Your Submission</h5>
            <?php if ($submission): ?>
                <p><strong>Status:</strong> 
                    <?php if ($submission['status'] == 'graded'): ?>
                        <span class="badge bg-success">Graded</span>
                    <?php elseif ($submission['status'] == 'late'): ?>
                        <span class="badge bg-warning">Late Submission</span>
                    <?php else: ?>
                        <span class="badge bg-info">Submitted</span>
                    <?php endif; ?>
                </p>
                <p><strong>Submitted on:</strong> <?php echo date('M j, Y H:i', strtotime($submission['submitted_at'])); ?></p>
                <p><strong>File:</strong> 
                    <a href="/soams/uploads/<?php echo $submission['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-download"></i> Download Submission
                    </a>
                </p>
                
                <?php if ($submission['status'] == 'graded'): ?>
                    <div class="alert alert-info mt-3">
                        <h6>Feedback</h6>
                        <p><strong>Grade:</strong> <?php echo $submission['marks']; ?>/<?php echo $assignment['max_marks']; ?></p>
                        <p><strong>Feedback:</strong> <?php echo nl2br($submission['feedback']); ?></p>
                        <p><small>Graded by <?php echo $submission['graded_by']; ?> on <?php echo date('M j, Y', strtotime($submission['graded_at'])); ?></small></p>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['assignment_id']; ?>">
                    <div class="mb-3">
                        <label for="submission_file" class="form-label">Update Submission (PDF/DOC/DOCX, max 5MB)</label>
                        <input type="file" class="form-control" id="submission_file" name="submission_file" required>
                    </div>
                    <button type="submit" name="submit_assignment" class="btn btn-primary">Update Submission</button>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">
                    You haven't submitted this assignment yet.
                </div>
                
                <?php if (strtotime($assignment['deadline']) > time()): ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="assignment_id" value="<?php echo $assignment['assignment_id']; ?>">
                        <div class="mb-3">
                            <label for="submission_file" class="form-label">Upload Submission (PDF/DOC/DOCX, max 5MB)</label>
                            <input type="file" class="form-control" id="submission_file" name="submission_file" required>
                        </div>
                        <button type="submit" name="submit_assignment" class="btn btn-primary">Submit Assignment</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-danger">
                        The deadline for this assignment has passed. Late submissions are not accepted.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <a href="/soams/student/assignments.php" class="btn btn-secondary">Back to All Assignments</a>
<?php else: ?>
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">My Assignments</h5>
        </div>
        <div class="card-body">
            <?php if ($assignments->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Assignment</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Grade</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($assignment = $assignments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $assignment['course_code']; ?></td>
                                    <td><?php echo $assignment['title']; ?></td>
                                    <td><?php echo date('M j, Y H:i', strtotime($assignment['deadline'])); ?></td>
                                    <td>
                                        <?php if ($assignment['status'] == 'graded'): ?>
                                            <span class="badge bg-success">Graded</span>
                                        <?php elseif ($assignment['status'] == 'late'): ?>
                                            <span class="badge bg-warning">Late</span>
                                        <?php elseif ($assignment['submitted_at']): ?>
                                            <span class="badge bg-info">Submitted</span>
                                        <?php elseif (strtotime($assignment['deadline']) < time()): ?>
                                            <span class="badge bg-danger">Missed</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($assignment['status'] == 'graded'): ?>
                                            <?php echo $assignment['marks']; ?>/<?php echo $assignment['max_marks']; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/soams/student/assignments.php?action=view&id=<?php echo $assignment['assignment_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No assignments found for your courses.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- <?php require_once __DIR__ . '/../includes/footer.php'; ?> -->