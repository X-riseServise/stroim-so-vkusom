<?php

declare(strict_types=1);

function adminIsHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
}

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('stroim_admin_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin',
        'domain' => '',
        'secure' => adminIsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function destroyAdminSession(): void
{
    startAdminSession();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
