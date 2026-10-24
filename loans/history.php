<?php
require_once __DIR__ . '/../config/init.php';

$auth = new Auth();
$auth->requireStudentForWeb('../');

include __DIR__ . '/../views/loans/history.html';
