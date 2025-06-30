<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    if (hasRole('lecturer')) {
        header("Location: /soams/lecturer/dashboard.php");
    } else {
        header("Location: /soams/student/dashboard.php");
    }
    exit();
}

$pageTitle = "Home";
require_once __DIR__ . '/includes/header.php';
?>

<div class="jumbotron bg-light p-5 rounded-lg m-3">
    <h1 class="display-4">Welcome to SOAMS</h1>
    <p class="lead">Student Online Assignment Management System</p>
    <hr class="my-4">
    <p>Streamline your assignment submission and grading process with our easy-to-use platform.</p>
    <div class="mt-4">
        <a class="btn btn-outline-primary btn-lg" href="/soams/support.php" role="button">User Guide</a>
        <a class="btn btn-primary btn-lg mr-3" href="/soams/login.php" role="button">Login</a>
        <a class="btn btn-outline-primary btn-lg" href="/soams/register.php" role="button">Register</a>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-people-fill"></i> For Students</h5>
                <p class="card-text">
                    Submit assignments online, track deadlines, and receive feedback from your lecturers.
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-person-badge-fill"></i> For Lecturers</h5>
                <p class="card-text">Create and manage assignments, grade submissions, and provide feedback to students. </p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-clock-history"></i> 24/7 Access</h5>
                <p class="card-text">
                    Access the system anytime, anywhere to manage your academic work efficiently.</p>
            </div>
        </div>
    </div>
</div>

<!-- <?php require_once __DIR__ . '/includes/footer.php'; ?> -->