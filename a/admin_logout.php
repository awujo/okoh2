<?php
// admin_logout.php
require_once 'admin_auth.php';

adminLogout();
header("Location: admin_login.php");
exit;
?>