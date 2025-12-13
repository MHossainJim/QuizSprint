<?php
require_once 'config/db.php';

// Clear session and redirect
session_destroy();
redirect('index.php', 'You have been logged out successfully.', 'success');
?>