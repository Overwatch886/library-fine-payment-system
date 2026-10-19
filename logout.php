<?php
require_once __DIR__.'/config/init.php';

(new Auth())->logout();
$target = __DIR__ . 'login.php';
header('Location: '. $target);
exit;
