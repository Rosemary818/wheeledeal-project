<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Redirect based on role if accessing the wrong dashboard
function checkRole($expectedRole) {
    if ($_SESSION['role'] !== $expectedRole) {
        header("Location: login.php");
        exit();
    }
}
?>
