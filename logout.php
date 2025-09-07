<?php
require_once 'session-management.php';
// The connectDB function is required by logoutUser, so we need to include the connection file as well.
require_once 'db-connection.php'; 

logoutUser();
?>
