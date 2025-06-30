<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header("Location: /SOAMS/");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitizeInput($_POST['full_name']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitizeInput($_POST['role']);
    
    // Initialize academic fields
    $study_programme = '';
    $academic_year = '';
    $semester = '';
    
    // Get academic fields if student
    if ($role === 'student') {
        $study_programme = sanitizeInput($_POST['study_programme']);
        $academic_year = sanitizeInput($_POST['academic_year']);
        $semester = sanitizeInput($_POST['semester']);
    }
    
    // Validate inputs
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = "All fields are required";
    } elseif (!in_array($role, ['student', 'lecturer'])) {
        $error = "Invalid role selected";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } 
    // Additional validation for students
    elseif ($role === 'student' && (empty($study_programme) || empty($academic_year) || empty($semester))) {
        $error = "All academic fields are required for students";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email already registered";
        } else {
            // Insert new user
            $hashed_password = hashPassword($password);
            
            if ($role === 'student') {
                $stmt = $conn->prepare("
                    INSERT INTO users (role, email, password_hash, full_name, study_programme, academic_year, semester)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssssss", $role, $email, $hashed_password, $full_name, 
                                 $study_programme, $academic_year, $semester);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO users (role, email, password_hash, full_name)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("ssss", $role, $email, $hashed_password, $full_name);
            }
            
            if ($stmt->execute()) {
                $success = "Registration successful. Please login.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}

$pageTitle = "Register";
require_once __DIR__ . '/includes/header.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const academicFields = document.getElementById('academic-fields');
    
    function toggleAcademicFields() {
        academicFields.style.display = roleSelect.value === 'student' ? 'block' : 'none';
    }
    
    // Initial toggle
    toggleAcademicFields();
    
    // Add event listener
    roleSelect.addEventListener('change', toggleAcademicFields);
});
</script>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="card-title mb-0">Register for SOAMS</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required
                               value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Register as</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] === 'student') ? 'selected' : ''; ?>>Student</option>
                            <option value="lecturer" <?php echo (isset($_POST['role']) && $_POST['role'] === 'lecturer') ? 'selected' : ''; ?>>Lecturer</option>
                        </select>
                    </div>
                    
                    <!-- Academic Fields (Visible only for students) -->
                    <div id="academic-fields" style="display: none;">
                        <div class="mb-3">
                            <label for="study_programme" class="form-label">Study Programme</label>
                            <select class="form-select" id="study_programme" name="study_programme">
                                <option value="">Select Programme</option>
                                <option value="BSC Information Technology" <?php echo (isset($_POST['study_programme']) && $_POST['study_programme'] === 'BSC Information Technology') ? 'selected' : ''; ?>>BSC Information Technology</option>
                                <option value="BSC Computer Science" <?php echo (isset($_POST['study_programme']) && $_POST['study_programme'] === 'BSC Computer Science') ? 'selected' : ''; ?>>BSC Computer Science</option>
                                <option value="BSC Software Engineering" <?php echo (isset($_POST['study_programme']) && $_POST['study_programme'] === 'BSC Software Engineering') ? 'selected' : ''; ?>>BSC Software Engineering</option>
                                <!-- Add other programmes -->
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="academic_year" class="form-label">Academic Year</label>
                            <select class="form-select" id="academic_year" name="academic_year">
                                <option value="">Select Academic Year</option>
                                <option value="2023/2024" <?php echo (isset($_POST['academic_year']) && $_POST['academic_year'] === '2023/2024') ? 'selected' : ''; ?>>2023/2024</option>
                                <option value="2024/2025" <?php echo (isset($_POST['academic_year']) && $_POST['academic_year'] === '2024/2025') ? 'selected' : ''; ?>>2024/2025</option>
                                <option value="2025/2026" <?php echo (isset($_POST['academic_year']) && $_POST['academic_year'] === '2025/2026') ? 'selected' : ''; ?>>2025/2026</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="semester" class="form-label">Current Semester</label>
                            <select class="form-select" id="semester" name="semester">
                                <option value="">Select Semester</option>
                                <option value="1.1" <?php echo (isset($_POST['semester']) && $_POST['semester'] === '1.1') ? 'selected' : ''; ?>>Year 1 - Semester 1</option>
                                <option value="1.2" <?php echo (isset($_POST['semester']) && $_POST['semester'] === '1.2') ? 'selected' : ''; ?>>Year 1 - Semester 2</option>
                                <option value="2.1" <?php echo (isset($_POST['semester']) && $_POST['semester'] === '2.1') ? 'selected' : ''; ?>>Year 2 - Semester 1</option>
                                <option value="2.2" <?php echo (isset($_POST['semester']) && $_POST['semester'] === '2.2') ? 'selected' : ''; ?>>Year 2 - Semester 2</option>
                                <option value="3.1" <?php echo (isset($_POST['semester']) && $_POST['semester'] === '3.1') ? 'selected' : ''; ?>>Year 3 - Semester 1</option>
                                <option value="3.2" <?php echo (isset($_POST['semester']) && $_POST['semester'] === '3.2') ? 'selected' : ''; ?>>Year 3 - Semester 2</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <small class="text-muted">Minimum 8 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Register</button>
                </form>
                
                <div class="mt-3 text-center">
                    <p>Already have an account? <a href="/soams/login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- <?php require_once __DIR__ . '/includes/footer.php'; ?> -->