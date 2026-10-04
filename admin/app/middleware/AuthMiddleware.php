<?php
// app/middleware/AuthMiddleware.php

function requireAuth() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit;
    }
    
    // Check if user needs to change default password
    if (isset($_SESSION['default_password_used']) && $_SESSION['default_password_used'] === true) {
        if (basename($_SERVER['PHP_SELF']) !== 'change_password.php') {
            header("Location: change_password.php");
            exit;
        }
    }
}

function requireRole($allowedRoles = []) {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit;
    }
    
    // Check if user needs to change default password (except for change_password page)
    if (isset($_SESSION['default_password_used']) && $_SESSION['default_password_used'] === true) {
        if (basename($_SERVER['PHP_SELF']) !== 'change_password.php') {
            header("Location: change_password.php");
            exit;
        }
        return true;
    }
    
    if (!empty($allowedRoles) && !in_array($_SESSION['admin_role'], $allowedRoles)) {
        header("Location: index.php?error=access_denied");
        exit;
    }
    
    return true;
}

function canResetPassword($currentUserRole, $targetUserRole) {
    $hierarchy = [
        'super_admin' => 4,
        'admin' => 3,
        'manager' => 2,
        'staff' => 1
    ];
    
    $currentLevel = isset($hierarchy[$currentUserRole]) ? $hierarchy[$currentUserRole] : 0;
    $targetLevel = isset($hierarchy[$targetUserRole]) ? $hierarchy[$targetUserRole] : 0;
    
    return $currentLevel >= 3 && $currentLevel > $targetLevel;
}
?>