<?php
require_once __DIR__.'/config/init.php';
(new Auth())->requireLibrarian();
require __DIR__.'/views/admin/dashboard_view.html';
