<?php
/*
 * POST /api/loans/return.php
 * Body: loan_id
 * Librarians confirm receipt of the physical copy before returning a loan.
 * If the book is late, a fine is created and returned in data.fine.
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('POST');
(new Auth())->requireLibrarian();
$input = getInput();

$loanId = requireInt($input, 'loan_id');
$result = (new Loan())->returnBook($loanId);

if ($result['fine'] !== null) {
    $message = 'Book returned ' . $result['fine']['days_late'] . ' day(s) late. A fine of ₦'
        . number_format((float) $result['fine']['amount'], 2) . ' has been added.';
} else {
    $message = 'Book returned on time. Thank you!';
}

respond(true, $message, $result);
