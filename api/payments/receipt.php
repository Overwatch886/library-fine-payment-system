<?php
/*
 * GET /api/payments/receipt.php?reference=RCPT-20261017-7KQ2XM
 * Students see their own receipts; librarians can see any receipt.
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('GET');
$user = (new Auth())->requireLogin();

$reference = requireString($_GET, 'reference');
$onlyForMatric = $user['role'] === 'student' ? $user['id'] : null;

$receipt = (new Payment())->getReceipt($reference, $onlyForMatric);

respond(true, 'Receipt found.', $receipt);
