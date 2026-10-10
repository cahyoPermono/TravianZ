<?php
session_start();
$_SESSION['username'] = 'nusa';
$_SESSION['sessid'] = 'c766f616baf581d237fcaafbb99bdcf6';
$_SESSION['checker'] = '12345678901234567890123456789012';
$_SESSION['mchecker'] = '12345678901234567890123456789012';
$to = isset($_GET['to']) ? $_GET['to'] : 'dorf1.php';
header('Location: ' . $to);
