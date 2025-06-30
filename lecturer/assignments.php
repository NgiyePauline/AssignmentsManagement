<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('lecturer');

$lecturer_id = $_SESSION['user_id'];

// Handle new assignment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {
    $course_id = sanitizeInput($_POST['course_id']);
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $deadline = sanitizeInput($_POST['deadline']);
    $max_marks = sanitizeInput($_POST['max_marks']);
    
    // Validate inputs
    if (empty($title) || empty($deadline) || empty($max_marks)) {
        $error = "Title, deadline and max marks are required";
    } else {
        // Check if lecturer teaches this course
        $stmt = $conn->prepare("SELECT course_id FROM courses WHERE course_id = ? AND lecturer_id = ?");
        $stmt->bind_param("ii", $course_id, $lecturer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            // Insert new assignment
            $stmt = $conn->prepare("
                INSERT INTO assignments (course_id, title, description, deadline, max_marks)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssi", $course_id, $title, $description, $deadline, $max_marks);
            
            if ($stmt->execute()) {
                $new_assignment_id = $stmt->insert_id;
                $success = "Assignment created successfully!";
                
                //NOTIFICATIONS 
                // Get course details
                $stmt = $conn->prepare("SELECT course_code FROM courses WHERE course_id = ?");
                $stmt->bind_param("i", $course_id);
                $stmt->execute();
                $course = $stmt->get_result()->fetch_assoc();
                
                // Notify students about new assignment
                $stmt = $conn->prepare("
                    SELECT student_id 
                    FROM enrollments 
                    WHERE course_id = ?
                ");
                $stmt->bind_param("i", $course_id);
                $stmt->execute();
                $students = $stmt->get_result();
                
                while ($student = $students->fetch_assoc()) {
                    $message = "New assignment: $title for {$course['course_code']}";
                    $link = "/soams/student/assignments.php?action=view&id=$new_assignment_id";
                    addNotification(
                        $student['student_id'],
                        'new_assignment',
                        $message,
                        $link
                    );
                }
                
                //EMAIL NOTIFICATION
                $stmt = $conn->prepare("
                    SELECT u.email, u.full_name 
                    FROM enrollments e
                    JOIN users u ON e.student_id = u.user_id
                    WHERE e.course_id = ?
                ");
                $stmt->bind_param("i", $course_id);
                $stmt->execute();
                $students = $stmt->get_result();
                
                while ($student = $students->fetch_assoc()) {
                    $subject = "New Assignment: " . $title;
                    $body = "
                        <h3>New Assignment Notification</h3>
                        <p>Hello {$student['full_name']},</p>
                        <p>A new assignment has been posted for {$course['course_code']}:</p>
                        <p><strong>{$title}</strong></p>
                        <p>Deadline: " . date('M j, Y H:i', strtotime($deadline)) . "</p>
                        <p>Description: " . nl2br($description) . "</p>
                        <p>Please log in to SOAMS to view and submit the assignment.</p>
                        <p>Best regards,<br>SOAMS Team</p>
                    ";
                    
                    sendEmail($student['email'], $subject, $body);
                }
            } else {
                $error = "Failed to create assignment. Please try again.";
            }
        } else {
            $error = "Invalid course or you are not the lecturer for this course.";
        }
    }
}

// Get assignment details if viewing specific assignment
if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
    $assignment_id = sanitizeInput($_GET['id']);
    
    $stmt = $conn->prepare("
        SELECT a.*, c.course_code, c.course_name, 
               c.study_programme, c.academic_year, c.semester
        FROM assignments a
        JOIN courses c ON a.course_id = c.course_id
        WHERE a.assignment_id = ? AND c.lecturer_id = ?
    ");
    $stmt->bind_param("ii", $assignment_id, $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $assignment = $result->fetch_assoc();
        
        // Get submissions for this assignment
        $stmt = $conn->prepare("
            SELECT s.*, u.full_name AS student_name, u.email AS student_email, 
                   g.marks, g.feedback, g.graded_at
            FROM submissions s
            JOIN users u ON s.student_id = u.user_id
            LEFT JOIN grades g ON s.submission_id = g.submission_id
            WHERE s.assignment_id = ?
            ORDER BY s.submitted_at DESC
        ");
        $stmt->bind_param("i", $assignment_id);
        $stmt->execute();
        $submissions = $stmt->get_result();
        
        // DEADLINE REMINDER NOTIFICATION
        // Check if deadline is within 24 hours and not passed
        $deadline = strtotime($assignment['deadline']);
        $now = time();
        $hours_until_deadline = ($deadline - $now) / 3600;
        
        if ($hours_until_deadline > 0 && $hours_until_deadline <= 24) {
            // Create notification for lecturer
            $message = "Deadline approaching: {$assignment['title']} for {$assignment['course_code']}";
            $link = "/soams/lecturer/assignments.php?action=view&id=$assignment_id";
            addNotification($lecturer_id, 'deadline_reminder', $message, $link);
        }
    } else {
        $error = "Assignment not found or you are not the lecturer for this course.";
    }
} else {
    // List all assignments for lecturer's courses
    $stmt = $conn->prepare("
        SELECT a.assignment_id, a.title, a.deadline, c.course_code, c.course_name,
               c.study_programme, c.academic_year, c.semester,
               COUNT(s.submission_id) AS submissions_count,
               COUNT(CASE WHEN s.status = 'graded' THEN 1 END) AS graded_count
        FROM assignments a
        JOIN courses c ON a.course_id = c.course_id
        LEFT JOIN submissions s ON a.assignment_id = s.assignment_id
        WHERE c.lecturer_id = ?
        GROUP BY a.assignment_id
        ORDER BY a.deadline ASC
    ");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $assignments = $stmt->get_result();
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
            <p><strong>Study Programme:</strong> <?php echo $assignment['study_programme']; ?></p>
            <p><strong>Academic Year:</strong> <?php echo $assignment['academic_year']; ?></p>
            <p><strong>Semester:</strong> Year <?php echo substr($assignment['semester'], 0, 1); ?>, Semester <?php echo substr($assignment['semester'], 2, 1); ?></p>
            <p><strong>Deadline:</strong> <?php echo date('M j, Y H:i', strtotime($assignment['deadline'])); ?></p>
            <p><strong>Max Marks:</strong> <?php echo $assignment['max_marks']; ?></p>
            <p><strong>Description:</strong></p>
            <div class="border p-3 mb-3"><?php echo nl2br($assignment['description']); ?></div>
            
            <hr>
            
            <h5>Submissions</h5>
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
                            <?php while ($submission = $submissions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $submission['student_name']; ?><br><small><?php echo $submission['student_email']; ?></small></td>
                                    <td><?php echo date('M j, Y H:i', strtotime($submission['submitted_at'])); ?></td>
                                    <td>
                                        <?php if ($submission['status'] == 'graded'): ?>
                                            <span class="badge bg-success">Graded</span>
                                        <?php elseif ($submission['status'] == 'late'): ?>
                                            <span class="badge bg-warning">Late</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Submitted</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($submission['status'] == 'graded'): ?>
                                            <?php echo $submission['marks']; ?>/<?php echo $assignment['max_marks']; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/soams/lecturer/grading.php?submission_id=<?php echo $submission['submission_id']; ?>" class="btn btn-sm btn-primary">
                                            <?php echo $submission['status'] == 'graded' ? 'View/Edit' : 'Grade'; ?>
                                        </a>
                                        <a href="/soams/uploads/<?php echo $submission['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    No submissions yet for this assignment.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <a href="/soams/lecturer/assignments.php" class="btn btn-secondary">Back to All Assignments</a>
<?php elseif (isset($_GET['action']) && $_GET['action'] === 'create'): ?>
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">Create New Assignment</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label for="course_id" class="form-label">Course</label>
                    <select class="form-select" id="course_id" name="course_id" required>
                        <option value="">Select Course</option>
                        <?php
                        $stmt = $conn->prepare("
                            SELECT course_id, course_code, course_name, 
                                   study_programme, academic_year, semester 
                            FROM courses 
                            WHERE lecturer_id = ?
                        ");
                        $stmt->bind_param("i", $lecturer_id);
                        $stmt->execute();
                        $courses = $stmt->get_result();
                        
                        while ($course = $courses->fetch_assoc()): 
                            $semester_label = "Yr. " . substr($course['semester'], 0, 1) . 
                                              " Sem. " . substr($course['semester'], 2, 1);
                        ?>
                            <option value="<?php echo $course['course_id']; ?>">
                                <?php echo "{$course['course_code']} - {$course['course_name']} 
                                      ({$course['study_programme']}, 
                                      {$semester_label}, 
                                      {$course['academic_year']})"; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="title" class="form-label">Assignment Title</label>
                    <input type="text" class="form-control" id="title" name="title" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="5"></textarea>
                </div>
                <div class="mb-3">
                    <label for="deadline" class="form-label">Deadline</label>
                    <input type="datetime-local" class="form-control" id="deadline" name="deadline" required>
                </div>
                <div class="mb-3">
                    <label for="max_marks" class="form-label">Maximum Marks</label>
                    <input type="number" class="form-control" id="max_marks" name="max_marks" min="1" required>
                </div>
                <button type="submit" name="create_assignment" class="btn btn-primary">Create Assignment</button>
                <a href="/soams/lecturer/assignments.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>My Assignments</h4>
        <a href="/soams/lecturer/assignments.php?action=create" class="btn btn-primary">
            <i class="bi bi-plus"></i> Create Assignment
        </a>
    </div>
    
    <div class="card">
        <div class="card-body">
            <?php if ($assignments->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Assignment</th>
                                <th>Programme</th>
                                <th>Year/Sem</th>
                                <th>Deadline</th>
                                <th>Submissions</th>
                                <th>Graded</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($assignment = $assignments->fetch_assoc()): 
                                $semester_label = "Y" . substr($assignment['semester'], 0, 1) . 
                                                 ".S" . substr($assignment['semester'], 2, 1);
                            ?>
                                <tr>
                                    <td><?php echo $assignment['course_code']; ?></td>
                                    <td><?php echo $assignment['title']; ?></td>
                                    <td><?php echo $assignment['study_programme']; ?></td>
                                    <td><?php echo $semester_label; ?></td>
                                    <td><?php echo date('M j, Y H:i', strtotime($assignment['deadline'])); ?></td>
                                    <td><?php echo $assignment['submissions_count']; ?></td>
                                    <td><?php echo $assignment['graded_count']; ?></td>
                                    <td>
                                        <a href="/soams/lecturer/assignments.php?action=view&id=<?php echo $assignment['assignment_id']; ?>" class="btn btn-sm btn-primary">View</a>
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