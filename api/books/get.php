<?php
/*
 * GET /api/books/get.php?id=6
 * One book with its copy counts.
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('GET');
(new Auth())->requireLogin();

$book = (new Book())->getById(requireInt($_GET, 'id'));

respond(true, 'Book found.', $book);
