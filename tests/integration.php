<?php
/* Loaded by run.php after it creates a separate database and server. */
if (PHP_SAPI !== 'cli' || !isset($environment, $runner)) { exit; }

function apiResult(array $response, int $status, bool $success): array
{
    TestRunner::same($status, $response['status']);
    TestRunner::truth(is_array($response['json']), 'Response must be JSON');
    TestRunner::same($success, $response['json']['success']);
    return $response['json']['data'] ?? [];
}

function dbValue(TestEnvironment $environment, string $sql, array $parameters = []): mixed
{
    $stmt = $environment->db->prepare($sql);
    $stmt->execute($parameters);
    return $stmt->fetchColumn();
}

/* Each test starts with the same sample records, so tests cannot affect each other. */
$integrationTest = function (string $name, callable $test) use ($environment, $runner): void {
    $runner->test($name, function () use ($test, $environment): void {
        $environment->reset();
        $test($environment);
    });
};

$integrationTest('Wrong password returns 401; login stores a session; logout clears it', function ($env): void {
    $client = new HttpClient($env->url);
    apiResult($client->request('/api/auth/login.php', ['user_type' => 'student', 'username' => '221345', 'password' => 'wrong']), 401, false);
    $client->login();
    $user = apiResult($client->request('/api/auth/me.php'), 200, true);
    TestRunner::same('221345', $user['profile']['matric_number']);
    TestRunner::same('student', $user['role']);
    apiResult($client->request('/api/auth/logout.php', []), 200, true);
    apiResult($client->request('/api/auth/me.php'), 401, false);
});

$integrationTest('Nested pages redirect to login and show flash message only once', function ($env): void {
    foreach (['', '/library-demo'] as $prefix) {
        $client = new HttpClient($env->url);
        $response = $client->request($prefix . '/loans/history.php');
        TestRunner::same(302, $response['status']);
        TestRunner::same('../login.php', $response['headers']['location']);
        $login = $client->request($prefix . '/login.php');
        TestRunner::truth(str_contains($login['text'], 'Please log in to access that page.'));
        TestRunner::truth(!str_contains($client->request($prefix . '/login.php')['text'], 'Please log in to access that page.'));
        TestRunner::same('../login.php', $client->request($prefix . '/payments/receipt.php')['headers']['location']);
    }
});

$integrationTest('Wrong role redirects on pages and returns 403 on student APIs', function ($env): void {
    $client = new HttpClient($env->url);
    $client->login('bakinyemi', 'administrator');
    TestRunner::same('../admin_dashboard.php', $client->request('/loans/history.php')['headers']['location']);
    apiResult($client->request('/api/loans/history.php'), 403, false);
    apiResult($client->request('/api/loans/borrow.php', ['book_id' => 2]), 403, false);
    $student = new HttpClient($env->url);
    $student->login();
    TestRunner::same('catalogue.php', $student->request('/admin_dashboard.php')['headers']['location']);
});

$integrationTest('Search reports titles and available copies', function ($env): void {
    $client = new HttpClient($env->url);
    apiResult($client->request('/api/books/search.php'), 401, false);
    $client->login();
    $books = apiResult($client->request('/api/books/search.php?q=Clean%20Code'), 200, true);
    TestRunner::same(1, count($books));
    TestRunner::same('Clean Code', $books[0]['title']);
    TestRunner::same(2, (int) $books[0]['available_copies']);
});

foreach ([null, 1, 14] as $days) {
    $integrationTest('Borrow with ' . ($days === null ? 'default due date' : "$days-day due date"), function ($env) use ($days): void {
        $client = new HttpClient($env->url);
        $client->login('214567');
        $body = ['book_id' => 2];
        $expected = dbValue($env, 'SELECT DATE_FORMAT(CURDATE() + INTERVAL ? DAY, "%Y-%m-%d")', [$days ?? 14]);
        if ($days !== null) $body['due_date'] = $expected;
        $loan = apiResult($client->request('/api/loans/borrow.php', $body), 201, true);
        TestRunner::same($expected, $loan['due_date']);
        TestRunner::same($expected, dbValue($env, 'SELECT due_date FROM loans WHERE loan_id = ?', [$loan['loan_id']]));
        TestRunner::same('borrowed', dbValue($env, 'SELECT status FROM book_copies WHERE copy_id = ?', [$loan['copy_id']]));
    });
}

foreach (['today' => 0, 'past' => -1, 'too far' => 15, 'impossible' => '2026-02-30', 'malformed' => 'tomorrow'] as $label => $date) {
    $integrationTest("Reject $label due date without creating a loan", function ($env) use ($date): void {
        $client = new HttpClient($env->url);
        $client->login('214567');
        $due = is_int($date) ? dbValue($env, 'SELECT DATE_FORMAT(CURDATE() + INTERVAL ? DAY, "%Y-%m-%d")', [$date]) : $date;
        $count = dbValue($env, 'SELECT COUNT(*) FROM loans');
        apiResult($client->request('/api/loans/borrow.php', ['book_id' => 2, 'due_date' => $due]), 400, false);
        TestRunner::same($count, dbValue($env, 'SELECT COUNT(*) FROM loans'));
        TestRunner::same(2, (int) dbValue($env, 'SELECT available_copies FROM v_book_availability WHERE book_id = 2'));
    });
}

$integrationTest('Fourth loan is rejected and leaves book availability unchanged', function ($env): void {
    $client = new HttpClient($env->url);
    $client->login();
    apiResult($client->request('/api/loans/borrow.php', ['book_id' => 2]), 201, true);
    apiResult($client->request('/api/loans/borrow.php', ['book_id' => 3]), 409, false);
    TestRunner::same(3, (int) dbValue($env, 'SELECT COUNT(*) FROM loans WHERE matric_number = ? AND return_date IS NULL', ['221345']));
    TestRunner::same(2, (int) dbValue($env, 'SELECT available_copies FROM v_book_availability WHERE book_id = 3'));
});

$integrationTest('Unpaid fines and unavailable books block borrowing', function ($env): void {
    $client = new HttpClient($env->url);
    $client->login('220981');
    apiResult($client->request('/api/loans/borrow.php', ['book_id' => 2]), 409, false);
    $other = new HttpClient($env->url);
    $other->login('214567');
    apiResult($other->request('/api/loans/borrow.php', ['book_id' => 6]), 409, false);
    TestRunner::same(8, (int) dbValue($env, 'SELECT COUNT(*) FROM loans'));
});

$integrationTest('Anonymous and student return attempts are rejected without changing loans, copies or fines', function ($env): void {
    $client = new HttpClient($env->url);
    $before = [];
    foreach (['loans', 'book_copies', 'fines'] as $table) {
        $before[$table] = $env->db->query('SELECT * FROM ' . $table)->fetchAll();
    }
    apiResult($client->request('/api/loans/return.php', ['loan_id' => 1]), 401, false);
    $client->login();
    foreach ([1, 3] as $id) {
        apiResult($client->request('/api/loans/return.php', ['loan_id' => $id, 'onlyForMatric' => null]), 403, false);
    }
    foreach ($before as $table => $rows) {
        TestRunner::same($rows, $env->db->query('SELECT * FROM ' . $table)->fetchAll());
    }
});

$integrationTest('Librarian on-time return releases the copy; repeated return is rejected', function ($env): void {
    $client = new HttpClient($env->url);
    $client->login('bakinyemi', 'administrator');
    $result = apiResult($client->request('/api/loans/return.php', ['loan_id' => 1]), 200, true);
    TestRunner::same(null, $result['fine']);
    TestRunner::same('returned', $result['loan']['status']);
    TestRunner::same(dbValue($env, 'SELECT CURDATE()'), $result['loan']['return_date']);
    TestRunner::same('available', dbValue($env, 'SELECT status FROM book_copies WHERE copy_id = 1'));
    TestRunner::same(0, (int) dbValue($env, 'SELECT COUNT(*) FROM fines WHERE loan_id = 1'));
    apiResult($client->request('/api/loans/return.php', ['loan_id' => 1]), 409, false);
});

$integrationTest('Librarian return rejects wrong method, invalid ID and missing loan', function ($env): void {
    $client = new HttpClient($env->url);
    $client->login('bakinyemi', 'administrator');
    apiResult($client->request('/api/loans/return.php'), 405, false);
    foreach ([[], ['loan_id' => -1], ['loan_id' => 'invalid']] as $body) {
        apiResult($client->request('/api/loans/return.php', $body), 400, false);
    }
    apiResult($client->request('/api/loans/return.php', ['loan_id' => 999999]), 404, false);
    TestRunner::same(5, (int) dbValue($env, 'SELECT COUNT(*) FROM loans WHERE return_date IS NULL'));
    TestRunner::same(2, (int) dbValue($env, 'SELECT COUNT(*) FROM fines'));
});

$integrationTest('Late returns create one fine; very late return reaches the cap', function ($env): void {
    $client = new HttpClient($env->url);
    $client->login('232118');
    $fines = apiResult($client->request('/api/fines/list.php'), 200, true);
    TestRunner::same(2, count($fines['accruing']));
    $admin = new HttpClient($env->url);
    $admin->login('bakinyemi', 'administrator');
    foreach ([3 => '600.00', 4 => '5000.00'] as $id => $amount) {
        $result = apiResult($admin->request('/api/loans/return.php', ['loan_id' => $id]), 200, true);
        TestRunner::same($amount, $result['fine']['amount']);
        TestRunner::same('available', dbValue($env, 'SELECT status FROM book_copies WHERE copy_id = ?', [$result['loan']['copy_id']]));
        apiResult($admin->request('/api/loans/return.php', ['loan_id' => $id]), 409, false);
        TestRunner::same(1, (int) dbValue($env, 'SELECT COUNT(*) FROM fines WHERE loan_id = ?', [$id]));
    }
    TestRunner::same(0, count(apiResult($client->request('/api/fines/list.php'), 200, true)['accruing']));
});

$integrationTest('Declined payment is recorded; successful retry pays the fine and links the receipt', function ($env): void {
    $client = new HttpClient($env->url);
    $client->login('220981');
    $failed = apiResult($client->request('/api/payments/pay.php', ['fine_id' => 1, 'channel' => 'card', 'card_number' => '4000000000000000']), 402, false);
    TestRunner::same('failed', $failed['status']);
    TestRunner::same('unpaid', dbValue($env, 'SELECT status FROM fines WHERE fine_id = 1'));
    TestRunner::same(2, (int) dbValue($env, 'SELECT COUNT(*) FROM payments WHERE fine_id = 1'));
    $paid = apiResult($client->request('/api/payments/pay.php', ['fine_id' => 1, 'channel' => 'card', 'card_number' => '4000 1234 5678 1111']), 201, true);
    TestRunner::same('successful', $paid['status']);
    TestRunner::same('paid', dbValue($env, 'SELECT status FROM fines WHERE fine_id = 1'));
    $saved = apiResult($client->request('/api/fines/list.php'), 200, true);
    TestRunner::same($paid['reference'], $saved['fines'][0]['payment_reference']);
    $receipt = apiResult($client->request('/api/payments/receipt.php?reference=' . urlencode($paid['reference'])), 200, true);
    TestRunner::same('card', $receipt['payment_channel']);
    TestRunner::truth(isset($receipt['time_paid'], $receipt['student_name'], $receipt['book_title']));
    apiResult($client->request('/api/payments/pay.php', ['fine_id' => 1, 'channel' => 'ussd']), 409, false);
    $records = $env->db->query('SELECT * FROM payments')->fetchAll();
    TestRunner::truth(!str_contains(json_encode($records), '4000000000000000'), 'Card digits must not be stored');
    TestRunner::truth(!str_contains(json_encode($records), '4000123456781111'), 'Card digits must not be stored');
});

foreach (['bank_transfer', 'ussd'] as $channel) {
    $integrationTest("$channel payment succeeds", function ($env) use ($channel): void {
        $client = new HttpClient($env->url);
        $client->login('220981');
        TestRunner::same('successful', apiResult($client->request('/api/payments/pay.php', ['fine_id' => 1, 'channel' => $channel]), 201, true)['status']);
    });
}

$integrationTest('Invalid payment inputs and another student fine do not create attempts', function ($env): void {
    $client = new HttpClient($env->url);
    $client->login('220981');
    foreach ([['fine_id' => 1, 'channel' => 'cash'], ['fine_id' => 1, 'channel' => 'card', 'card_number' => '123'], ['fine_id' => -1, 'channel' => 'ussd']] as $body) {
        apiResult($client->request('/api/payments/pay.php', $body), 400, false);
    }
    apiResult($client->request('/api/payments/pay.php', ['fine_id' => 2, 'channel' => 'ussd']), 404, false);
    TestRunner::same(2, (int) dbValue($env, 'SELECT COUNT(*) FROM payments'));
});

$integrationTest('Receipts are private to their student; librarians may view them', function ($env): void {
    $reference = dbValue($env, 'SELECT reference FROM payments WHERE payment_id = 1');
    $path = '/api/payments/receipt.php?reference=' . urlencode($reference);
    $client = new HttpClient($env->url);
    apiResult($client->request($path), 401, false);
    $client->login();
    apiResult($client->request($path), 404, false);
    apiResult($client->request('/api/payments/receipt.php'), 400, false);
    apiResult($client->request('/api/payments/receipt.php?reference=missing'), 404, false);
    $owner = new HttpClient($env->url);
    $owner->login('205643');
    apiResult($owner->request($path), 200, true);
    $admin = new HttpClient($env->url);
    $admin->login('bakinyemi', 'administrator');
    apiResult($admin->request($path), 200, true);
});

$integrationTest('History shows only own active and returned loans with saved fine status', function ($env): void {
    $client = new HttpClient($env->url);
    $client->login();
    $history = apiResult($client->request('/api/loans/history.php'), 200, true);
    TestRunner::same(2, count($history));
    foreach ($history as $loan) TestRunner::same('221345', $loan['matric_number']);
    $admin = new HttpClient($env->url);
    $admin->login('bakinyemi', 'administrator');
    apiResult($admin->request('/api/loans/return.php', ['loan_id' => 1]), 200, true);
    $history = apiResult($client->request('/api/loans/history.php'), 200, true);
    TestRunner::truth(in_array('returned', array_column($history, 'status'), true));
    $paidStudent = new HttpClient($env->url);
    $paidStudent->login('205643');
    $history = apiResult($paidStudent->request('/api/loans/history.php'), 200, true);
    TestRunner::same('500.00', $history[0]['fine_amount']);
    TestRunner::same('paid', $history[0]['fine_status']);
});

$integrationTest('Nested assets load; private support and views are blocked by the test router', function ($env): void {
    $client = new HttpClient($env->url);
    foreach (['/assets/js/loans_history.js', '/library-demo/assets/css/style.css'] as $path) TestRunner::same(200, $client->request($path)['status']);
    foreach (['/tests/run.php', '/views/loans/history.html', '/config/config.php'] as $path) TestRunner::same(403, $client->request($path)['status']);
});
