<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header("Location: /soams/");
    exit();
}

$error = '';
$success = '';

if (isset($_GET['token'])) {
    $token = sanitizeInput($_GET['token']);
    
    // Validate token
    $stmt = $conn->prepare("
        SELECT prt.*, u.email, u.full_name 
        FROM password_reset_tokens prt
        JOIN users u ON prt.user_id = u.user_id
        WHERE prt.token = ? AND prt.used = 0 AND prt.expires_at > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $token_data = $result->fetch_assoc();
        
        // Handle password reset
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (empty($password)) {
                $error = "Password is required";
            } elseif (strlen($password) < 8) {
                $error = "Password must be at least 8 characters long";
            } elseif ($password !== $confirm_password) {
                $error = "Passwords do not match";
            } else {
                // Update password
                $hashed_password = hashPassword($password);
                $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->bind_param("si", $hashed_password, $token_data['user_id']);
                
                if ($stmt->execute()) {
                    // Mark token as used
                    $stmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token_id = ?");
                    $stmt->bind_param("i", $token_data['token_id']);
                    $stmt->execute();
                    
                    $success = "Password reset successfully! You can now login with your new password.";
                } else {
                    $error = "Failed to reset password. Please try again.";
                }
            }
        }
    } else {
        $error = "Invalid or expired reset token.";
    }
} else {
    $error = "No reset token provided.";
}

$pageTitle = "Reset Password";
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="card-title mb-0">Reset Password</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php if (!isset($_GET['token'])): ?>
                        <div class="text-center mt-3">
                            <a href="/soams/forgot-password.php" class="btn btn-secondary">Request New Reset Link</a>
                        </div>
                    <?php endif; ?>
                <?php elseif ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <div class="text-center mt-3">
                        <a href="/soams/login.php" class="btn btn-primary">Login Now</a>
                    </div>
                <?php else: ?>
                    <p>Resetting password for: <strong><?php echo $token_data['email']; ?></strong></p>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary password-toggle" type="button">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary password-toggle" type="button">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>