<?php
require_once __DIR__ . '/config/init.php';

Auth::startSession();
$auth = new Auth();
$user = $auth->requireStudentForWeb();

include __DIR__ . '/views/student/my_loans.html';
