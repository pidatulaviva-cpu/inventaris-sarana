<?php
session_start();
session_destroy();
// Redirect ke login - auto detect base path
$base = rtrim(str_replace('\\', '/', substr(dirname(__FILE__), strlen(str_replace('\\','/',$_SERVER['DOCUMENT_ROOT'])))), '/') . '/';
header('Location: ' . $base . 'login.php');
exit;
