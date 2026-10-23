<?php
/* CLI-only bridge: Playwright asks PHP to reset/check the database owned by its launcher. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/TestEnvironment.php';

try {
    $name = getenv('LIBRARY_DB_NAME');
    $token = getenv('LIBRARY_TEST_TOKEN');
    if (!$name || !$token || !preg_match('/^ui_library_test_[a-f0-9]{16}$/D', $name)) {
        throw new RuntimeException('Missing isolated browser-test database.');
    }
    $db = Database::getConnection();
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== $name
        || $db->query('SELECT token FROM test_run_owner')->fetchColumn() !== $token) {
        throw new RuntimeException('Browser fixture ownership check failed.');
    }
    $action = $argv[1] ?? '';
    if ($action === 'reset') {
        TestEnvironment::resetOwned($db, $name, $token);
        // Extra records exist only here, never in the application database or seed submission.
        $db->exec("INSERT INTO students SELECT '299999', 'Empty History Fixture', hall_of_residence,
            department, faculty, level, phone, 'empty@example.invalid', 'empty-fixture',
            password_hash, created_at FROM students WHERE matric_number = '214567'");
        echo json_encode(['today' => $db->query('SELECT CURDATE()')->fetchColumn()]);
    } elseif ($action === 'quoted-title') {
        $stmt = $db->prepare('UPDATE books SET title = ? WHERE book_id = 5');
        $stmt->execute(['A "quoted" <img src=x onerror="window.titleInjected=1"> title']);
        echo json_encode(['updated' => true]);
    } elseif ($action === 'loan') {
        $stmt = $db->prepare('SELECT due_date, status FROM loans WHERE matric_number = ? AND copy_id IN
            (SELECT copy_id FROM book_copies WHERE book_id = ?) ORDER BY loan_id DESC LIMIT 1');
        $stmt->execute([$argv[2] ?? '', $argv[3] ?? '']);
        echo json_encode($stmt->fetch());
    } else {
        throw new RuntimeException('Unknown fixture action.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
