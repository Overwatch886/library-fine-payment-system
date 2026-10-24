<?php
require_once __DIR__ . '/../config/init.php';

$auth = new Auth();
$auth->requireLoginForWeb('../');

include __DIR__ . '/../views/payments/receipt.html';
