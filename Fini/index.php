<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: /Fini/dashboard.php');
} else {
    header('Location: /Fini/login.php');
}
exit();
?>
