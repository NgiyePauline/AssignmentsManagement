<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header("Location: /soams/");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    
    if (empty($email)) {
        $error = "Email is required";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Generate token
            $token = generateToken();
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Delete any existing tokens for this user
            $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
            $stmt->bind_param("i", $user['user_id']);
            $stmt->execute();
            
            // Store new token
            $stmt = $conn->prepare("
                INSERT INTO password_reset_tokens (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iss", $user['user_id'], $token, $expires_at);
            
            if ($stmt->execute()) {
                // Send reset email
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/soams/reset-password.php?token=$token";
                $subject = "Password Reset Request";
                $body = "
                    <h3>Password Reset</h3>
                    <p>Hello {$user['full_name']},</p>
                    <p>We received a request to reset your password for SOAMS.</p>
                    <p>Please click the link below to reset your password:</p>
                    <p><a href='$reset_link'>$reset_link</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                    <p>Best regards,<br>SOAMS Team</p>
                ";
                
                if (sendEmail($email, $subject, $body)) {
                    $success = "Password reset link has been sent to your email.";
                } else {
                    $error = "Failed to send reset email. Please try again.";
                }
            } else {
                $error = "Failed to generate reset token. Please try again.";
            }
        } else {
            $error = "No account found with that email.";
        }
    }
}

$pageTitle = "Forgot Password";
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="card-title mb-0">Forgot Password</h4>
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
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                </form>
                <div class="mt-3 text-center">
                    <p>Remember your password? <a href="/soams/login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- <?php require_once __DIR__ . '/includes/footer.php'; ?> -->