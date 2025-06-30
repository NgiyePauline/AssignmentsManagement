<?php
// Email configuration
define('SMTP_FROM', 'soams@gmail.com');
define('SMTP_FROM_NAME', 'SOAMS System');

function sendEmail($to, $subject, $body) {
    // Set email headers
    $headers = [
        'From' => SMTP_FROM_NAME . ' <' . SMTP_FROM . '>',
        'Reply-To' => SMTP_FROM,
        'Content-Type' => 'text/html; charset=UTF-8',
        'X-Mailer' => 'PHP/' . phpversion()
    ];
    
    // Convert headers array to string format
    $headersString = '';
    foreach ($headers as $key => $value) {
        $headersString .= "$key: $value\r\n";
    }
    
    // Send the email
    $success = mail($to, $subject, $body, $headersString);
    
    if (!$success) {
        error_log("Message could not be sent. Mail error: " . error_get_last()['message']);
        return false;
    }
    
    return true;
}