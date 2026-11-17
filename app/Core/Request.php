<?php

namespace App\Core;

class Request
{
    /**
     * Request URI path
     */
    protected string $path;

    /**
     * HTTP Method
     */
    protected string $method;

    /**
     * Parsed query parameters
     */
    protected array $queryParams;

    /**
     * Parsed body parameters (POST / JSON)
     */
    protected array $bodyParams;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Handle method override (_method parameter or X-HTTP-Method-Override header)
        if ($this->method === 'POST') {
            if (isset($_POST['_method'])) {
                $this->method = strtoupper($_POST['_method']);
            } elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                $this->method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            }
        }

        $this->path = $this->extractPath();
        $this->queryParams = $_GET;
        $this->bodyParams = $this->extractBody();
    }

    /**
     * Extract normalized URI path relative to script/base.
     */
    protected function extractPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Strip query string
        if (false !== ($pos = strpos($uri, '?'))) {
            $uri = substr($uri, 0, $pos);
        }

        $uri = rawurldecode($uri);

        // Normalize when running inside subfolder (e.g. /Project RPL/Sistem Kas/public/...)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = rtrim(dirname($scriptName), '/\\');

        if (!empty($baseDir) && str_starts_with($uri, $baseDir)) {
            $uri = substr($uri, strlen($baseDir));
        }

        $uri = '/' . trim($uri, '/');
        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    /**
     * Extract request body (supports application/json and form-urlencoded).
     */
    protected function extractBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            return is_array($json) ? $json : [];
        }

        return $_POST;
    }

    /**
     * Get request method.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get normalized path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Check if request is AJAX.
     */
    public function isAjax(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    /**
     * Get specific input value from body or query.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->bodyParams[$key] ?? ($this->queryParams[$key] ?? $default);
    }

    /**
     * Get all input values (body + query).
     */
    public function all(): array
    {
        return array_merge($this->queryParams, $this->bodyParams);
    }

    /**
     * Get body params only.
     */
    public function getBody(): array
    {
        return $this->bodyParams;
    }

    /**
     * Get query params only.
     */
    public function getQuery(): array
    {
        return $this->queryParams;
    }

    /**
     * Get sanitized string input.
     */
    public function clean(string $key, string $default = ''): string
    {
        $val = $this->input($key, $default);
        if (!is_string($val)) {
            return $default;
        }
        return trim($val);
    }

    /**
     * Flash current input for repopulating forms.
     */
    public function flashInput(): void
    {
        $data = $this->all();
        // Remove sensitive fields
        unset($data['password'], $data['password_confirmation'], $data['_csrf_token']);
        Session::setFlash('_old_input', $data);
    }
}
