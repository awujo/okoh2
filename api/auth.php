<?php
require_once 'session.php';
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email']) || $_SESSION['2fa_verified'] !== true) {
    header("Location: ../getIn/login.html");
    exit;
} else {
    include_once 'db.php';
}
