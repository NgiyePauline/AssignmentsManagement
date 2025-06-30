<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('student');

$student_id = $_SESSION['user_id'];

// Get student's courses
$stmt = $conn->prepare("
    SELECT c.course_id, c.course_code, c.course_name, u.full_name AS lecturer_name
    FROM courses c
    JOIN users u ON c.lecturer_id = u.user_id
    JOIN enrollments e ON c.course_id = e.course_id
    WHERE e.student_id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$courses = $stmt->get_result();

// Get upcoming assignments
$stmt = $conn->prepare("
    SELECT a.assignment_id, a.title, a.deadline, c.course_code, c.course_name
    FROM assignments a
    JOIN courses c ON a.course_id = c.course_id
    JOIN enrollments e ON c.course_id = e.course_id
    WHERE e.student_id = ? AND a.deadline > NOW()
    ORDER BY a.deadline ASC
    LIMIT 5
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$upcoming_assignments = $stmt->get_result();

// Get recent submissions
$stmt = $conn->prepare("
    SELECT s.submission_id, a.title, c.course_code, s.submitted_at, s.status, g.marks, a.max_marks
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.assignment_id
    JOIN courses c ON a.course_id = c.course_id
    LEFT JOIN grades g ON s.submission_id = g.submission_id
    WHERE s.student_id = ?
    ORDER BY s.submitted_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$recent_submissions = $stmt->get_result();

$pageTitle = "Student Dashboard";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .quick-actions .btn {
        transition: all 0.3s;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        padding: 12px 15px;
    }
    
    .quick-actions .btn i {
        font-size: 1.2rem;
        margin-right: 10px;
    }
    
    .quick-actions .btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .btn-submit {
        background: linear-gradient(135deg, #0d6efd, #0b5ed7);
        border: none;
    }
    
    .btn-courses {
        background: linear-gradient(135deg, #198754, #157347);
        border: none;
    }
    
    .btn-help {
        background: linear-gradient(135deg, #6f42c1, #d63384);
        border: none;
    }
    
    .btn-submit:hover {
        background: linear-gradient(135deg, #0b5ed7, #0a58ca);
    }
    
    .btn-courses:hover {
        background: linear-gradient(135deg, #157347, #13653f);
    }
    
    .btn-help:hover {
        background: linear-gradient(135deg, #5a32a3, #b02a6e);
    }
</style>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">My Courses</h5>
            </div>
            <div class="card-body">
                <?php if ($courses->num_rows > 0): ?>
                    <ul class="list-group">
                        <?php while ($course = $courses->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <strong><?php echo $course['course_code']; ?></strong> - <?php echo $course['course_name']; ?>
                                <br>
                                <small>Lecturer: <?php echo $course['lecturer_name']; ?></small>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p>You are not enrolled in any courses yet.</p>
                <?php endif; ?>
                <div class="mt-3">
                    <a href="/soams/student/courses.php" class="btn btn-sm btn-primary">Browse Courses</a>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions Section -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body quick-actions">
                <a href="/soams/student/assignments.php" class="btn btn-submit text-white w-100">
                    <i class="bi bi-upload"></i> Submit Assignment
                </a>
                
                <a href="/soams/student/courses.php" class="btn btn-courses text-white w-100">
                    <i class="bi bi-journals"></i> View Courses
                </a>
                
                <a href="/soams/support.php" class="btn btn-help text-white w-100">
                    <i class="bi bi-question-circle"></i> Get Help
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Upcoming Assignments</h5>
            </div>
            <div class="card-body">
                <?php if ($upcoming_assignments->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Assignment</th>
                                    <th>Deadline</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($assignment = $upcoming_assignments->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $assignment['course_code']; ?></td>
                                        <td><?php echo $assignment['title']; ?></td>
                                        <td><?php echo date('M j, Y H:i', strtotime($assignment['deadline'])); ?></td>
                                        <td>
                                            <a href="/soams/student/assignments.php?action=view&id=<?php echo $assignment['assignment_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No upcoming assignments.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Recent Submissions</h5>
            </div>
            <div class="card-body">
                <?php if ($recent_submissions->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Assignment</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                    <th>Grade</th>
                                    
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($submission = $recent_submissions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $submission['course_code']; ?></td>
                                        <td><?php echo $submission['title']; ?></td>
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
                                                <?php echo $submission['marks']; ?>/<?php echo $submission['max_marks']; ?>
                                            <?php else: ?>
                                                
                                                
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No recent submissions.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- <?php require_once __DIR__ . '/../includes/footer.php'; ?> -->