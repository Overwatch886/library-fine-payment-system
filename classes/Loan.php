<?php
/*
 * Borrowing and returning books.
 *
 * Library rules:
 *  - due date is 1–14 days from today (default 14)
 *  - at most 3 books out at once
 *  - no borrowing while the student has an unpaid fine
 *  - returning late creates ONE fine for that loan
 *
 * Borrow and return change several tables, so each runs inside a
 * TRANSACTION: either every change is saved, or (on any error) none are.
 */
class Loan
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * A student borrows any available copy of a book.
     * $dueDate is "YYYY-MM-DD" or null for the default (14 days).
     * Returns the new loan.
     */
    public function borrow(string $matric, int $bookId, ?string $dueDate = null): array
    {
        $loanDays = $this->loanDaysFor($dueDate);

        $this->db->beginTransaction();
        try {
            // Lock this student's row until we commit. If the same student sends
            // two borrow requests at once, the second waits here, so the
            // "max 3 loans" check below cannot be bypassed.
            $stmt = $this->db->prepare('SELECT matric_number FROM students WHERE matric_number = ? FOR UPDATE');
            $stmt->execute([$matric]);
            if (!$stmt->fetch()) {
                throw new AppException('Student not found.', 404);
            }

            // Rule: no borrowing with an unpaid fine.
            $unpaid = (new Fine())->getUnpaidTotal($matric);
            if ($unpaid > 0) {
                throw new AppException(
                    'You have an unpaid fine of ₦' . number_format($unpaid, 2) . '. Please pay it before borrowing.',
                    409
                );
            }

            // Rule: at most MAX_ACTIVE_LOANS books out at once.
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM loans WHERE matric_number = ? AND return_date IS NULL');
            $stmt->execute([$matric]);
            if ((int) $stmt->fetchColumn() >= MAX_ACTIVE_LOANS) {
                throw new AppException(
                    'You already have ' . MAX_ACTIVE_LOANS . ' books borrowed. Return one before borrowing another.',
                    409
                );
            }

            // The book must exist (throws 404 if not).
            $book = (new Book())->getById($bookId);

            // Pick one available copy and LOCK it, so two students
            // can never be given the same copy at the same moment.
            $stmt = $this->db->prepare(
                "SELECT copy_id FROM book_copies
                  WHERE book_id = ? AND status = 'available'
                  ORDER BY copy_id
                  LIMIT 1
                    FOR UPDATE"
            );
            $stmt->execute([$bookId]);
            $copyId = $stmt->fetchColumn();
            if ($copyId === false) {
                throw new AppException('Sorry, all copies of "' . $book['title'] . '" are currently borrowed.', 409);
            }

            $stmt = $this->db->prepare("UPDATE book_copies SET status = 'borrowed' WHERE copy_id = ?");
            $stmt->execute([$copyId]);

            $stmt = $this->db->prepare(
                "INSERT INTO loans (copy_id, matric_number, borrow_date, due_date, status)
                 VALUES (?, ?, CURDATE(), CURDATE() + INTERVAL ? DAY, 'borrowed')"
            );
            $stmt->execute([$copyId, $matric, $loanDays]);
            $loanId = (int) $this->db->lastInsertId();

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();   // undo everything from this borrow
            throw $e;
        }

        return $this->getById($loanId);
    }

    /**
     * Return a borrowed book. If it is late, a fine is created.
     * Pass $onlyForMatric when a STUDENT is returning, so they can only
     * return their own loans. Librarians pass null and can return any loan.
     * Returns ['loan' => ..., 'fine' => ... or null].
     */
    public function returnBook(int $loanId, ?string $onlyForMatric = null): array
    {
        $this->db->beginTransaction();
        try {
            // Lock the loan so it cannot be returned twice at the same moment.
            $stmt = $this->db->prepare(
                'SELECT loan_id, copy_id, matric_number, return_date,
                        DATEDIFF(CURDATE(), due_date) AS days_late
                   FROM loans
                  WHERE loan_id = ?
                    FOR UPDATE'
            );
            $stmt->execute([$loanId]);
            $loan = $stmt->fetch();

            // A student asking about someone else's loan gets "not found".
            if (!$loan || ($onlyForMatric !== null && $loan['matric_number'] !== $onlyForMatric)) {
                throw new AppException('Loan not found.', 404);
            }
            if ($loan['return_date'] !== null) {
                throw new AppException('This book has already been returned.', 409);
            }

            $stmt = $this->db->prepare(
                "UPDATE loans SET return_date = CURDATE(), status = 'returned' WHERE loan_id = ?"
            );
            $stmt->execute([$loanId]);

            $stmt = $this->db->prepare("UPDATE book_copies SET status = 'available' WHERE copy_id = ?");
            $stmt->execute([$loan['copy_id']]);

            // Late? Create the fine for this loan (only one fine per loan).
            $fine = null;
            $daysLate = (int) $loan['days_late'];
            if ($daysLate > 0) {
                $amount = Fine::calculate($daysLate);
                $stmt = $this->db->prepare(
                    "INSERT INTO fines (loan_id, matric_number, days_late, amount, status)
                     VALUES (?, ?, ?, ?, 'unpaid')"
                );
                $stmt->execute([$loanId, $loan['matric_number'], $daysLate, $amount]);

                $fine = [
                    'fine_id'   => (int) $this->db->lastInsertId(),
                    'days_late' => $daysLate,
                    'amount'    => Fine::formatAmount($amount),
                    'status'    => 'unpaid',
                ];
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return ['loan' => $this->getById($loanId), 'fine' => $fine];
    }

    /** One loan with book title, student name, overdue info and fine so far. */
    public function getById(int $loanId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM v_loan_details WHERE loan_id = ?');
        $stmt->execute([$loanId]);
        $loan = $stmt->fetch();

        if (!$loan) {
            throw new AppException('Loan not found.', 404);
        }
        $loan['fine_so_far'] = Fine::formatAmount(Fine::calculate($loan['days_overdue']));
        return $loan;
    }

    /** Books a student has right now (soonest due first). */
    public function getActiveForStudent(string $matric): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM v_loan_details
              WHERE matric_number = ? AND return_date IS NULL
              ORDER BY due_date'
        );
        $stmt->execute([$matric]);
        $loans = $stmt->fetchAll();

        foreach ($loans as &$loan) {
            $loan['fine_so_far'] = Fine::formatAmount(Fine::calculate($loan['days_overdue']));
        }
        unset($loan);

        return $loans;
    }

    /** Every loan a student has ever had (newest first), with any fine attached. */
    public function getHistoryForStudent(string $matric): array
    {
        $stmt = $this->db->prepare(
            'SELECT v.*, f.fine_id, f.amount AS fine_amount, f.status AS fine_status
               FROM v_loan_details v
               LEFT JOIN fines f ON f.loan_id = v.loan_id
              WHERE v.matric_number = ?
              ORDER BY v.borrow_date DESC, v.loan_id DESC'
        );
        $stmt->execute([$matric]);
        return $stmt->fetchAll();
    }

    /**
     * Turn the chosen due date into a number of days from today (1–14).
     * No date chosen → the maximum (14 days).
     */
    private function loanDaysFor(?string $dueDate): int
    {
        if ($dueDate === null || $dueDate === '') {
            return MAX_LOAN_DAYS;
        }

        $date = DateTime::createFromFormat('!Y-m-d', $dueDate);
        if (!$date || $date->format('Y-m-d') !== $dueDate) {
            throw new AppException('due_date must be a date like 2026-10-31 (YYYY-MM-DD).', 400);
        }

        // Ask MySQL, so "today" is the same day the database uses for CURDATE().
        $stmt = $this->db->prepare('SELECT DATEDIFF(?, CURDATE())');
        $stmt->execute([$dueDate]);
        $days = (int) $stmt->fetchColumn();

        if ($days < 1 || $days > MAX_LOAN_DAYS) {
            throw new AppException('The due date must be between 1 and ' . MAX_LOAN_DAYS . ' days from today.', 400);
        }
        return $days;
    }
}
