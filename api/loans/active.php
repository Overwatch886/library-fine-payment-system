<?php
/*
 * GET /api/loans/active.php   (students only)
 * The logged-in student's books that are still out, with is_overdue and fine_so_far.
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('GET');
$matric = (new Auth())->requireStudent();

$loans = (new Loan())->getActiveForStudent($matric);

respond(true, count($loans) . ' active loan(s).', $loans);
