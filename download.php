<?php
require_once __DIR__ . '/includes/auth.php';

if(isset($_GET['file'])) {
    $validFiles = [
        'student_guide' => 'SOAMS_Student_Manual.pdf',
        'lecturer_guide' => 'SOAMS_Lecturer_Manual.pdf'
    ];
    
    $file = $_GET['file'];
    
    if(array_key_exists($file, $validFiles)) {
        $filename = $validFiles[$file];
        $filepath = __DIR__ . '/guides/' . $filename;
        
        if(file_exists($filepath)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            readfile($filepath);
            exit;
        }
    }
}

// Invalid file request
header('Location: support.php');
exit;
?>