<?php
/*
 * An error we EXPECT and want to show to the user, for example
 * "You already have 3 books" or "Incorrect password".
 *
 * The second argument is the HTTP status code the API should send back:
 *   400 bad input, 401 not logged in, 403 not allowed,
 *   404 not found, 409 a library rule blocks the action.
 *
 * Unexpected errors (e.g. the database is down) are NOT AppExceptions;
 * the API turns those into a generic 500 "server error".
 */
class AppException extends Exception
{
    public function __construct(string $message, int $httpStatus = 400)
    {
        parent::__construct($message, $httpStatus);
    }

    public function getHttpStatus(): int
    {
        return $this->getCode();
    }
}
