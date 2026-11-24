<?php

namespace Modules\ZabbixJumpserver\Lib;

/**
 * JumpServer REST API client
 *
 * Uses HTTP Signature authentication with the JumpServer AccessKey (consistent with drf-httpsig):
 * - Signed headers: (request-target) accept date
 * - Algorithm: hmac-sha256, signature base64-encoded
 * - Authorization: Signature keyId="...",algorithm="hmac-sha256",headers="(request-target) accept date",signature="..."
 *
 * Reference: https://docs.jumpserver.org/zh/v4/dev/rest_api/
 */
class JumpserverClient {

    private $baseUrl;
    private $keyId;
    private $secret;
    private $orgId;
    private $verifySsl;

    public function __construct(array $config) {
        $this->baseUrl = rtrim((string) ($config['jumpserver_url'] ?? ''), '/');
        $this->keyId = (string) ($config['access_key_id'] ?? '');
        $this->secret = (string) ($config['access_key_secret'] ?? '');
        $this->orgId = (string) ($config['org_id'] ?? '00000000-0000-0000-0000-000000000002');
        $this->verifySsl = (bool) ($config['verify_ssl'] ?? false);
    }

    public function isConfigured(): bool {
        return $this->baseUrl !== '' && $this->keyId !== '' && $this->secret !== '';
    }

    /**
     * Perform one API request
     *
     * @param string     $method GET/POST/PATCH/DELETE
     * @param string     $path   API path starting with / (may include a query string)
     * @param array|null $body   request body (encoded as JSON)
     * @return array ['ok' => bool, 'status' => int, 'data' => mixed, 'error' => string]
     */
    public function request(string $method, string $path, ?array $body = null): array {
        $method = strtoupper($method);
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $accept = 'application/json';

        // Build the signing string: request-target must exactly match the method + path of the actual request
        $requestTarget = strtolower($method) . ' ' . $path;
        $signingString = '(request-target): ' . $requestTarget . "\n"
            . 'accept: ' . $accept . "\n"
            . 'date: ' . $date;
        $signature = base64_encode(hash_hmac('sha256', $signingString, $this->secret, true));

        $authorization = sprintf(
            'Signature keyId="%s",algorithm="hmac-sha256",headers="(request-target) accept date",signature="%s"',
            $this->keyId,
            $signature
        );

        $headers = [
            'Accept: ' . $accept,
            'Date: ' . $date,
            'X-JMS-ORG: ' . $this->orgId,
            'Authorization: ' . $authorization,
        ];

        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        }

        $url = $this->baseUrl . $path;

        if (function_exists('curl_init')) {
            return $this->curlRequest($method, $url, $headers, $payload);
        }

        return $this->streamRequest($method, $url, $headers, $payload);
    }

    private function curlRequest(string $method, string $url, array $headers, ?string $payload): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => $error];
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $this->buildResult($status, $response);
    }

    private function streamRequest(string $method, string $url, array $headers, ?string $payload): array {
        $opts = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'timeout' => 30,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => $this->verifySsl,
                'verify_peer_name' => $this->verifySsl,
            ],
        ];
        if ($payload !== null) {
            $opts['http']['content'] = $payload;
        }

        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);

        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        if ($response === false) {
            return ['ok' => false, 'status' => $status, 'data' => null, 'error' => 'Request failed'];
        }

        return $this->buildResult($status, $response);
    }

    private function buildResult(int $status, string $response): array {
        $data = json_decode($response, true);
        $ok = ($status >= 200 && $status < 300);

        $error = '';
        if (!$ok) {
            $error = is_string($response) ? $response : '';
            if (is_array($data)) {
                $error = json_encode($data, JSON_UNESCAPED_UNICODE);
            }
        }

        return [
            'ok' => $ok,
            'status' => $status,
            'data' => $data,
            'error' => $error,
        ];
    }

    /**
     * Get all results of a paginated/non-paginated list (JumpServer may return an array or {count,results})
     */
    public function getAll(string $basePath): array {
        $sep = (strpos($basePath, '?') !== false) ? '&' : '?';
        $result = $this->request('GET', $basePath . $sep . 'limit=1000');
        if (!$result['ok']) {
            return [];
        }

        $data = $result['data'];
        if (is_array($data) && isset($data['results']) && is_array($data['results'])) {
            return $data['results'];
        }
        if (is_array($data)) {
            return $data;
        }

        return [];
    }

    // ── Nodes (asset groups) ──

    public function getNodes(): array {
        return $this->getAll('/api/v1/assets/nodes/');
    }

    public function createNode(string $name): ?array {
        $result = $this->request('POST', '/api/v1/assets/nodes/', ['value' => $name]);
        if ($result['ok'] && is_array($result['data'])) {
            return $result['data'];
        }
        return null;
    }

    // ── Platforms ──

    public function getPlatforms(): array {
        return $this->getAll('/api/v1/assets/platforms/');
    }

    // ── Host assets ──

    public function getHosts(): array {
        return $this->getAll('/api/v1/assets/hosts/');
    }

    public function createHost(array $data): array {
        return $this->request('POST', '/api/v1/assets/hosts/', $data);
    }

    public function updateHost(string $assetId, array $data): array {
        return $this->request('PATCH', '/api/v1/assets/hosts/' . $assetId . '/', $data);
    }

    // ── Accounts ──

    /**
     * Get the account template list (for validation / display)
     */
    public function getAccountTemplates(): array {
        return $this->getAll('/api/v1/accounts/account-templates/');
    }

    /**
     * Link accounts to an asset through an account template
     *
     * Uses the bulk endpoint /api/v1/accounts/account-bulk/; the template fills in
     * username / secret / secret type automatically; on_invalid=skip keeps repeated pushes idempotent
     * (the account unique key is asset_id + username + secret_type).
     *
     * @param bool $pushNow whether to push and create the account on the target host (false only records the credentials)
     */
    public function associateAccountByTemplate(string $assetId, string $templateId, bool $pushNow = false): array {
        return $this->request('POST', '/api/v1/accounts/account-bulk/', [
            'assets'     => [$assetId],
            'template'   => $templateId,
            'push_now'   => $pushNow,
            'on_invalid' => 'skip',
        ]);
    }
}
