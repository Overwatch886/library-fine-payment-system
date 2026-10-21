# GROUP 10 — University Library Fine Payment System

## Scenario

The University of Ibadan library wants to automate book borrowing and fine collection.
Students can borrow books. If a book is returned late, the system calculates a fine.
Students can then pay the fine online.

## Required Features

1. Student login
2. Book catalogue
3. Search books
4. Borrow book
5. Return book
6. Due date
7. Automatic fine calculation
8. Fine payment
9. Payment receipt
10. Borrowing history
11. Administrator/librarian dashboard

## Asynchronous Requirement

Book availability should be obtained without reloading the page.

```text
Search "Database Systems"
        ↓
JavaScript Fetch()
        ↓
PHP API
        ↓
MySQL
        ↓
Available copies
        ↓
Update webpage
```

## OOP

Classes: `Book`, `Student`, `Loan`, `Fine`, `Payment`, `Database`.

## Project Guidelines

This is the group semester assignment guidelines:

- Each group must demonstrate a working system, not just submit source code.
- The system must use HTML, CSS, JavaScript, PHP and MySQL.
- At least one major operation must use asynchronous communication with Fetch/AJAX.
- PHP must contain OOP classes relevant to the problem domain.
- Data must actually be stored and retrieved from MySQL.
- The project must contain at least one API endpoint.
- Payment must be implemented as a simulated/test payment, not real financial transactions.
- Students must demonstrate both successful and failed operations.
- Students should submit their database SQL file together with the project.
- Every group member should be able to explain the part of the system they worked on.

## Write-Up Structure

The various sections of the write-up:

1. Introduction
2. Problem statement
3. Objectives
4. System requirements
5. Use-case diagram
6. System architecture
7. Database/ER diagram
8. Database tables
9. Important interface screenshots
10. Explanation of PHP OOP implementation
11. Explanation of API/AJAX communication
12. Payment workflow
13. Testing
14. Conclusion
