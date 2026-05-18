<?php

declare(strict_types=1);

namespace NullAuth\Http;

final readonly class Request
{
    /** @param array<string, mixed> $post @param array<string, mixed> $query @param array<string, mixed> $server */
    public function __construct(
        public string $method,
        public string $path,
        public array $post,
        public array $query,
        public array $server,
    ) {
    }

    public static function capture(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path === false ? '/' : $path,
            $_POST,
            $_GET,
            $_SERVER,
        );
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->post[$key] ?? $this->query[$key] ?? $default;
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function ip(): string
    {
        $forwarded = (string) ($this->server['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 512);
    }

    public function expectsJson(): bool
    {
        return str_contains((string) ($this->server['HTTP_ACCEPT'] ?? ''), 'application/json')
            || str_contains((string) ($this->server['CONTENT_TYPE'] ?? ''), 'application/json');
    }
}

