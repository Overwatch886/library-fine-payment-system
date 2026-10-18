<?php
/*
 * Everything the librarian (administrator) dashboard needs.
 */
class Librarian
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** The librarian's own profile (no password hash). */
    public function getProfile(int $librarianId): array
    {
        $stmt = $this->db->prepare(
            'SELECT librarian_id, full_name, username, email, phone, created_at
               FROM librarians WHERE librarian_id = ?'
        );
        $stmt->execute([$librarianId]);
        $librarian = $stmt->fetch();

        if (!$librarian) {
            throw new AppException('Librarian not found.', 404);
        }
        return $librarian;
    }

    /** Headline numbers for the dashboard, all in one query. */
    public function getStats(): array
    {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM books)                                     AS total_books,
                    (SELECT COUNT(*) FROM book_copies)                               AS total_copies,
                    (SELECT COUNT(*) FROM book_copies WHERE status = 'borrowed')     AS copies_out,
                    (SELECT COUNT(*) FROM v_loan_details WHERE is_overdue = 1)       AS overdue_loans,
                    (SELECT COUNT(*) FROM students)                                  AS total_students,
                    (SELECT COUNT(*) FROM fines WHERE status = 'unpaid')             AS unpaid_fines_count,
                    (SELECT COALESCE(SUM(amount), 0) FROM fines WHERE status = 'unpaid')         AS unpaid_fines_total,
                    (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'successful')  AS payments_collected";

        return $this->db->query($sql)->fetch();
    }

    /**
     * All loans, newest first, with optional filters:
     *   $status  'active' | 'overdue' | 'returned' | '' (all)
     *   $matric  only this student's loans
     *   $search  matches book title or student name
     */
    public function getLoans(string $status = '', string $matric = '', string $search = ''): array
    {
        $sql = 'SELECT v.*, f.fine_id, f.amount AS fine_amount, f.status AS fine_status
                  FROM v_loan_details v
                  LEFT JOIN fines f ON f.loan_id = v.loan_id
                 WHERE 1 = 1';
        $params = [];

        if ($status === 'active') {
            $sql .= ' AND v.return_date IS NULL';
        } elseif ($status === 'overdue') {
            $sql .= ' AND v.is_overdue = 1';
        } elseif ($status === 'returned') {
            $sql .= ' AND v.return_date IS NOT NULL';
        } elseif ($status !== '') {
            throw new AppException('status must be active, overdue or returned.', 400);
        }

        if ($matric !== '') {
            $sql .= ' AND v.matric_number = ?';
            $params[] = $matric;
        }

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $sql .= ' AND (v.book_title LIKE ? OR v.student_name LIKE ?)';
            array_push($params, $like, $like);
        }

        $sql .= ' ORDER BY v.borrow_date DESC, v.loan_id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $loans = $stmt->fetchAll();

        foreach ($loans as &$loan) {
            $loan['fine_so_far'] = Fine::formatAmount(Fine::calculate($loan['days_overdue']));
        }
        unset($loan);

        return $loans;
    }

    /** Every student with a summary of their borrowing. $search matches name or matric number. */
    public function getStudentRecords(string $search = ''): array
    {
        $sql = "SELECT s.matric_number, s.full_name, s.department, s.faculty, s.level,
                       s.hall_of_residence, s.email, s.phone,
                       (SELECT COUNT(*) FROM loans l WHERE l.matric_number = s.matric_number)          AS total_loans,
                       (SELECT COUNT(*) FROM loans l
                         WHERE l.matric_number = s.matric_number AND l.return_date IS NULL)            AS active_loans,
                       (SELECT COUNT(*) FROM v_loan_details v
                         WHERE v.matric_number = s.matric_number AND v.is_overdue = 1)                AS overdue_loans,
                       (SELECT COALESCE(SUM(f.amount), 0) FROM fines f
                         WHERE f.matric_number = s.matric_number AND f.status = 'unpaid')             AS unpaid_fines_total
                  FROM students s";
        $params = [];

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $sql .= ' WHERE s.full_name LIKE ? OR s.matric_number LIKE ?';
            array_push($params, $like, $like);
        }

        $sql .= ' ORDER BY s.full_name';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
