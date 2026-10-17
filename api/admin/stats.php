<?php
/*
 * GET /api/admin/stats.php   (librarians only)
 * Headline numbers for the dashboard.
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('GET');
(new Auth())->requireLibrarian();

respond(true, 'Dashboard stats loaded.', (new Librarian())->getStats());
