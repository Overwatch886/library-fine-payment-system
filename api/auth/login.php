<?php
/*
 * POST /api/auth/login.php
 * Body: user_type ("student" | "administrator"), username, password
 * Students may use their matric number or username.
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('POST');
$input = getInput();

$userType = requireString($input, 'user_type');
$username = requireString($input, 'username');
$password = requireString($input, 'password');

$user = (new Auth())->login($userType, $username, $password);

respond(true, 'Welcome, ' . $user['name'] . '.', $user);
