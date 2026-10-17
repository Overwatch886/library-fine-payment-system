-- =============================================================
-- UI Library: development seed data
-- Run AFTER schema.sql. All dates are relative to CURDATE(), so
-- the scenarios below hold on whatever day you import this.
--
-- Every account's password is: Password123
--
-- Loan scenarios:
--   loan 1  active, on time                 (Adebayo, Intro to Algorithms)
--   loan 2  active, on time, 7-day loan     (Adebayo, Half of a Yellow Sun copy 1)
--   loan 3  active, overdue 6 days          (Chiamaka, Half of a Yellow Sun copy 2)
--   loan 4  active, overdue 56 days         (Chiamaka, Macroeconomics) -> fine so far hits the N5,000 cap
--   loan 5  returned on time                (Ibrahim, Fundamentals of Electric Circuits)
--   loan 6  returned 7 days late, UNPAID    (Funmilayo, Things Fall Apart) + 1 failed card payment
--   loan 7  returned 5 days late, PAID      (Tunde, The Concept of Law) + 1 successful payment
--   loan 8  active, on time                 (Ngozi, Brock Biology of Microorganisms)
--
-- "Half of a Yellow Sun" (book 6) has ALL of its copies borrowed.
-- Borrowed copies: 1, 13, 14, 20, 39. Every other copy is available.
-- =============================================================

USE ui_library;

SET NAMES utf8mb4;
START TRANSACTION;

-- -------------------------------------------------------------
-- Librarians
-- -------------------------------------------------------------
INSERT INTO librarians (librarian_id, full_name, phone, email, username, password_hash) VALUES
(1, 'Bolanle Akinyemi', '08031234567', 'b.akinyemi@library.ui.edu.ng', 'bakinyemi', '$2y$10$CaZnvxlXjrrAu4ymqlh4aO0Zha537Rr3AdxWqgAHK2LbFaAc.g8TW'),
(2, 'Emeka Nwosu',      '08059876543', 'e.nwosu@library.ui.edu.ng',    'enwosu',    '$2y$10$CaZnvxlXjrrAu4ymqlh4aO0Zha537Rr3AdxWqgAHK2LbFaAc.g8TW');

-- -------------------------------------------------------------
-- Students
-- -------------------------------------------------------------
INSERT INTO students (matric_number, full_name, hall_of_residence, department, faculty, level, phone, email, username, password_hash) VALUES
('221345', 'Oluwaseun Adebayo', 'Kenneth Mellanby Hall',   'Computer Science',                       'Science',         300, '08061112233', 'oadebayo345@stu.ui.edu.ng', 'oadebayo',  '$2y$10$CaZnvxlXjrrAu4ymqlh4aO0Zha537Rr3AdxWqgAHK2LbFaAc.g8TW'),
('232118', 'Chiamaka Okafor',   'Queen Idia Hall',         'Economics',                              'Economics and Management Sciences', 200, '08072223344', 'cokafor118@stu.ui.edu.ng',  'cokafor',   '$2y$10$CaZnvxlXjrrAu4ymqlh4aO0Zha537Rr3AdxWqgAHK2LbFaAc.g8TW'),
('214567', 'Ibrahim Musa',      'Sultan Bello Hall',       'Electrical and Electronic Engineering',  'Technology',      400, '08083334455', 'imusa567@stu.ui.edu.ng',    'imusa',     '$2y$10$CaZnvxlXjrrAu4ymqlh4aO0Zha537Rr3AdxWqgAHK2LbFaAc.g8TW'),
('220981', 'Funmilayo Adeyemi', 'Obafemi Awolowo Hall',    'English',                                'Arts',            300, '08094445566', 'fadeyemi981@stu.ui.edu.ng', 'fadeyemi',  '$2y$10$CaZnvxlXjrrAu4ymqlh4aO0Zha537Rr3AdxWqgAHK2LbFaAc.g8TW'),
('205643', 'Babatunde Bakare',  'Tedder Hall',             'Public and International Law',           'Law',             500, '08105556677', 'bbakare643@stu.ui.edu.ng',  'bbakare',   '$2y$10$CaZnvxlXjrrAu4ymqlh4aO0Zha537Rr3AdxWqgAHK2LbFaAc.g8TW'),
('245210', 'Ngozi Eze',         'Queen Elizabeth II Hall', 'Microbiology',                           'Science',         100, '08126667788', 'neze210@stu.ui.edu.ng',     'neze',      '$2y$10$CaZnvxlXjrrAu4ymqlh4aO0Zha537Rr3AdxWqgAHK2LbFaAc.g8TW');

-- -------------------------------------------------------------
-- Books (20)
-- -------------------------------------------------------------
INSERT INTO books (book_id, title, author, isbn, publisher, publication_year, category) VALUES
( 1, 'Introduction to Algorithms',                    'Thomas H. Cormen, Charles E. Leiserson, Ronald L. Rivest, Clifford Stein', '9780262046305', 'MIT Press',                  2022, 'Computer Science'),
( 2, 'Clean Code',                                    'Robert C. Martin',                         '9780132350884', 'Prentice Hall',              2008, 'Computer Science'),
( 3, 'Database System Concepts',                      'Abraham Silberschatz, Henry F. Korth, S. Sudarshan', '9780078022159', 'McGraw-Hill',      2019, 'Computer Science'),
( 4, 'Computer Networking: A Top-Down Approach',      'James F. Kurose, Keith W. Ross',           '9780136681557', 'Pearson',                    2021, 'Computer Science'),
( 5, 'Things Fall Apart',                             'Chinua Achebe',                            '9780385474542', 'Anchor Books',               1994, 'Literature'),
( 6, 'Half of a Yellow Sun',                          'Chimamanda Ngozi Adichie',                 '9781400095209', 'Anchor Books',               2007, 'Literature'),
( 7, 'Death and the King''s Horseman',                'Wole Soyinka',                             '9780393322996', 'W. W. Norton',               2002, 'Literature'),
( 8, 'The Famished Road',                             'Ben Okri',                                 '9780385425131', 'Anchor Books',               1993, 'Literature'),
( 9, 'Principles of Economics',                       'N. Gregory Mankiw',                        '9780357038314', 'Cengage Learning',           2020, 'Economics'),
(10, 'Macroeconomics',                                'Olivier Blanchard',                        '9780134897899', 'Pearson',                    2021, 'Economics'),
(11, 'Development as Freedom',                        'Amartya Sen',                              '9780385720274', 'Anchor Books',               2000, 'Economics'),
(12, 'Engineering Mathematics',                       'K. A. Stroud, Dexter J. Booth',            '9781352010275', 'Red Globe Press',            2020, 'Mathematics'),
(13, 'Calculus: Early Transcendentals',               'James Stewart',                            '9781285741550', 'Cengage Learning',           2015, 'Mathematics'),
(14, 'Fundamentals of Electric Circuits',             'Charles K. Alexander, Matthew N. O. Sadiku', '9780078028229', 'McGraw-Hill',              2016, 'Engineering'),
(15, 'Electronic Devices and Circuit Theory',         'Robert L. Boylestad, Louis Nashelsky',     '9780132622264', 'Pearson',                    2012, 'Engineering'),
(16, 'Gray''s Anatomy for Students',                  'Richard L. Drake, A. Wayne Vogl, Adam W. M. Mitchell', '9780323393041', 'Elsevier',       2019, 'Medicine'),
(17, 'Guyton and Hall Textbook of Medical Physiology', 'John E. Hall, Michael E. Hall',           '9780323597128', 'Elsevier',                   2020, 'Medicine'),
(18, 'The Concept of Law',                            'H. L. A. Hart',                            '9780199644704', 'Oxford University Press',    2012, 'Law'),
(19, 'A History of Nigeria',                          'Toyin Falola, Matthew M. Heaton',          '9780521681575', 'Cambridge University Press', 2008, 'History'),
(20, 'Brock Biology of Microorganisms',               'Michael T. Madigan, Kelly S. Bender, Daniel H. Buckley', '9780134261928', 'Pearson',      2017, 'Biology');

-- -------------------------------------------------------------
-- Book copies (39). Status must match the active loans below.
-- -------------------------------------------------------------
INSERT INTO book_copies (copy_id, book_id, status) VALUES
( 1,  1, 'borrowed'),   -- loan 1
( 2,  1, 'available'),
( 3,  1, 'available'),
( 4,  2, 'available'),
( 5,  2, 'available'),
( 6,  3, 'available'),
( 7,  3, 'available'),
( 8,  4, 'available'),
( 9,  5, 'available'),  -- loan 6 (returned)
(10,  5, 'available'),
(11,  5, 'available'),
(12,  5, 'available'),
(13,  6, 'borrowed'),   -- loan 2
(14,  6, 'borrowed'),   -- loan 3  (book 6 now has 0 available)
(15,  7, 'available'),
(16,  8, 'available'),
(17,  9, 'available'),
(18,  9, 'available'),
(19,  9, 'available'),
(20, 10, 'borrowed'),   -- loan 4
(21, 10, 'available'),
(22, 11, 'available'),
(23, 12, 'available'),
(24, 12, 'available'),
(25, 12, 'available'),
(26, 13, 'available'),
(27, 13, 'available'),
(28, 14, 'available'),  -- loan 5 (returned)
(29, 14, 'available'),
(30, 15, 'available'),
(31, 16, 'available'),
(32, 16, 'available'),
(33, 17, 'available'),
(34, 17, 'available'),
(35, 18, 'available'),  -- loan 7 (returned)
(36, 18, 'available'),
(37, 19, 'available'),
(38, 19, 'available'),
(39, 20, 'borrowed');   -- loan 8

-- -------------------------------------------------------------
-- Loans
-- -------------------------------------------------------------
INSERT INTO loans (loan_id, copy_id, matric_number, borrow_date, due_date, return_date, status) VALUES
(1,  1, '221345', CURDATE() - INTERVAL  5 DAY, CURDATE() + INTERVAL  9 DAY, NULL,                         'borrowed'),
(2, 13, '221345', CURDATE() - INTERVAL  3 DAY, CURDATE() + INTERVAL  4 DAY, NULL,                         'borrowed'),
(3, 14, '232118', CURDATE() - INTERVAL 20 DAY, CURDATE() - INTERVAL  6 DAY, NULL,                         'borrowed'),
(4, 20, '232118', CURDATE() - INTERVAL 70 DAY, CURDATE() - INTERVAL 56 DAY, NULL,                         'borrowed'),
(5, 28, '214567', CURDATE() - INTERVAL 30 DAY, CURDATE() - INTERVAL 16 DAY, CURDATE() - INTERVAL 18 DAY, 'returned'),
(6,  9, '220981', CURDATE() - INTERVAL 25 DAY, CURDATE() - INTERVAL 11 DAY, CURDATE() - INTERVAL  4 DAY, 'returned'),
(7, 35, '205643', CURDATE() - INTERVAL 40 DAY, CURDATE() - INTERVAL 26 DAY, CURDATE() - INTERVAL 21 DAY, 'returned'),
(8, 39, '245210', CURDATE() - INTERVAL  2 DAY, CURDATE() + INTERVAL 12 DAY, NULL,                         'borrowed');

-- -------------------------------------------------------------
-- Fines (only for late returns; created on the return date)
-- -------------------------------------------------------------
INSERT INTO fines (fine_id, loan_id, matric_number, days_late, amount, status, created_at, paid_at) VALUES
(1, 6, '220981', 7, 700.00, 'unpaid', TIMESTAMP(CURDATE() - INTERVAL  4 DAY, '10:15:00'), NULL),
(2, 7, '205643', 5, 500.00, 'paid',   TIMESTAMP(CURDATE() - INTERVAL 21 DAY, '09:40:00'), TIMESTAMP(CURDATE() - INTERVAL 20 DAY, '11:02:00'));

-- -------------------------------------------------------------
-- Payments (every attempt is recorded)
-- -------------------------------------------------------------
INSERT INTO payments (payment_id, fine_id, matric_number, amount, payment_channel, status, reference, failure_reason, time_paid) VALUES
(1, 2, '205643', 500.00, 'card', 'successful', 'UIL-PAY-SEED-000001', NULL,
    TIMESTAMP(CURDATE() - INTERVAL 20 DAY, '11:02:00')),
(2, 1, '220981', 700.00, 'card', 'failed',     'UIL-PAY-SEED-000002', 'Card declined (simulated: card number ends in 0000)',
    TIMESTAMP(CURDATE() - INTERVAL  3 DAY, '14:30:00'));

COMMIT;
