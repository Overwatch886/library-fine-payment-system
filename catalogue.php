<?php
require_once __DIR__.'/config/init.php';
(new Auth())->requireStudentForWeb();
require __DIR__."/views/student/catalogue_view.html";
?>
