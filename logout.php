<?php
// logout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session Variables clear karein
$_SESSION = array();

// Session Cookie delete karein agar exist karti hai
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Session Destroy karein
session_destroy();

// Redirect to Login Page
header("Location: login.php");
exit();
?>