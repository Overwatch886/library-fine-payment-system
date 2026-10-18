<?php
/*
 * Fines: ₦100 per day late, never more than ₦5,000 (see config.php).
 *
 * A row in the `fines` table is only created when a late book is RETURNED
 * (see Loan::returnBook). While a book is still out, the fine so far is
 * worked out on the fly with calculate() and is not saved anywhere.
 */
class Fine
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** The fine for a number of days late, e.g. 7 → 700, 60 → 5000 (capped). */
    public static function calculate(int $daysLate): int
    {
        if ($daysLate <= 0) {
            return 0;
        }
        return min($daysLate * FINE_PER_DAY, FINE_CAP);
    }

    /** Money as the same "700.00" text format the database uses. */
    public static function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /** Total of a student's unpaid fines (0 if none). */
    public function getUnpaidTotal(string $matric): float
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM fines WHERE matric_number = ? AND status = 'unpaid'"
        );
        $stmt->execute([$matric]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Everything a student owes or has owed:
     *  - fines:        saved fines (unpaid first, then paid)
     *  - accruing:     books still out and overdue, with the fine so far (not saved yet)
     *  - total_unpaid: sum of the unpaid saved fines
     */
    public function listForStudent(string $matric): array
    {
        $stmt = $this->db->prepare(
            "SELECT f.fine_id, f.loan_id, v.book_title, v.due_date, v.return_date,
                    f.days_late, f.amount, f.status, f.created_at, f.paid_at
               FROM fines f
               JOIN v_loan_details v ON v.loan_id = f.loan_id
              WHERE f.matric_number = ?
              ORDER BY f.status = 'paid', f.created_at DESC"
        );
        $stmt->execute([$matric]);
        $fines = $stmt->fetchAll();

        $stmt = $this->db->prepare(
            'SELECT loan_id, book_title, due_date, days_overdue
               FROM v_loan_details
              WHERE matric_number = ? AND is_overdue = 1
              ORDER BY due_date'
        );
        $stmt->execute([$matric]);
        $accruing = $stmt->fetchAll();

        foreach ($accruing as &$loan) {
            $loan['fine_so_far'] = self::formatAmount(self::calculate($loan['days_overdue']));
        }
        unset($loan);

        return [
            'fines'        => $fines,
            'accruing'     => $accruing,
            'total_unpaid' => self::formatAmount($this->getUnpaidTotal($matric)),
        ];
    }
}
