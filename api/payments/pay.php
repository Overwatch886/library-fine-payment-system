<?php
/*
 * POST /api/payments/pay.php   (students only)
 * Body: fine_id, channel ("card" | "bank_transfer" | "ussd"), card_number (card only)
 * SIMULATED: a card number ending in 0000 is declined, anything else succeeds.
 * Failed attempts are still saved; the response is then success:false with HTTP 402.
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('POST');
$matric = (new Auth())->requireStudent();
$input = getInput();

$fineId = requireInt($input, 'fine_id');
$channel = requireString($input, 'channel');
$cardNumber = optionalString($input, 'card_number');

$receipt = (new Payment())->pay($matric, $fineId, $channel, $cardNumber);

if ($receipt['status'] === 'failed') {
    respond(false, 'Payment failed: ' . $receipt['failure_reason'], $receipt, 402);
}

respond(true, 'Payment successful. Your fine has been cleared.', $receipt, 201);
