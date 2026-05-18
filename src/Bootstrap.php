<?php

declare(strict_types=1);

namespace NullAuth;

use NullAuth\Controller\AdminController;
use NullAuth\Controller\AuthController;
use NullAuth\Controller\DashboardController;
use NullAuth\Controller\GeneratorController;
use NullAuth\Controller\HomeController;
use NullAuth\Controller\VaultController;
use NullAuth\Http\Request;
use NullAuth\Http\Response;
use NullAuth\Http\Router;
use NullAuth\Http\SecurityHeaders;
use NullAuth\Repository\AuditRepository;
use NullAuth\Repository\Database;
use NullAuth\Repository\UserRepository;
use NullAuth\Repository\VaultRepository;
use NullAuth\Service\AuditService;
use NullAuth\Service\AuthService;
use NullAuth\Service\CryptoService;
use NullAuth\Service\CsrfService;
use NullAuth\Service\PasswordGeneratorService;
use NullAuth\Service\SessionService;
use NullAuth\Service\TotpService;
use NullAuth\Service\VaultService;
use NullAuth\Support\Config;
use NullAuth\Support\Env;

final class Bootstrap
{
    public static function run(): void
    {
        self::loadRuntime();

        $request = Request::capture();
        $headers = new SecurityHeaders();
        $csrf = new CsrfService();
        $db = new Database((string) config('database.dsn'), (string) config('database.user'), (string) config('database.password'));
        $audit = new AuditService(new AuditRepository($db), $request);
        $session = new SessionService($db, $audit);
        $crypto = new CryptoService((string) config('app.key_base64'));
        $totp = new TotpService($crypto);
        $users = new UserRepository($db);
        $auth = new AuthService($users, $session, $audit, $totp, $crypto);
        $vault = new VaultService(new VaultRepository($db), $crypto, $audit);
        $generator = new PasswordGeneratorService();

        $router = new Router($request);
        $home = new HomeController($headers);
        $authController = new AuthController($auth, $csrf, $session, $headers, $request);
        $dashboard = new DashboardController($session, $csrf);
        $vaultController = new VaultController($session, $csrf, $vault, $request);
        $admin = new AdminController($session, $csrf, $audit, $users);
        $generatorController = new GeneratorController($session, $csrf, $generator, $request);

        $router->get('/', [$home, 'errorLanding']);
        $router->get('/unlock', [$authController, 'unlock']);
        $router->post('/login', [$authController, 'login']);
        $router->post('/logout', [$authController, 'logout']);
        $router->get('/dashboard', [$dashboard, 'index']);
        $router->get('/vault', [$vaultController, 'index']);
        $router->post('/vault', [$vaultController, 'store']);
        $router->post('/vault/reveal', [$vaultController, 'reveal']);
        $router->get('/generator', [$generatorController, 'index']);
        $router->post('/generator', [$generatorController, 'generate']);
        $router->get('/admin/users', [$admin, 'users']);
        $router->get('/admin/audit', [$admin, 'audit']);

        $response = $router->dispatch();
        $headers->apply($response, $request);
        $response->send();
    }

    private static function loadRuntime(): void
    {
        if (!function_exists('base_path')) {
            require dirname(__DIR__) . '/src/Support/helpers.php';
        }

        Env::load(base_path('.env'));
        Config::load(require base_path('config/app.php'));

        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', self::isHttps() ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('expose_php', '0');
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}

