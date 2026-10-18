<?php
/*
 * GET /api/fines/list.php   (students only)
 * The student's saved fines, fines still building up on overdue books, and total unpaid.
 */
require_once __DIR__ . '/../bootstrap.php';

requireMethod('GET');
$matric = (new Auth())->requireStudent();

$result = (new Fine())->listForStudent($matric);

respond(true, 'You owe ₦' . number_format((float) $result['total_unpaid'], 2) . ' in unpaid fines.', $result);
