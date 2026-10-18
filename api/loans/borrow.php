<?php
/*
 * POST /api/loans/borrow.php   (students only)
 * Body: book_id, due_date (optional, "YYYY-MM-DD", 1–14 days from today; default 14 days)
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('POST');
$matric = (new Auth())->requireStudent();
$input = getInput();

$bookId = requireInt($input, 'book_id');
$dueDate = optionalString($input, 'due_date');

$loan = (new Loan())->borrow($matric, $bookId, $dueDate === '' ? null : $dueDate);

respond(true, 'You borrowed "' . $loan['book_title'] . '". Please return it by ' . $loan['due_date'] . '.', $loan, 201);
