-- =============================================================
-- UI Library borrowing + fine payment system: schema
-- Drops and recreates the `ui_library` database.
-- WARNING: running this deletes all existing data in ui_library.
-- =============================================================

DROP DATABASE IF EXISTS ui_library;
CREATE DATABASE ui_library
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE ui_library;

-- -------------------------------------------------------------
-- People
-- -------------------------------------------------------------
CREATE TABLE students (
    matric_number      VARCHAR(20)  NOT NULL,
    full_name          VARCHAR(100) NOT NULL,
    hall_of_residence  VARCHAR(100) NOT NULL,
    department         VARCHAR(100) NOT NULL,
    faculty            VARCHAR(100) NOT NULL,
    level              SMALLINT UNSIGNED NOT NULL,
    phone              VARCHAR(20)  NOT NULL,
    email              VARCHAR(150) NOT NULL,
    username           VARCHAR(50)  NOT NULL,
    password_hash      VARCHAR(255) NOT NULL,
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (matric_number),
    UNIQUE KEY uq_students_email (email),
    UNIQUE KEY uq_students_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE librarians (
    librarian_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name      VARCHAR(100) NOT NULL,
    phone          VARCHAR(20)  NOT NULL,
    email          VARCHAR(150) NOT NULL,
    username       VARCHAR(50)  NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (librarian_id),
    UNIQUE KEY uq_librarians_email (email),
    UNIQUE KEY uq_librarians_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Catalogue
-- -------------------------------------------------------------
CREATE TABLE books (
    book_id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title             VARCHAR(255) NOT NULL,
    author            VARCHAR(255) NOT NULL,
    isbn              VARCHAR(20)  NOT NULL,
    publisher         VARCHAR(150) NULL,
    publication_year  SMALLINT UNSIGNED NULL,
    category          VARCHAR(50)  NOT NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (book_id),
    UNIQUE KEY uq_books_isbn (isbn),
    KEY idx_books_title (title),
    KEY idx_books_author (author),
    KEY idx_books_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per physical copy on the shelf.
CREATE TABLE book_copies (
    copy_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    book_id  INT UNSIGNED NOT NULL,
    status   ENUM('available','borrowed') NOT NULL DEFAULT 'available',
    PRIMARY KEY (copy_id),
    KEY idx_book_copies_book_status (book_id, status),
    CONSTRAINT fk_book_copies_book
        FOREIGN KEY (book_id) REFERENCES books (book_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Circulation
-- -------------------------------------------------------------
-- "Overdue" is NOT a status: it is return_date IS NULL AND due_date < CURDATE().
CREATE TABLE loans (
    loan_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    copy_id        INT UNSIGNED NOT NULL,
    matric_number  VARCHAR(20)  NOT NULL,
    borrow_date    DATE NOT NULL,
    due_date       DATE NOT NULL,
    return_date    DATE NULL DEFAULT NULL,
    status         ENUM('borrowed','returned') NOT NULL DEFAULT 'borrowed',
    PRIMARY KEY (loan_id),
    KEY idx_loans_copy (copy_id),
    KEY idx_loans_student_return (matric_number, return_date),
    KEY idx_loans_due_return (due_date, return_date),
    CONSTRAINT chk_loans_due_after_borrow CHECK (due_date >= borrow_date),
    CONSTRAINT fk_loans_copy
        FOREIGN KEY (copy_id) REFERENCES book_copies (copy_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_loans_student
        FOREIGN KEY (matric_number) REFERENCES students (matric_number)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Created when a late book is returned. One fine per loan.
CREATE TABLE fines (
    fine_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    loan_id        INT UNSIGNED NOT NULL,
    matric_number  VARCHAR(20)  NOT NULL,
    days_late      INT UNSIGNED NOT NULL,
    amount         DECIMAL(10,2) NOT NULL,
    status         ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at        DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (fine_id),
    UNIQUE KEY uq_fines_loan (loan_id),
    KEY idx_fines_student_status (matric_number, status),
    CONSTRAINT fk_fines_loan
        FOREIGN KEY (loan_id) REFERENCES loans (loan_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_fines_student
        FOREIGN KEY (matric_number) REFERENCES students (matric_number)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every payment attempt, successful or failed. Card numbers are never stored.
CREATE TABLE payments (
    payment_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fine_id          INT UNSIGNED NOT NULL,
    matric_number    VARCHAR(20)  NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    payment_channel  ENUM('card','bank_transfer','ussd') NOT NULL,
    status           ENUM('successful','failed') NOT NULL,
    reference        VARCHAR(30)  NOT NULL,
    failure_reason   VARCHAR(255) NULL DEFAULT NULL,
    time_paid        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_id),
    UNIQUE KEY uq_payments_reference (reference),
    KEY idx_payments_fine (fine_id),
    KEY idx_payments_student (matric_number),
    CONSTRAINT fk_payments_fine
        FOREIGN KEY (fine_id) REFERENCES fines (fine_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_payments_student
        FOREIGN KEY (matric_number) REFERENCES students (matric_number)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Views
-- -------------------------------------------------------------
-- Catalogue with copy counts (books with no copies show 0 / 0).
CREATE VIEW v_book_availability AS
SELECT
    b.book_id,
    b.title,
    b.author,
    b.isbn,
    b.publisher,
    b.publication_year,
    b.category,
    COUNT(c.copy_id)                                   AS total_copies,
    CAST(COALESCE(SUM(c.status = 'available'), 0) AS UNSIGNED) AS available_copies
FROM books b
LEFT JOIN book_copies c ON c.book_id = b.book_id
GROUP BY b.book_id, b.title, b.author, b.isbn, b.publisher,
         b.publication_year, b.category;

-- Loans with student and book info, plus computed overdue fields.
CREATE VIEW v_loan_details AS
SELECT
    l.loan_id,
    l.copy_id,
    bk.book_id,
    bk.title          AS book_title,
    l.matric_number,
    s.full_name       AS student_name,
    l.borrow_date,
    l.due_date,
    l.return_date,
    l.status,
    (l.return_date IS NULL AND l.due_date < CURDATE())          AS is_overdue,
    CASE
        WHEN l.return_date IS NULL AND l.due_date < CURDATE()
            THEN DATEDIFF(CURDATE(), l.due_date)
        ELSE 0
    END                                                         AS days_overdue
FROM loans l
JOIN students    s  ON s.matric_number = l.matric_number
JOIN book_copies c  ON c.copy_id       = l.copy_id
JOIN books       bk ON bk.book_id      = c.book_id;
