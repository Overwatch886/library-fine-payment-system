<?php
/* Used only by our loopback test server; emulate an optional XAMPP project subfolder. */
$sessionPath = getenv('LIBRARY_TEST_SESSION_PATH');
if ($sessionPath === false || !is_dir($sessionPath)) {
    http_response_code(500); echo 'Test session directory missing'; return true;
}
session_save_path($sessionPath);
$root = realpath(dirname(__DIR__, 2));
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($path === '/__test_health') { echo 'ready'; return true; }
if (str_starts_with($path, '/library-demo/')) $path = substr($path, strlen('/library-demo'));
$file = realpath($root . $path);
if (!$file || !str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $root) . '/')) {
    http_response_code(404); echo 'Not found'; return true;
}
$relative = substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', $root)) + 1);
if (preg_match('#^(?:tests|views|config|classes|database|docs|\.git|\.claude)(?:/|$)#', $relative)) {
    http_response_code(403); echo 'Forbidden'; return true;
}
if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['SCRIPT_NAME'] = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    require $file;
    return true;
}
if (str_starts_with($relative, 'assets/')) {
    $types = ['css' => 'text/css', 'js' => 'text/javascript', 'svg' => 'image/svg+xml', 'png' => 'image/png'];
    header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    readfile($file);
    return true;
}
http_response_code(404);
echo 'Not found';
return true;
