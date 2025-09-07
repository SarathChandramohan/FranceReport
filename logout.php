<?php
// logout.php (Corrected)
require_once 'session-management.php';

// The new logoutUser() handles its own DB connection safely.
logoutUser();
?>
