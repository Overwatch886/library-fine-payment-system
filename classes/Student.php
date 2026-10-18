<?php
/*
 * A student's own profile details.
 */
class Student
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Profile + a quick summary (books out, overdue, unpaid fines).
     * The password hash is never returned.
     */
    public function getProfile(string $matric): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.matric_number, s.full_name, s.username, s.email, s.phone,
                    s.hall_of_residence, s.department, s.faculty, s.level, s.created_at,
                    (SELECT COUNT(*) FROM loans l
                      WHERE l.matric_number = s.matric_number AND l.return_date IS NULL) AS active_loans,
                    (SELECT COUNT(*) FROM v_loan_details v
                      WHERE v.matric_number = s.matric_number AND v.is_overdue = 1) AS overdue_loans,
                    (SELECT COALESCE(SUM(f.amount), 0) FROM fines f
                      WHERE f.matric_number = s.matric_number AND f.status = 'unpaid') AS unpaid_fines_total
               FROM students s
              WHERE s.matric_number = ?"
        );
        $stmt->execute([$matric]);
        $student = $stmt->fetch();

        if (!$student) {
            throw new AppException('Student not found.', 404);
        }

        // Handy for the frontend: can this student borrow right now?
        $student['can_borrow'] = $student['active_loans'] < MAX_ACTIVE_LOANS
            && (float) $student['unpaid_fines_total'] == 0;

        return $student;
    }
}
