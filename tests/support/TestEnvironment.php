<?php
/* Each run owns one new database, one PHP process, and one temporary directory. */
class TestEnvironment
{
    private PDO $admin;
    private string $name;
    private string $token;
    private string $directory;
    private $server = null;
    private array $pipes = [];
    private bool $created = false;
    public string $url;
    public PDO $db;

    public function __construct()
    {
        $this->token = bin2hex(random_bytes(8));
        $this->name = 'ui_library_test_' . $this->token;
        $this->directory = sys_get_temp_dir() . '/library-tests-' . $this->token;
        $this->admin = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        try {
            mkdir($this->directory);
            mkdir($this->directory . '/sessions');
            $this->admin->exec('CREATE DATABASE `' . $this->name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->created = true;
            $this->db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . $this->name . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->db->exec('CREATE TABLE test_run_owner (token VARCHAR(32) PRIMARY KEY)');
            $stmt = $this->db->prepare('INSERT INTO test_run_owner VALUES (?)');
            $stmt->execute([$this->token]);
            $this->reset();
            $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
            if (!$socket) throw new RuntimeException('Cannot choose a test port: ' . $error);
            $address = stream_socket_get_name($socket, false);
            fclose($socket);
            $this->url = 'http://' . $address;
            $env = getenv();
            $env['LIBRARY_DB_HOST'] = DB_HOST;
            $env['LIBRARY_DB_NAME'] = $this->name;
            $env['LIBRARY_DB_USER'] = DB_USER;
            $env['LIBRARY_DB_PASS'] = DB_PASS;
            $env['LIBRARY_TEST_SESSION_PATH'] = $this->directory . '/sessions';
            $root = dirname(__DIR__, 2);
            $this->server = proc_open([
                PHP_BINARY, '-S', $address, '-t', $root, __DIR__ . '/router.php',
            ], [0 => ['pipe', 'r'], 1 => ['file', $this->directory . '/server.log', 'a'],
                2 => ['file', $this->directory . '/server.log', 'a']], $this->pipes, $root, $env);
            if (!is_resource($this->server)) throw new RuntimeException('Cannot start the test PHP server.');
            fclose($this->pipes[0]);
            $this->pipes = [];
            $deadline = microtime(true) + 10;
            do {
                $curl = curl_init($this->url . '/__test_health');
                curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 1]);
                $body = curl_exec($curl);
                curl_close($curl);
                if ($body === 'ready') return;
                usleep(100000);
            } while (microtime(true) < $deadline && proc_get_status($this->server)['running']);
            throw new RuntimeException('Test server did not become ready.');
        } catch (Throwable $error) {
            try {
                $this->close();
            } catch (Throwable $cleanupError) {
                fwrite(STDERR, 'Cleanup refused: ' . $cleanupError->getMessage() . "\n");
            }
            throw $error;
        }
    }

    private function verifyOwner(): void
    {
        if (!$this->created) {
            throw new RuntimeException('Refusing to reset/delete a database not created by this run.');
        }
        self::verifyDatabase($this->db, $this->name, $this->token);
    }

    private static function verifyDatabase(PDO $db, string $name, string $token): void
    {
        if (!preg_match('/^ui_library_test_[a-f0-9]{16}$/D', $name)
            || !preg_match('/^[a-f0-9]{16}$/D', $token)
            || $db->query('SELECT DATABASE()')->fetchColumn() !== $name
            || $db->query('SELECT token FROM test_run_owner')->fetchColumn() !== $token) {
            throw new RuntimeException('Test database ownership check failed.');
        }
    }

    public function reset(): void
    {
        $this->verifyOwner();
        self::resetOwned($this->db, $this->name, $this->token);
    }

    public static function resetOwned(PDO $db, string $name, string $token): void
    {
        self::verifyDatabase($db, $name, $token);
        /* Never execute DROP DATABASE / CREATE DATABASE / USE from the application SQL. */
        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');
        $prefix = '-- -------------------------------------------------------------' . "\n" . '-- People';
        $offset = strpos(str_replace("\r\n", "\n", $schema), $prefix);
        if ($offset === false) throw new RuntimeException('Unexpected schema structure.');
        $schema = substr(str_replace("\r\n", "\n", $schema), $offset);
        $seed = file_get_contents(dirname(__DIR__, 2) . '/database/seed.sql');
        $seed = preg_replace('/^USE ui_library;\s*$/m', '', $seed, 1, $count);
        if ($count !== 1 || preg_match('/\b(?:USE|DROP\s+DATABASE|CREATE\s+DATABASE)\s+/i', $schema . $seed)) {
            throw new RuntimeException('Unsafe or unexpected database directives in test SQL.');
        }
        $db->exec('DROP VIEW IF EXISTS v_loan_details, v_book_availability');
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (['payments', 'fines', 'loans', 'book_copies', 'books', 'students', 'librarians'] as $table) {
                $db->exec('DROP TABLE IF EXISTS `' . $table . '`');
            }
        } finally {
            $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        $db->exec($schema);
        $db->exec($seed);
    }

    public function browserEnvironment(): array
    {
        return [
            'LIBRARY_TEST_URL' => $this->url,
            'LIBRARY_DB_HOST' => DB_HOST,
            'LIBRARY_DB_NAME' => $this->name,
            'LIBRARY_DB_USER' => DB_USER,
            'LIBRARY_DB_PASS' => DB_PASS,
            'LIBRARY_TEST_TOKEN' => $this->token,
            'LIBRARY_TEST_PHP' => PHP_BINARY,
        ];
    }

    public function serverLog(): string
    {
        return file_get_contents($this->directory . '/server.log');
    }

    public function close(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
            $this->server = null;
        }
        try {
            if ($this->created) {
                if (!isset($this->db)) {
                    throw new RuntimeException('Cannot verify ownership; left ' . $this->name . ' untouched.');
                }
                $this->verifyOwner();
                $this->admin->exec('DROP DATABASE `' . $this->name . '`');
                $this->created = false;
            }
        } finally {
            if (is_dir($this->directory)) {
                foreach (glob($this->directory . '/sessions/*') ?: [] as $file) unlink($file);
                if (is_dir($this->directory . '/sessions')) rmdir($this->directory . '/sessions');
                if (is_file($this->directory . '/server.log')) unlink($this->directory . '/server.log');
                rmdir($this->directory);
            }
        }
    }
}
