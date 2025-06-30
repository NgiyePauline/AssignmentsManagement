<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('student');

$student_id = $_SESSION['user_id'];

// Fetch academic information if not in session
if (!isset($_SESSION['study_programme']) || !isset($_SESSION['academic_year']) || !isset($_SESSION['semester'])) {
    $stmt = $conn->prepare("SELECT study_programme, academic_year, semester FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $_SESSION['study_programme'] = $user['study_programme'];
        $_SESSION['academic_year'] = $user['academic_year'];
        $_SESSION['semester'] = $user['semester'];
    }
}

// Handle course enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_course'])) {
    $course_id = sanitizeInput($_POST['course_id']);
    
    // Check if already enrolled
    $stmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ? AND course_id = ?");
    $stmt->bind_param("ii", $student_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = "You are already enrolled in this course";
    } else {
        // Enroll student
        $stmt = $conn->prepare("
            INSERT INTO enrollments (student_id, course_id, enrolled_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->bind_param("ii", $student_id, $course_id);
        
        if ($stmt->execute()) {
            $success = "Enrollment successful!";
            // Send notification to lecturer
            $stmt = $conn->prepare("
                SELECT u.email, u.full_name, c.course_code, c.course_name
                FROM courses c
                JOIN users u ON c.lecturer_id = u.user_id
                WHERE c.course_id = ?
            ");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $course_info = $stmt->get_result()->fetch_assoc();
            
            $subject = "New Enrollment: " . $_SESSION['full_name'];
            $body = "
                <h3>New Course Enrollment</h3>
                <p>Hello {$course_info['full_name']},</p>
                <p>Student {$_SESSION['full_name']} has enrolled in your course:</p>
                <p><strong>{$course_info['course_code']} - {$course_info['course_name']}</strong></p>
                <p>Best regards,<br>SOAMS Team</p>
            ";
            
            sendEmail($course_info['email'], $subject, $body);
        } else {
            $error = "Failed to enroll. Please try again.";
        }
    }
}

// Handle course unenrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unenroll_course'])) {
    $course_id = sanitizeInput($_POST['course_id']);
    
    $stmt = $conn->prepare("
        DELETE FROM enrollments 
        WHERE student_id = ? AND course_id = ?
    ");
    $stmt->bind_param("ii", $student_id, $course_id);
    
    if ($stmt->execute()) {
        $success = "Unenrolled successfully!";
    } else {
        $error = "Failed to unenroll. Please try again.";
    }
}

// Get enrolled courses
$stmt = $conn->prepare("
    SELECT c.course_id, c.course_code, c.course_name, 
           c.study_programme, c.academic_year, c.semester,
           u.full_name AS lecturer_name
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    JOIN users u ON c.lecturer_id = u.user_id
    WHERE e.student_id = ?
    ORDER BY e.enrolled_at DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$enrolled_courses = $stmt->get_result();

// Get available courses (not enrolled, same programme, and same semester/year)
$stmt = $conn->prepare("
    SELECT c.*, u.full_name AS lecturer_name
    FROM courses c
    JOIN users u ON c.lecturer_id = u.user_id
    LEFT JOIN enrollments e ON c.course_id = e.course_id AND e.student_id = ?
    WHERE e.enrollment_id IS NULL 
      AND c.study_programme = ?
      AND c.academic_year = ?
      AND c.semester = ?
    ORDER BY c.course_code ASC
");
$stmt->bind_param("isss", 
    $student_id, 
    $_SESSION['study_programme'], 
    $_SESSION['academic_year'], 
    $_SESSION['semester']
);
$stmt->execute();
$available_courses = $stmt->get_result();

$pageTitle = "My Courses";
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">My Enrolled Courses</h5>
            </div>
            <div class="card-body">
                <?php if ($enrolled_courses->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Programme</th>
                                    <th>Year/Sem</th>
                                    <th>Lecturer</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($course = $enrolled_courses->fetch_assoc()): 
                                    $semester_label = "Y" . substr($course['semester'], 0, 1) . 
                                                     ".S" . substr($course['semester'], 2, 1);
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $course['course_code']; ?></strong><br>
                                            <?php echo $course['course_name']; ?>
                                        </td>
                                        <td><?php echo $course['study_programme']; ?></td>
                                        <td><?php echo $semester_label; ?></td>
                                        <td><?php echo $course['lecturer_name']; ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                                <button type="submit" name="unenroll_course" class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Are you sure you want to unenroll from this course?');">
                                                    Unenroll
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        You haven't enrolled in any courses yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">Available Courses</h5>
            </div>
            <div class="card-body">
                <?php if ($available_courses->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($course = $available_courses->fetch_assoc()): 
                            $semester_label = "Y" . substr($course['semester'], 0, 1) . 
                                             ".S" . substr($course['semester'], 2, 1);
                        ?>
                            <div class="list-group-item">
                                <h6 class="mb-1"><?php echo $course['course_code']; ?> - <?php echo $course['course_name']; ?></h6>
                                <div class="d-flex justify-content-between small mb-2">
                                    <span class="text-muted"><?php echo $semester_label; ?></span>
                                    <span><?php echo $course['lecturer_name']; ?></span>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                    <button type="submit" name="enroll_course" class="btn btn-sm btn-success w-100">
                                        Enroll Now
                                    </button>
                                </form>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        No available courses for your programme at this time.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-body">
                <h6>Your Academic Information</h6>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Study Programme:</span>
                        <strong><?php echo htmlspecialchars($_SESSION['study_programme'] ?? 'Not specified'); ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Current Academic Year:</span>
                        <strong><?php echo htmlspecialchars($_SESSION['academic_year'] ?? 'Not specified'); ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Current Semester:</span>
                        <strong>
                            <?php if (isset($_SESSION['semester'])): ?>
                                Year <?php echo substr($_SESSION['semester'], 0, 1); ?>, 
                                Semester <?php echo substr($_SESSION['semester'], 2, 1); ?>
                            <?php else: ?>
                                Not specified
                            <?php endif; ?>
                        </strong>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- <?php require_once __DIR__ . '/../includes/footer.php'; ?> -->