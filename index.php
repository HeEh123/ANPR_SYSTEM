<?php
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirectBasedOnRole();
} else {
    header('Location: login.php');
    exit();
}
?>