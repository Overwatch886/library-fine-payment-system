<?php
/*
 * Gives every class the SAME PDO connection to MySQL.
 *
 * The connection is created the first time getConnection() is called and
 * then reused (this is called the "singleton" pattern). One connection per
 * request also means transactions started in one class are seen by the others.
 */
class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

            self::$connection = new PDO($dsn, DB_USER, DB_PASS, [
                // Throw an exception on any SQL error instead of failing silently.
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // fetch() returns ['column' => value] arrays.
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Use real prepared statements (also returns numbers as numbers).
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$connection;
    }
}
