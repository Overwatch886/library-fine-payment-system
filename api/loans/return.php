<?php
/*
 * POST /api/loans/return.php
 * Body: loan_id
 * Students can return their own loans; librarians can return any loan.
 * If the book is late, a fine is created and returned in data.fine.
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('POST');
$user = (new Auth())->requireLogin();
$input = getInput();

$loanId = requireInt($input, 'loan_id');
$onlyForMatric = $user['role'] === 'student' ? $user['id'] : null;

$result = (new Loan())->returnBook($loanId, $onlyForMatric);

if ($result['fine'] !== null) {
    $message = 'Book returned ' . $result['fine']['days_late'] . ' day(s) late. A fine of ₦'
        . number_format((float) $result['fine']['amount'], 2) . ' has been added.';
} else {
    $message = 'Book returned on time. Thank you!';
}

respond(true, $message, $result);
