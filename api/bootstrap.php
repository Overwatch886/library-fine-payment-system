<?php
/*
 * Loaded at the top of EVERY api/ endpoint.
 *
 *  - loads config + classes
 *  - makes every response JSON in the same shape:
 *        {"success": true/false, "message": "...", "data": ...}
 *  - turns errors into JSON with the right HTTP status code, so the
 *    endpoint files can stay short and simply "throw" when something is wrong
 */

require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json; charset=utf-8');

// Never print PHP warnings into the response: they would break the JSON.
ini_set('display_errors', '0');

// Turn PHP warnings/notices into exceptions so they are handled below.
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Any exception that an endpoint does not catch ends up here.
set_exception_handler(function (Throwable $e): void {
    if ($e instanceof AppException) {
        // Expected problem: show the message to the user.
        respond(false, $e->getMessage(), null, $e->getHttpStatus());
    }

    // Unexpected problem: log the details for us, show a generic message to the user.
    error_log('[library api] ' . $e);
    respond(false, 'Something went wrong on the server. Please try again.', null, 500);
});

Auth::startSession();

/** Send the JSON response and stop. */
function respond(bool $success, string $message, mixed $data = null, int $httpStatus = 200): never
{
    http_response_code($httpStatus);
    echo json_encode(
        ['success' => $success, 'message' => $message, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

/** Stop with 405 unless the request uses the expected method ('GET' or 'POST'). */
function requireMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        respond(false, 'This endpoint only accepts ' . $method . ' requests.', null, 405);
    }
}

/**
 * The POST body as an array. Works with both
 *   fetch(url, {body: JSON.stringify({...}), headers: {'Content-Type': 'application/json'}})
 * and normal form posts / FormData.
 */
function getInput(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (str_contains($contentType, 'application/json')) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            throw new AppException('The request body is not valid JSON.', 400);
        }
        return $data;
    }

    return $_POST;
}

/** A required positive whole number from $source (e.g. book_id). 400 if missing/invalid. */
function requireInt(array $source, string $key): int
{
    $value = filter_var($source[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($value === false) {
        throw new AppException($key . ' is required and must be a positive whole number.', 400);
    }
    return $value;
}

/** A required non-empty text value from $source. 400 if missing. */
function requireString(array $source, string $key): string
{
    $value = optionalString($source, $key);
    if ($value === '') {
        throw new AppException($key . ' is required.', 400);
    }
    return $value;
}

/** An optional text value from $source ('' if missing or not text). */
function optionalString(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_scalar($value) ? trim((string) $value) : '';
}
