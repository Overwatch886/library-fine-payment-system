<?php
/*
 * Logging in and out, and checking who is logged in.
 *
 * After a successful login we store a small array in the PHP session:
 *     $_SESSION['user'] = ['role' => 'student', 'id' => '221345', 'name' => '...']
 * role is 'student' or 'librarian'. For students id is the matric number,
 * for librarians it is librarian_id.
 */
class Auth
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        self::startSession();
    }

    /** Start the PHP session once per request, with a safer cookie. */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'path'     => '/',
                'httponly' => true,   // JavaScript cannot read the session cookie
                'samesite' => 'Lax',  // other websites cannot send it with their POSTs
            ]);
            session_start();
        }
    }

    /**
     * Check the username + password and log the user in.
     *
     * $userType comes from the login form: 'student' or 'administrator'
     * ('librarian' is accepted too). Students may type their matric number OR username.
     */
    public function login(string $userType, string $username, string $password): array
    {
        if ($userType === 'student') {
            $role = 'student';
            $stmt = $this->db->prepare(
                'SELECT matric_number AS id, full_name, password_hash
                   FROM students
                  WHERE matric_number = ? OR username = ?'
            );
            $stmt->execute([$username, $username]);
        } elseif ($userType === 'administrator' || $userType === 'librarian') {
            $role = 'librarian';
            $stmt = $this->db->prepare(
                'SELECT librarian_id AS id, full_name, password_hash
                   FROM librarians
                  WHERE username = ?'
            );
            $stmt->execute([$username]);
        } else {
            throw new AppException('user_type must be "student" or "administrator".', 400);
        }

        $user = $stmt->fetch();

        // Same message for "no such user" and "wrong password", so attackers
        // cannot find out which usernames exist.
        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new AppException('Incorrect username or password.', 401);
        }

        // New session id after login (prevents "session fixation" attacks).
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'role' => $role,
            'id'   => $user['id'],
            'name' => $user['full_name'],
        ];

        return $_SESSION['user'];
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    /** The logged-in user's session array, or null if nobody is logged in. */
    public function currentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /** Stop with 401 unless someone is logged in. Returns the session user. */
    public function requireLogin(): array
    {
        $user = $this->currentUser();
        if ($user === null) {
            throw new AppException('Please log in first.', 401);
        }
        return $user;
    }

    /** Only students may continue. Returns their matric number. */
    public function requireStudent(): string
    {
        $user = $this->requireLogin();
        if ($user['role'] !== 'student') {
            throw new AppException('Only students can do this.', 403);
        }
        return $user['id'];
    }

    /** Only librarians may continue. Returns their librarian_id. */
    public function requireLibrarian(): int
    {
        $user = $this->requireLogin();
        if ($user['role'] !== 'librarian') {
            throw new AppException('Only librarians can do this.', 403);
        }
        return (int) $user['id'];
    }

    /* ------------------------------------------------------------------ */
    /*  Web-page variants: redirect to login.php instead of throwing.     */
    /*  Use these in entry-point files (catalogue.php, my_loans.php, etc.)*/
    /* ------------------------------------------------------------------ */

    /**
     * For web pages: redirect to login.php with a flash message
     * if the user is not logged in. Returns the session user on success.
     */
    public function requireLoginForWeb(string $basePath = ''): array
    {
        $user = $this->currentUser();
        if ($user === null) {
            $_SESSION['flash'] = 'Please log in to access that page.';
            header('Location: ' . $basePath . 'login.php');
            exit;
        }
        return $user;
    }

    /**
     * For web pages: redirect to login.php if not logged in,
     * or redirect to the admin dashboard if the role is wrong.
     * Returns the student's matric number.
     */
    public function requireStudentForWeb(string $basePath = ''): string
    {
        $user = $this->requireLoginForWeb($basePath);
        if ($user['role'] !== 'student') {
            header('Location: ' . $basePath . 'admin_dashboard.php');
            exit;
        }
        return $user['id'];
    }

    /**
     * For web pages: redirect to login.php if not logged in,
     * or redirect to the catalogue if the role is wrong.
     * Returns the librarian_id.
     */
    public function requireLibrarianForWeb(string $basePath = ''): int
    {
        $user = $this->requireLoginForWeb($basePath);
        if ($user['role'] !== 'librarian') {
            header('Location: ' . $basePath . 'catalogue.php');
            exit;
        }
        return (int) $user['id'];
    }
}
