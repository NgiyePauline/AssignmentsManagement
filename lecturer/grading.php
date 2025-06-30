<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('lecturer');

$lecturer_id = $_SESSION['user_id'];

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_grade'])) {
    $submission_id = sanitizeInput($_POST['submission_id']);
    $marks = sanitizeInput($_POST['marks']);
    $feedback = sanitizeInput($_POST['feedback']);
    
    // Validate inputs
    if (empty($marks)) {
        $error = "Marks are required";
    } else {
        // Check if submission exists and lecturer is authorized to grade it
        $stmt = $conn->prepare("
            SELECT s.submission_id, a.max_marks, s.student_id, a.assignment_id, a.title, c.course_code
            FROM submissions s
            JOIN assignments a ON s.assignment_id = a.assignment_id
            JOIN courses c ON a.course_id = c.course_id
            WHERE s.submission_id = ? AND c.lecturer_id = ?
        ");
        $stmt->bind_param("ii", $submission_id, $lecturer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $submission = $result->fetch_assoc();
            $student_id = $submission['student_id'];
            $assignment_id = $submission['assignment_id'];
            
            // Validate marks don't exceed max marks
            if ($marks > $submission['max_marks']) {
                $error = "Marks cannot exceed maximum marks for this assignment";
            } else {
                // Check if grade already exists
                $stmt = $conn->prepare("SELECT grade_id FROM grades WHERE submission_id = ?");
                $stmt->bind_param("i", $submission_id);
                $stmt->execute();
                $grade_result = $stmt->get_result();
                
                $is_update = ($grade_result->num_rows > 0);
                
                if ($is_update) {
                    // Update existing grade
                    $grade = $grade_result->fetch_assoc();
                    $stmt = $conn->prepare("
                        UPDATE grades 
                        SET marks = ?, feedback = ?, graded_by = ?, graded_at = NOW()
                        WHERE grade_id = ?
                    ");
                    $stmt->bind_param("isii", $marks, $feedback, $lecturer_id, $grade['grade_id']);
                } else {
                    // Insert new grade
                    $stmt = $conn->prepare("
                        INSERT INTO grades (submission_id, marks, feedback, graded_by)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->bind_param("iisi", $submission_id, $marks, $feedback, $lecturer_id);
                }
                
                if ($stmt->execute()) {
                    // Update submission status to graded
                    $stmt = $conn->prepare("
                        UPDATE submissions 
                        SET status = 'graded'
                        WHERE submission_id = ?
                    ");
                    $stmt->bind_param("i", $submission_id);
                    $stmt->execute();
                    
                    $success = "Grade " . ($is_update ? "updated" : "submitted") . " successfully!";
                    
                    // NOTIFICATION 
                    // Notify student about graded assignment
                    $message = "Assignment graded: {$submission['title']} ({$submission['course_code']}) - $marks/{$submission['max_marks']}";
                    $link = "/soams/student/assignments.php?action=view&id=$assignment_id";
                    addNotification($student_id, 'assignment_grade', $message, $link);
                    
                    //EMAIL NOTIFICATION 
                    $stmt = $conn->prepare("
                        SELECT u.email, u.full_name
                        FROM users u
                        WHERE u.user_id = ?
                    ");
                    $stmt->bind_param("i", $student_id);
                    $stmt->execute();
                    $student_data = $stmt->get_result()->fetch_assoc();
                    
                    $action = $is_update ? "updated" : "posted";
                    $subject = "Grade $action for: " . $submission['title'];
                    $body = "
                        <h3>Assignment Graded</h3>
                        <p>Hello {$student_data['full_name']},</p>
                        <p>Your submission for <strong>{$submission['title']}</strong> has been graded.</p>
                        <p>Grade: <strong>{$marks}/{$submission['max_marks']}</strong></p>
                        <p>Feedback: " . nl2br($feedback) . "</p>
                        <p>Please log in to SOAMS to view your grade and feedback.</p>
                        <p>Best regards,<br>SOAMS Team</p>
                    ";
                    
                    sendEmail($student_data['email'], $subject, $body);
                } else {
                    $error = "Failed to " . ($is_update ? "update" : "submit") . " grade. Please try again.";
                }
            }
        } else {
            $error = "Submission not found or you are not authorized to grade it.";
        }
    }
}

// Get submission details if grading specific submission
if (isset($_GET['submission_id'])) {
    $submission_id = sanitizeInput($_GET['submission_id']);
    
    $stmt = $conn->prepare("
        SELECT s.*, a.*, c.course_code, c.course_name, u.full_name AS student_name, 
               u.email AS student_email, g.marks, g.feedback, g.graded_at
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.assignment_id
        JOIN courses c ON a.course_id = c.course_id
        JOIN users u ON s.student_id = u.user_id
        LEFT JOIN grades g ON s.submission_id = g.submission_id
        WHERE s.submission_id = ? AND c.lecturer_id = ?
    ");
    $stmt->bind_param("ii", $submission_id, $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $submission = $result->fetch_assoc();
    } else {
        $error = "Submission not found or you are not authorized to grade it.";
    }
} elseif (isset($_GET['assignment_id'])) {
    // List all submissions for an assignment
    $assignment_id = sanitizeInput($_GET['assignment_id']);
    
    $stmt = $conn->prepare("
        SELECT s.submission_id, s.submitted_at, s.status, u.full_name AS student_name,
               g.marks, a.max_marks, g.feedback
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.assignment_id
        JOIN courses c ON a.course_id = c.course_id
        JOIN users u ON s.student_id = u.user_id
        LEFT JOIN grades g ON s.submission_id = g.submission_id
        WHERE s.assignment_id = ? AND c.lecturer_id = ?
        ORDER BY s.submitted_at DESC
    ");
    $stmt->bind_param("ii", $assignment_id, $lecturer_id);
    $stmt->execute();
    $submissions = $stmt->get_result();
    
    // Get assignment details
    $stmt = $conn->prepare("
        SELECT a.*, c.course_code, c.course_name
        FROM assignments a
        JOIN courses c ON a.course_id = c.course_id
        WHERE a.assignment_id = ? AND c.lecturer_id = ?
    ");
    $stmt->bind_param("ii", $assignment_id, $lecturer_id);
    $stmt->execute();
    $assignment_result = $stmt->get_result();
    $assignment = $assignment_result->num_rows > 0 ? $assignment_result->fetch_assoc() : null;
} else {
    $error = "No submission or assignment specified.";
}

$pageTitle = isset($submission) ? "Grade Submission" : (isset($assignment) ? "Grade Assignments" : "Grading");
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isset($submission)): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">Grade Submission</h5>
        </div>
        <div class="card-body">
            <h4><?php echo $submission['title']; ?></h4>
            <p><strong>Course:</strong> <?php echo $submission['course_code']; ?> - <?php echo $submission['course_name']; ?></p>
            <p><strong>Student:</strong> <?php echo $submission['student_name']; ?> (<?php echo $submission['student_email']; ?>)</p>
            <p><strong>Submitted:</strong> <?php echo date('M j, Y H:i', strtotime($submission['submitted_at'])); ?></p>
            <p><strong>Status:</strong> 
                <?php if ($submission['status'] == 'graded'): ?>
                    <span class="badge bg-success">Graded</span>
                <?php elseif ($submission['status'] == 'late'): ?>
                    <span class="badge bg-warning">Late Submission</span>
                <?php else: ?>
                    <span class="badge bg-info">Submitted</span>
                <?php endif; ?>
            </p>
            
            <div class="mb-3">
                <label class="form-label">Submission File:</label>
                <a href="/soams/uploads/<?php echo $submission['file_path']; ?>" target="_blank" class="btn btn-outline-primary">
                    <i class="bi bi-download"></i> Download Submission
                </a>
            </div>
            
            <form method="POST">
                <input type="hidden" name="submission_id" value="<?php echo $submission['submission_id']; ?>">
                
                <div class="mb-3">
                    <label for="marks" class="form-label">Marks (Max: <?php echo $submission['max_marks']; ?>)</label>
                    <input type="number" class="form-control" id="marks" name="marks" 
                           min="0" max="<?php echo $submission['max_marks']; ?>" 
                           value="<?php echo isset($submission['marks']) ? $submission['marks'] : ''; ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="feedback" class="form-label">Feedback</label>
                    <textarea class="form-control" id="feedback" name="feedback" rows="5"><?php echo isset($submission['feedback']) ? $submission['feedback'] : ''; ?></textarea>
                </div>
                
                <button type="submit" name="submit_grade" class="btn btn-primary">
                    <?php echo $submission['status'] == 'graded' ? 'Update Grade' : 'Submit Grade'; ?>
                </button>
                <a href="/soams/lecturer/grading.php?assignment_id=<?php echo $submission['assignment_id']; ?>" class="btn btn-secondary">Back to Submissions</a>
            </form>
            
            <?php if ($submission['status'] == 'graded'): ?>
                <div class="mt-4">
                    <h6>Previous Grade Details</h6>
                    <p><strong>Graded on:</strong> <?php echo date('M j, Y H:i', strtotime($submission['graded_at'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php elseif (isset($assignment)): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">Assignment: <?php echo $assignment['title']; ?></h5>
        </div>
        <div class="card-body">
            <p><strong>Course:</strong> <?php echo $assignment['course_code']; ?> - <?php echo $assignment['course_name']; ?></p>
            <p><strong>Deadline:</strong> <?php echo date('M j, Y H:i', strtotime($assignment['deadline'])); ?></p>
            <p><strong>Max Marks:</strong> <?php echo $assignment['max_marks']; ?></p>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">Submissions</h5>
        </div>
        <div class="card-body">
            <?php if ($submissions->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Grade</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($sub = $submissions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $sub['student_name']; ?></td>
                                    <td><?php echo date('M j, Y H:i', strtotime($sub['submitted_at'])); ?></td>
                                    <td>
                                        <?php if ($sub['status'] == 'graded'): ?>
                                            <span class="badge bg-success">Graded</span>
                                        <?php elseif ($sub['status'] == 'late'): ?>
                                            <span class="badge bg-warning">Late</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Submitted</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($sub['status'] == 'graded'): ?>
                                            <?php echo $sub['marks']; ?>/<?php echo $assignment['max_marks']; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/soams/lecturer/grading.php?submission_id=<?php echo $sub['submission_id']; ?>" class="btn btn-sm btn-primary">
                                            <?php echo $sub['status'] == 'graded' ? 'View/Edit' : 'Grade'; ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    No submissions yet for this assignment.</div>
            <?php endif; ?>
        </div>
    </div>
    
    <a href="/soams/lecturer/assignments.php" class="btn btn-secondary mt-3">Back to Assignments</a>
<?php else: ?>
    <div class="alert alert-info">
        Please select a submission or assignment to grade.</div>
<?php endif; ?>

<!-- <?php require_once __DIR__ . '/../includes/footer.php'; ?> -->