<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('lecturer');

$lecturer_id = $_SESSION['user_id'];

// Handle new course creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    $course_code = sanitizeInput($_POST['course_code']);
    $course_name = sanitizeInput($_POST['course_name']);
    $study_programme = sanitizeInput($_POST['study_programme']);
    $academic_year = sanitizeInput($_POST['academic_year']);
    $semester = sanitizeInput($_POST['semester']);
    $description = sanitizeInput($_POST['description']);
    
    // Validate inputs
    if (empty($course_code) || empty($course_name) || empty($study_programme) || empty($academic_year) || empty($semester)) {
        $error = "All fields are required";
    } else {
        // Check if course code already exists
        $stmt = $conn->prepare("SELECT course_id FROM courses WHERE course_code = ?");
        $stmt->bind_param("s", $course_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Course code already exists";
        } else {
            // Insert new course
            $stmt = $conn->prepare("
                INSERT INTO courses (course_code, course_name, lecturer_id, description, study_programme, academic_year, semester)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssissss", $course_code, $course_name, $lecturer_id, $description, $study_programme, $academic_year, $semester);
            
            if ($stmt->execute()) {
                $success = "Course created successfully!";
                header("Location: /soams/lecturer/courses.php");
                exit();
            } else {
                $error = "Failed to create course. Please try again.";
            }
        }
    }
}

// Handle course update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_course'])) {
    $course_id = sanitizeInput($_POST['course_id']);
    $course_code = sanitizeInput($_POST['course_code']);
    $course_name = sanitizeInput($_POST['course_name']);
    $study_programme = sanitizeInput($_POST['study_programme']);
    $academic_year = sanitizeInput($_POST['academic_year']);
    $semester = sanitizeInput($_POST['semester']);
    $description = sanitizeInput($_POST['description']);
    
    // Validate inputs
    if (empty($course_code) || empty($course_name) || empty($study_programme) || empty($academic_year) || empty($semester)) {
        $error = "All fields are required";
    } else {
        // Check if course code already exists (excluding current course)
        $stmt = $conn->prepare("SELECT course_id FROM courses WHERE course_code = ? AND course_id != ?");
        $stmt->bind_param("si", $course_code, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Course code already exists";
        } else {
            // Update course
            $stmt = $conn->prepare("
                UPDATE courses 
                SET course_code = ?, course_name = ?, description = ?,
                    study_programme = ?, academic_year = ?, semester = ?
                WHERE course_id = ? AND lecturer_id = ?
            ");
            $stmt->bind_param("ssssssii", $course_code, $course_name, $description, 
                              $study_programme, $academic_year, $semester, 
                              $course_id, $lecturer_id);
            
            if ($stmt->execute()) {
                $success = "Course updated successfully!";
                header("Location: /soams/lecturer/courses.php?action=view&id=".$course_id);
                exit();
            } else {
                $error = "Failed to update course. Please try again.";
            }
        }
    }
}

// Handle course deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
    $course_id = sanitizeInput($_POST['course_id']);
    
    // First delete enrollments (if foreign key constraints don't handle this)
    $stmt = $conn->prepare("DELETE FROM enrollments WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    
    // Then delete the course
    $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ? AND lecturer_id = ?");
    $stmt->bind_param("ii", $course_id, $lecturer_id);
    
    if ($stmt->execute()) {
        $success = "Course deleted successfully!";
        header("Location: /soams/lecturer/courses.php");
        exit();
    } else {
        $error = "Failed to delete course. Please try again.";
    }
}

// Get course details if viewing specific course
if (isset($_GET['action']) && ($_GET['action'] === 'view' || $_GET['action'] === 'edit') && isset($_GET['id'])) {
    $course_id = sanitizeInput($_GET['id']);
    
    $stmt = $conn->prepare("
        SELECT c.*, u.full_name AS lecturer_name,
               COUNT(DISTINCT e.student_id) AS student_count
        FROM courses c
        JOIN users u ON c.lecturer_id = u.user_id
        LEFT JOIN enrollments e ON c.course_id = e.course_id
        WHERE c.course_id = ? AND c.lecturer_id = ?
        GROUP BY c.course_id
    ");
    $stmt->bind_param("ii", $course_id, $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $course = $result->fetch_assoc();
        
        // Get enrolled students (only for view action)
        if ($_GET['action'] === 'view') {
            $stmt = $conn->prepare("
                SELECT u.user_id, u.full_name, u.email, e.enrolled_at
                FROM enrollments e
                JOIN users u ON e.student_id = u.user_id
                WHERE e.course_id = ?
                ORDER BY e.enrolled_at DESC
            ");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $students = $stmt->get_result();
        }
    } else {
        $error = "Course not found or you are not the lecturer.";
        header("Location: /soams/lecturer/courses.php");
        exit();
    }
} else {
    // List all courses for this lecturer
    $stmt = $conn->prepare("
        SELECT c.*, COUNT(DISTINCT e.student_id) AS student_count
        FROM courses c
        LEFT JOIN enrollments e ON c.course_id = e.course_id
        WHERE c.lecturer_id = ?
        GROUP BY c.course_id
        ORDER BY c.course_code ASC
    ");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $courses = $stmt->get_result();
}

$pageTitle = isset($course) ? $course['course_code'] : "My Courses";
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($course)): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Course Details</h5>
                <div>
                    <a href="/soams/lecturer/courses.php?action=edit&id=<?php echo $course['course_id']; ?>" class="btn btn-warning btn-sm">Edit Course</a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <h4><?php echo $course['course_code']; ?> - <?php echo $course['course_name']; ?></h4>
            <p><strong>Lecturer:</strong> <?php echo $course['lecturer_name']; ?></p>
            <p><strong>Study Programme:</strong> <?php echo $course['study_programme']; ?></p>
            <p><strong>Academic Year:</strong> <?php echo $course['academic_year']; ?></p>
            <p><strong>Semester:</strong> Year <?php echo substr($course['semester'], 0, 1); ?>, Semester <?php echo substr($course['semester'], 2, 1); ?></p>
            <p><strong>Students Enrolled:</strong> <?php echo $course['student_count']; ?></p>
            <p><strong>Description:</strong></p>
            <div class="border p-3 mb-3"><?php echo nl2br($course['description']); ?></div>
            
            <hr>
            
            <h5>Enrolled Students</h5>
            <?php if ($students->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Enrolled On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $students->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $student['full_name']; ?></td>
                                    <td><?php echo $student['email']; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($student['enrolled_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    No students enrolled yet.
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer">
            <a href="/soams/lecturer/courses.php" class="btn btn-secondary">
                <i class="bi bi-list-ul"></i> View All Courses</a>
        </div>
    </div>

<?php elseif (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($course)): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Edit Course: <?php echo $course['course_code']; ?></h4>
        <div>
            <a href="/soams/lecturer/courses.php" class="btn btn-secondary me-2">
                <i class="bi bi-list-ul"></i> View Courses
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">Edit Course Details</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                <div class="mb-3">
                    <label for="course_code" class="form-label">Course Code</label>
                    <input type="text" class="form-control" id="course_code" name="course_code" 
                           value="<?php echo htmlspecialchars($course['course_code']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="course_name" class="form-label">Course Name</label>
                    <input type="text" class="form-control" id="course_name" name="course_name" 
                           value="<?php echo htmlspecialchars($course['course_name']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="study_programme" class="form-label">Study Programme</label>
                    <select class="form-select" id="study_programme" name="study_programme" required>
                        <option value="">Select Programme</option>
                        <option value="BSC Information Technology" <?php echo $course['study_programme'] === 'BSC Information Technology' ? 'selected' : ''; ?>>BSC Information Technology</option>
                        <option value="BSC Computer Science" <?php echo $course['study_programme'] === 'BSC Computer Science' ? 'selected' : ''; ?>>BSC Computer Science</option>
                        <option value="BSC Software Engineering" <?php echo $course['study_programme'] === 'BSC Software Engineering' ? 'selected' : ''; ?>>BSC Software Engineering</option>
                        
                    </select>
                </div>
                <div class="mb-3">
                    <label for="academic_year" class="form-label">Academic Year</label>
                    <input type="text" class="form-control" id="academic_year" name="academic_year" 
                           pattern="\d{4}/\d{4}" placeholder="e.g., 2023/2024" 
                           value="<?php echo htmlspecialchars($course['academic_year']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="semester" class="form-label">Year & Semester</label>
                    <select class="form-select" id="semester" name="semester" required>
                        <option value="">Select Semester</option>
                        <option value="1.1" <?php echo $course['semester'] === '1.1' ? 'selected' : ''; ?>>Year 1 - Semester 1</option>
                        <option value="1.2" <?php echo $course['semester'] === '1.2' ? 'selected' : ''; ?>>Year 1 - Semester 2</option>
                        <option value="2.1" <?php echo $course['semester'] === '2.1' ? 'selected' : ''; ?>>Year 2 - Semester 1</option>
                        <option value="2.2" <?php echo $course['semester'] === '2.2' ? 'selected' : ''; ?>>Year 2 - Semester 2</option>
                        <option value="3.1" <?php echo $course['semester'] === '3.1' ? 'selected' : ''; ?>>Year 3 - Semester 1</option>
                        <option value="3.2" <?php echo $course['semester'] === '3.2' ? 'selected' : ''; ?>>Year 3 - Semester 2</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="5"><?php 
                        echo htmlspecialchars($course['description']); 
                    ?></textarea>
                </div>
                <button type="submit" name="update_course" class="btn btn-primary">Update Course</button>
                <a href="/soams/lecturer/courses.php?action=view&id=<?php echo $course['course_id']; ?>" 
                   class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>

<?php elseif (isset($_GET['action']) && $_GET['action'] === 'create'): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Create New Course</h4>
        <div>
            <a href="/soams/lecturer/courses.php" class="btn btn-secondary me-2">
                <i class="bi bi-list-ul"></i> View Courses
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">Create New Course</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label for="course_code" class="form-label">Course Code</label>
                    <input type="text" class="form-control" id="course_code" name="course_code" required>
                </div>
                <div class="mb-3">
                    <label for="course_name" class="form-label">Course Name</label>
                    <input type="text" class="form-control" id="course_name" name="course_name" required>
                </div>
                <div class="mb-3">
                    <label for="study_programme" class="form-label">Study Programme</label>
                    <select class="form-select" id="study_programme" name="study_programme" required>
                        <option value="">Select Programme</option>
                        <option value="BSC Information Technology">BSC Information Technology</option>
                        <option value="BSC Computer Science">BSC Computer Science</option>
                        <option value="BSC Software Engineering">BSC Software Engineering</option>
                        
                    </select>
                </div>
                <div class="mb-3">
                    <label for="academic_year" class="form-label">Academic Year</label>
                    <input type="text" class="form-control" id="academic_year" name="academic_year" 
                           pattern="\d{4}/\d{4}" placeholder="e.g., 2023/2024" required>
                </div>
                <div class="mb-3">
                    <label for="semester" class="form-label">Year & Semester</label>
                    <select class="form-select" id="semester" name="semester" required>
                        <option value="">Select Semester</option>
                        <option value="1.1">Year 1 - Semester 1</option>
                        <option value="1.2">Year 1 - Semester 2</option>
                        <option value="2.1">Year 2 - Semester 1</option>
                        <option value="2.2">Year 2 - Semester 2</option>
                        <option value="3.1">Year 3 - Semester 1</option>
                        <option value="3.2">Year 3 - Semester 2</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="5"></textarea>
                </div>
                <button type="submit" name="create_course" class="btn btn-primary">Create Course</button>
                <a href="/soams/lecturer/courses.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>

<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>My Courses</h4>
        <div>
            <a href="/soams/lecturer/courses.php?action=create" class="btn btn-primary">
                <i class="bi bi-plus"></i> Create Course
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <?php if ($courses->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Programme</th>
                                <th>Year/Sem</th>
                                <th>Students</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($course = $courses->fetch_assoc()): 
                                $semester_label = "Y" . substr($course['semester'], 0, 1) . 
                                                 ".S" . substr($course['semester'], 2, 1);
                            ?>
                                <tr>
                                    <td><?php echo $course['course_code']; ?></td>
                                    <td><?php echo $course['course_name']; ?></td>
                                    <td><?php echo $course['study_programme']; ?></td>
                                    <td><?php echo $semester_label; ?></td>
                                    <td><?php echo $course['student_count']; ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="/soams/lecturer/courses.php?action=view&id=<?php echo $course['course_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                            <a href="/soams/lecturer/courses.php?action=edit&id=<?php echo $course['course_id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                            <form method="POST" action="/soams/lecturer/courses.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this course?');">
                                                <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                                <button type="submit" name="delete_course" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    You haven't created any courses yet. <a href="/soams/lecturer/courses.php?action=create" class="alert-link">Create your first course</a>.
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- <?php require_once __DIR__ . '/../includes/footer.php'; ?> -->