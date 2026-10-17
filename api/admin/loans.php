<?php
/*
 * GET /api/admin/loans.php?status=overdue&matric_number=232118&q=yellow   (librarians only)
 * All loans, newest first. Every filter is optional:
 *   status        active | overdue | returned
 *   matric_number one student's loans
 *   q             search book title or student name
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('GET');
(new Auth())->requireLibrarian();

$loans = (new Librarian())->getLoans(
    optionalString($_GET, 'status'),
    optionalString($_GET, 'matric_number'),
    optionalString($_GET, 'q')
);

respond(true, count($loans) . ' loan(s) found.', $loans);
