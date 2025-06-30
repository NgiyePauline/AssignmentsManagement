<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = "Support & User Guide";
require_once __DIR__ . '/includes/header.php';
?>

<style>
    .support-section {
        background: white;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        padding: 30px;
        margin-bottom: 30px;
        transition: transform 0.3s;
    }
    
    .support-section:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    .guide-card {
        border: none;
        border-radius: 10px;
        overflow: hidden;
        transition: all 0.3s;
        height: 100%;
    }
    
    .guide-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    }
    
    .guide-card .card-header {
        color: white;
        font-weight: 600;
        border: none;
    }
    
    .student-header {
        background: linear-gradient(135deg, #0d6efd, #0b5ed7);
    }
    
    .lecturer-header {
        background: linear-gradient(135deg, #198754, #157347);
    }
    
    .guide-card .card-body {
        padding: 25px;
    }
    
    .guide-card .btn-download {
        border: none;
        border-radius: 30px;
        padding: 8px 20px;
        font-weight: 500;
        transition: all 0.3s;
    }
    
    .student-download {
        background: linear-gradient(135deg, #0d6efd, #0b5ed7);
        color: white;
    }
    
    .lecturer-download {
        background: linear-gradient(135deg, #198754, #157347);
        color: white;
    }
    
    .guide-card .btn-download:hover {
        transform: scale(1.05);
    }
    
    .student-download:hover {
        background: linear-gradient(135deg, #0b5ed7, #0a58ca);
    }
    
    .lecturer-download:hover {
        background: linear-gradient(135deg, #157347, #13653f);
    }
    
    .step-by-step {
        background: #f8f9fa;
        border-left: 4px solid #0d6efd;
        padding: 20px;
        border-radius: 0 8px 8px 0;
        margin-bottom: 20px;
    }
    
    .step-number {
        display: inline-block;
        background: #0d6efd;
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        text-align: center;
        line-height: 30px;
        font-weight: bold;
        margin-right: 15px;
    }
    
    .video-container {
        position: relative;
        padding-bottom: 56.25%;
        height: 0;
        overflow: hidden;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        margin: 25px 0;
        background: #000;
    }
    
    .video-container iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
    
    .support-search {
        background: linear-gradient(135deg, #0d6efd, #6610f2);
        padding: 30px;
        border-radius: 10px;
        color: white;
        margin-bottom: 40px;
    }
    
    .topic-icon {
        font-size: 2.5rem;
        color: #0d6efd;
        margin-bottom: 15px;
    }
    
    .guide-section {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 30px;
        margin-bottom: 30px;
    }
</style>

<div class="container py-5">
    <div class="text-center mb-5">
        <h3 class="display-4 fw-bold mb-3">SOAMS Support Center</h3>
        <p class="lead">Find answers, guides, and resources to help you use our platform</p>
    </div>

    <!-- <div class="support-search">
        <div class="row align-items-center">
            <div class="col-md-8 mb-4 mb-md-0">
                <h3 class="mb-3">How can we help you today?</h3>
                <div class="input-group">
                    <input type="text" class="form-control form-control-lg" placeholder="Search for help articles...">
                    <button class="btn btn-light btn-lg" type="button">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div class="bg-white text-primary p-3 rounded-circle d-inline-block">
                    <i class="bi bi-question-circle-fill" style="font-size: 3rem;"></i>
                </div>
            </div>
        </div>
    </div> -->

    <div class="guide-section">
        <h2 class="mb-4 text-center">User Guides & Manuals</h2>
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="guide-card card">
                    <div class="card-header student-header">
                        <i class="bi bi-person-badge me-2"></i> Student Guide</div>
                    <div class="card-body">
                        <p class="card-text">Comprehensive guide for students covering all aspects of SOAMS:</p>
                        <ul>
                            <li>Creating user accounts</li>
                            <li>Submitting assignments</li>
                            <li>Viewing grades and feedback</li>
                            <li>Managing course enrollments</li>
                        </ul>
                    </div>
                    <div class="card-footer bg-white border-0">
                        <a href="download.php?file=student_guide" class="btn btn-download student-download w-100">
                        <i class="bi bi-download me-1"></i> Download Student Manual</a>
                        
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="guide-card card">
                    <div class="card-header lecturer-header">
                        <i class="bi bi-person-video3 me-2"></i> Lecturer Guide</div>
                    <div class="card-body">
                        <p class="card-text">Complete guide for lecturers and instructors:</p>
                        <ul>
                            <li>Creating courses and assignments</li>
                            <li>Uploading assignments</li>
                            <li>Grading submissions</li>
                            <li>Managing student enrollments</li>
                            <li>Providing feedback</li>
                        </ul>
                    </div>
                    <div class="card-footer bg-white border-0">
                        <a href="download.php?file=lecturer_guide" class="btn btn-download lecturer-download w-100">
                          <i class="bi bi-download me-1"></i> Download Lecturer Manual</a>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-6 mb-4">
            <div class="support-section">
                <h2 class="mb-4">Getting Started</h2>
                
                <div class="step-by-step">
                    <h5><span class="step-number">1</span> Account Registration</h5>
                    <p>Create your SOAMS account to access the platform.</p>
                </div>
                
                <div class="step-by-step">
                    <h5><span class="step-number">2</span> Logging In</h5>
                    <p>Access your account using your credentials.</p>
                </div>
                
                <div class="step-by-step">
                    <h5><span class="step-number">3</span> Profile Setup</h5>
                    <p>Complete your profile to get the best experience.</p>
                </div>
                
                <div class="mt-4">
                    <!-- <a href="#" class="btn btn-primary me-2">
                        <i class="bi bi-file-earmark-text me-1"></i> View Full Guide
                    </a>
                    <a href="#" class="btn btn-outline-primary">
                        <i class="bi bi-download me-1"></i> Download PDF
                    </a> -->
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="support-section">
                <h2 class="mb-4">Quick Resources</h2>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="text-center p-3 border rounded">
                            <div class="topic-icon">
                                <i class="bi bi-journals"></i>
                            </div>
                            <h5>Courses</h5>
                            <p class="small">Managing your courses and enrollments</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="text-center p-3 border rounded">
                            <div class="topic-icon">
                                <i class="bi bi-card-checklist"></i>
                            </div>
                            <h5>Assignments</h5>
                            <p class="small">Creating and submitting assignments</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="text-center p-3 border rounded">
                            <div class="topic-icon">
                                <i class="bi bi-clipboard-check"></i>
                            </div>
                            <h5>Grading</h5>
                            <p class="small">Understanding grading and feedback</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="text-center p-3 border rounded">
                            <div class="topic-icon">
                                <i class="bi bi-people"></i>
                            </div>
                            <h5>Collaboration</h5>
                            <p class="small">Working with peers and instructors</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3 text-center">
                    <!-- <a href="#" class="btn btn-link">
                        <i class="bi bi-collection me-1"></i> View All Topics
                    </a> -->
                </div>
            </div>
        </div>
    </div>
    
    <div class="support-section">
        <h2 class="mb-4">Assignment Submission Guide (Students)</h2>
        
        <div class="step-by-step">
            <h5><span class="step-number">1</span> Access Your Assignment</h5>
            <p>Navigate to the "Assignments" section in your course dashboard to view available assignments.</p>
        </div>
        
        <div class="step-by-step">
            <h5><span class="step-number">2</span> Prepare Your Submission</h5>
            <p>Ensure your assignment meets all requirements and is in the correct file format.</p>
        </div>
        
        <div class="step-by-step">
            <h5><span class="step-number">3</span> Upload Your Work</h5>
            <p>Click the "Submit Assignment" button and upload your file(s) using the submission form.</p>
        </div>
        
        <div class="step-by-step">
            <h5><span class="step-number">4</span> Confirm Submission</h5>
            <p>Review your submission details and confirm. You'll receive a confirmation email.</p>
        </div>

    </div>
    
    <div class="support-section">
        <h2 class="mb-4">Assignment Management Guide (Lecturers)</h2>
        
        <div class="step-by-step">
            <h5><span class="step-number">1</span> Create a New Assignment</h5>
            <p>From your course dashboard, click "Create Assignment" and fill in the required details.</p>
        </div>
        
        <div class="step-by-step">
            <h5><span class="step-number">2</span> Configure Settings</h5>
            <p>Set the deadline, maximum marks, and any specific submission requirements.</p>
        </div>
        
        <div class="step-by-step">
            <h5><span class="step-number">3</span> Upload Resources</h5>
            <p>Attach any necessary files or resources that students will need to complete the assignment.</p>
        </div>
        
        <div class="step-by-step">
            <h5><span class="step-number">4</span> Publish Assignment</h5>
            <p>Review and publish the assignment. Students will receive notifications automatically.</p>
        </div>
        
        <div class="alert alert-info mt-4">
            <i class="bi bi-lightbulb me-2"></i> <strong>Pro Tip:</strong> Use the plagiarism checker option to ensure academic integrity of submissions.
        </div>
    </div>
    
    <!-- <div class="row mt-5">
        <div class="col-md-6">
            <div class="support-section">
                <h3 class="mb-4">Viewing Feedback & Grades</h3>
                <p>Learn how to access and understand your assignment feedback:</p>
                
                <ul class="mb-4">
                    <li>Navigate to the "Grades" section in your course</li>
                    <li>Click on any assignment to view detailed feedback</li>
                    <li>Download graded submissions with instructor comments</li>
                    <li>Track your progress throughout the course</li>
                </ul>
                
                <button class="btn btn-primary">
                    <i class="bi bi-play-circle me-1"></i> Watch Tutorial
                </button>
            </div>
        </div> -->
        
        <!-- <div class="col-md-6">
            <div class="support-section">
                <h3 class="mb-4">Managing Course Enrollments</h3>
                <p>How to manage your course enrollments as a student:</p>
                
                <ul class="mb-4">
                    <li>Browse available courses in your program</li>
                    <li>Enroll in courses during registration periods</li>
                    <li>Drop courses before deadlines</li>
                    <li>Access your course schedule and materials</li>
                </ul>
                
                <button class="btn btn-primary">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Download Guide
                </button>
            </div>
        </div>
    </div> -->
    
    <div class="support-section mt-5">
        <div class="row">
            <div class="col-md-8">
                <h3 class="mb-3">Need Further Assistance?</h3>
                <p class="mb-4">Our support team is ready to help you with any questions or issues you may encounter.</p>
                
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary text-white p-2 rounded-circle me-3">
                        <i class="bi bi-envelope" style="font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">Email Support</h5>
                        <p class="mb-0">support@soams.ac.ke</p>
                    </div>
                </div>
                
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-success text-white p-2 rounded-circle me-3">
                        <i class="bi bi-telephone" style="font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">Phone Support</h5>
                        <p class="mb-0">+254700123000 (Mon-Fri, 9AM-5PM)</p>
                    </div>
                </div>
                
                <!-- <div class="d-flex align-items-center">
                    <div class="bg-info text-white p-2 rounded-circle me-3">
                        <i class="bi bi-chat-dots" style="font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">Live Chat</h5>
                        <p class="mb-0">Available during business hours</p>
                    </div>
                </div>
            </div> -->
            
            <!-- <div class="col-md-4 text-center">
                <div class="bg-light p-4 rounded">
                    <i class="bi bi-headset text-primary" style="font-size: 4rem;"></i>
                    <h5 class="mt-3">24/7 Knowledge Base</h5>
                    <p>Access our comprehensive knowledge base anytime</p>
                    <button class="btn btn-outline-primary">
                        Visit Knowledge Base
                    </button>
                </div>
            </div> -->
        </div>
    </div>
</div>

<!-- <?php require_once __DIR__ . '/includes/footer.php'; ?> -->