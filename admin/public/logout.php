<?php
// public/logout.php
require_once '../bootstrap/app.php';

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>