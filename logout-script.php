<?php
// logout.php - Script to handle user logout

// Include session management
require_once 'session_check.php';

// Call the logout function
logoutUser();

// Note: logoutUser() already handles redirecting to login page and exit
?>
