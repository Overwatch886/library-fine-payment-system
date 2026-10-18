<?php
/*
 * POST /api/auth/logout.php
 * Ends the session. Safe to call even when not logged in.
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('POST');

(new Auth())->logout();

respond(true, 'You have been logged out.');
