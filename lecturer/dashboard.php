<?php
require $_SERVER['DOCUMENT_ROOT'] . '/SOAMS/includes/auth.php';
requireRole('lecturer');

$lecturer_id = $_SESSION['user_id'];

// Get lecturer's courses
$stmt = $conn->prepare("
    SELECT c.course_id, c.course_code, c.course_name, 
           COUNT(DISTINCT e.student_id) AS student_count
    FROM courses c
    LEFT JOIN enrollments e ON c.course_id = e.course_id
    WHERE c.lecturer_id = ?
    GROUP BY c.course_id
");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$courses = $stmt->get_result();

// Get assignments needing grading
$stmt = $conn->prepare("
    SELECT a.assignment_id, a.title, c.course_code, 
           COUNT(s.submission_id) AS submissions_count
    FROM assignments a
    JOIN courses c ON a.course_id = c.course_id
    LEFT JOIN submissions s ON a.assignment_id = s.assignment_id AND s.status = 'submitted'
    WHERE c.lecturer_id = ?
    GROUP BY a.assignment_id
    HAVING submissions_count > 0
    ORDER BY a.deadline ASC
    LIMIT 5
");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$assignments_to_grade = $stmt->get_result();

// Get recent assignments
$stmt = $conn->prepare("
    SELECT a.assignment_id, a.title, c.course_code, a.deadline
    FROM assignments a
    JOIN courses c ON a.course_id = c.course_id
    WHERE c.lecturer_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$recent_assignments = $stmt->get_result();

$pageTitle = "Lecturer Dashboard";
require_once __DIR__ . '/../includes/header.php';
?>

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
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo $course['course_code']; ?></strong> - <?php echo $course['course_name']; ?>
                                </div>
                                <span class="badge bg-primary rounded-pill"><?php echo $course['student_count']; ?> students</span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p>You are not teaching any courses yet.</p>
                <?php endif; ?>
                <div class="mt-3">
                    <a href="/soams/lecturer/courses.php?action=create" class="btn btn-sm btn-primary">Create New Course</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Assignments Needing Grading</h5>
            </div>
            <div class="card-body">
                <?php if ($assignments_to_grade->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Assignment</th>
                                    <th>Submissions</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($assignment = $assignments_to_grade->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $assignment['course_code']; ?></td>
                                        <td><?php echo $assignment['title']; ?></td>
                                        <td><?php echo $assignment['submissions_count']; ?></td>
                                        <td>
                                            <a href="/soams/lecturer/grading.php?assignment_id=<?php echo $assignment['assignment_id']; ?>" class="btn btn-sm btn-primary">Grade</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No assignments need grading at this time.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Recent Assignments</h5>
            </div>
            <div class="card-body">
                <?php if ($recent_assignments->num_rows > 0): ?>
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
                                <?php while ($assignment = $recent_assignments->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $assignment['course_code']; ?></td>
                                        <td><?php echo $assignment['title']; ?></td>
                                        <td><?php echo date('M j, Y H:i', strtotime($assignment['deadline'])); ?></td>
                                        <td>
                                            <a href="/soams/lecturer/assignments.php?action=view&id=<?php echo $assignment['assignment_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No recent assignments.</p>
                <?php endif; ?>
                <div class="mt-3">
                    <a href="/soams/lecturer/assignments.php?action=create" class="btn btn-sm btn-primary">Create New Assignment</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- <?php require_once __DIR__ . '/../includes/footer.php'; ?> -->