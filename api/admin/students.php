<?php
/*
 * GET /api/admin/students.php                         (librarians only)
 *     All students with a borrowing summary. Optional ?q= searches name or matric number.
 *
 * GET /api/admin/students.php?matric_number=220981
 *     One student's full record: profile, every loan, and fines.
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('GET');
(new Auth())->requireLibrarian();

$matric = optionalString($_GET, 'matric_number');

if ($matric === '') {
    $students = (new Librarian())->getStudentRecords(optionalString($_GET, 'q'));
    respond(true, count($students) . ' student(s) found.', $students);
}

$record = [
    'profile' => (new Student())->getProfile($matric),   // 404 if no such student
    'loans'   => (new Loan())->getHistoryForStudent($matric),
    'fines'   => (new Fine())->listForStudent($matric),
];

respond(true, 'Student record loaded.', $record);
