<?php
require_once 'config/auth.php';

$auth = new Auth(null);
$auth->logout();

header('Location: index.html');
exit();
?>
