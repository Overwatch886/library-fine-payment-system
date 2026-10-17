<?php
/*
 * GET /api/books/categories.php
 * List of category names for the search filter dropdown.
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('GET');
(new Auth())->requireLogin();

respond(true, 'Categories loaded.', (new Book())->getCategories());
