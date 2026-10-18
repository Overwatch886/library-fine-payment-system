<?php
/*
 * GET /api/auth/me.php
 * Who is logged in? Returns the role and full profile.
 * Call this when a page loads to check the session and show the user's details.
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('GET');
$user = (new Auth())->requireLogin();

if ($user['role'] === 'student') {
    $profile = (new Student())->getProfile($user['id']);
} else {
    $profile = (new Librarian())->getProfile((int) $user['id']);
}

respond(true, 'Logged in as ' . $user['role'] . '.', ['role' => $user['role'], 'profile' => $profile]);
