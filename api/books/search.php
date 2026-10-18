<?php
/*
 * GET /api/books/search.php?q=achebe&category=Literature
 * Both parameters are optional. Any logged-in user can search.
 * Each book includes total_copies and available_copies (live).
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('GET');
(new Auth())->requireLogin();

$books = (new Book())->search(optionalString($_GET, 'q'), optionalString($_GET, 'category'));

respond(true, count($books) . ' book(s) found.', $books);
