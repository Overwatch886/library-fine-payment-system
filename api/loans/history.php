<?php
/*
 * GET /api/loans/history.php   (students only)
 * Every loan the logged-in student has ever had, newest first, with any fine.
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('GET');
$matric = (new Auth())->requireStudent();

$loans = (new Loan())->getHistoryForStudent($matric);

respond(true, count($loans) . ' loan(s) in your history.', $loans);
