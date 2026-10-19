<?php
require_once __DIR__ . '/config/init.php';

(new Auth())->logout();

header('Location: login.php');
exit;
