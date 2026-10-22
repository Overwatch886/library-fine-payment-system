<?php
/* A cookie jar keeps the PHP session between requests, just like a browser. */
class HttpClient
{
    private CurlHandle $curl;
    public function __construct(private string $url)
    {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '',
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 10]);
    }

    public function request(string $path, ?array $body = null, bool $form = false): array
    {
        $headers = [];
        curl_setopt_array($this->curl, [CURLOPT_URL => $this->url . $path,
            CURLOPT_CUSTOMREQUEST => $body === null ? 'GET' : 'POST',
            CURLOPT_POSTFIELDS => $body === null ? null : ($form ? http_build_query($body) : json_encode($body)),
            CURLOPT_HTTPHEADER => $body === null ? [] : ['Content-Type: ' . ($form ? 'application/x-www-form-urlencoded' : 'application/json')],
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$headers): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            }]);
        $text = curl_exec($this->curl);
        if ($text === false) throw new RuntimeException('HTTP request failed: ' . curl_error($this->curl));
        return ['status' => curl_getinfo($this->curl, CURLINFO_HTTP_CODE), 'headers' => $headers,
            'text' => $text, 'json' => json_decode($text, true)];
    }

    public function login(string $username = '221345', string $role = 'student'): void
    {
        $response = $this->request('/api/auth/login.php', ['user_type' => $role, 'username' => $username, 'password' => 'Password123']);
        TestRunner::same(200, $response['status']);
        TestRunner::truth($response['json']['success']);
    }
}
