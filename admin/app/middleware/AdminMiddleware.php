<?php
// app/middleware/AuthMiddleware.php

function requireAuth() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit;
    }
}

function requireRole($allowedRoles = []) {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit;
    }
    
    if (!empty($allowedRoles) && !in_array($_SESSION['admin_role'], $allowedRoles)) {
        header("Location: index.php?error=access_denied");
        exit;
    }
}