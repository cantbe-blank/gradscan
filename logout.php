<?php
session_start();
session_destroy();
header('Location: ../gradscan/login.php');
exit;
?>