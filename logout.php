<?php
require_once __DIR__ . '/includes/auth.php';

session_start();
session_unset();
session_destroy();

header("Location: /soams/login.php");
exit();
?>