<?php
/*
 * SIMULATED fine payments (no real money moves).
 *
 *  - card numbers ending in 0000 are declined; any other card succeeds
 *  - bank_transfer and ussd always succeed
 *  - EVERY attempt is saved in `payments`, including failed ones
 *  - the fine becomes 'paid' only after a successful payment of the full amount
 *  - card numbers are never saved
 */
class Payment
{
    public const CHANNELS = ['card', 'bank_transfer', 'ussd'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Try to pay one of the student's fines in full.
     * Returns the receipt. Check $receipt['status']: 'successful' or 'failed'.
     */
    public function pay(string $matric, int $fineId, string $channel, ?string $cardNumber = null): array
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            throw new AppException('channel must be one of: ' . implode(', ', self::CHANNELS) . '.', 400);
        }

        $digits = '';
        if ($channel === 'card') {
            $digits = preg_replace('/[\s-]/', '', (string) $cardNumber);   // allow "4111 1111 ..." or "4111-1111-..."
            if (!preg_match('/^\d{12,19}$/', $digits)) {
                throw new AppException('Please enter a valid card number (12 to 19 digits).', 400);
            }
        }

        $this->db->beginTransaction();
        try {
            // Lock the fine so it cannot be paid twice at the same moment.
            $stmt = $this->db->prepare(
                'SELECT fine_id, matric_number, amount, status FROM fines WHERE fine_id = ? FOR UPDATE'
            );
            $stmt->execute([$fineId]);
            $fine = $stmt->fetch();

            if (!$fine || $fine['matric_number'] !== $matric) {
                throw new AppException('Fine not found.', 404);
            }
            if ($fine['status'] === 'paid') {
                throw new AppException('This fine has already been paid.', 409);
            }

            // The simulated "bank" decision.
            $failed = ($channel === 'card' && str_ends_with($digits, '0000'));
            $status = $failed ? 'failed' : 'successful';
            $failureReason = $failed
                ? 'Card declined by the bank (simulated: card numbers ending in 0000 always fail).'
                : null;

            $reference = $this->generateReference();

            // Always record the attempt, successful or not. The amount is always
            // the full fine, so a successful payment clears it completely.
            $stmt = $this->db->prepare(
                'INSERT INTO payments (fine_id, matric_number, amount, payment_channel, status,
                                       reference, failure_reason, time_paid)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$fineId, $matric, $fine['amount'], $channel, $status, $reference, $failureReason]);

            if (!$failed) {
                $stmt = $this->db->prepare("UPDATE fines SET status = 'paid', paid_at = NOW() WHERE fine_id = ?");
                $stmt->execute([$fineId]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->getReceipt($reference, $matric);
    }

    /**
     * Receipt for one payment attempt, found by its reference.
     * Pass $onlyForMatric for students (they only see their own receipts);
     * librarians pass null.
     */
    public function getReceipt(string $reference, ?string $onlyForMatric = null): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.reference, p.status, p.amount, p.payment_channel, p.failure_reason, p.time_paid,
                    p.matric_number, v.student_name,
                    f.fine_id, f.days_late, f.status AS fine_status,
                    v.loan_id, v.book_title, v.borrow_date, v.due_date, v.return_date
               FROM payments p
               JOIN fines f          ON f.fine_id = p.fine_id
               JOIN v_loan_details v ON v.loan_id = f.loan_id
              WHERE p.reference = ?'
        );
        $stmt->execute([$reference]);
        $receipt = $stmt->fetch();

        if (!$receipt || ($onlyForMatric !== null && $receipt['matric_number'] !== $onlyForMatric)) {
            throw new AppException('Receipt not found.', 404);
        }
        return $receipt;
    }

    /** A unique reference like RCPT-20261017-7KQ2XM. */
    private function generateReference(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // no 0/O or 1/I, easier to read out
        $check = $this->db->prepare('SELECT 1 FROM payments WHERE reference = ?');

        do {
            $random = '';
            for ($i = 0; $i < 6; $i++) {
                $random .= $characters[random_int(0, strlen($characters) - 1)];
            }
            $reference = 'RCPT-' . date('Ymd') . '-' . $random;

            $check->execute([$reference]);
        } while ($check->fetchColumn());   // already used? try again (very unlikely)

        return $reference;
    }
}
