<?php

declare(strict_types=1);

namespace NullAuth\Http;

final class Router
{
    /** @var array<string, callable> */
    private array $routes = [];

    public function __construct(private readonly Request $request)
    {
    }

    /** @param callable(): Response $handler */
    public function get(string $path, callable $handler): void
    {
        $this->routes['GET ' . $path] = $handler;
    }

    /** @param callable(): Response $handler */
    public function post(string $path, callable $handler): void
    {
        $this->routes['POST ' . $path] = $handler;
    }

    public function dispatch(): Response
    {
        $key = $this->request->method . ' ' . $this->request->path;
        $handler = $this->routes[$key] ?? null;
        if ($handler === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        try {
            return $handler();
        } catch (\Throwable $exception) {
            error_log('Unhandled NullAuth exception: ' . $exception::class);
            return Response::html('<h1>500 Internal Server Error</h1>', 500);
        }
    }
}

