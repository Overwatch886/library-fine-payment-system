<?php
require_once __DIR__.'/config/init.php';
(new Auth())->requireLibrarian();
require __DIR__.'/views/admin/admin_dashboard.html';
