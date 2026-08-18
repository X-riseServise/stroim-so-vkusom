<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';

const ADMIN_MAX_LOGIN_ATTEMPTS = 5;
const ADMIN_LOCK_SECONDS = 300;

function adminConfigPath(): string
{
    return __DIR__ . '/config.php';
}

function loadAdminConfig(): array
{
    $path = adminConfigPath();

    if (!is_file($path)) {
        return [];
    }

    $config = require $path;
    return is_array($config) ? $config : [];
}

function adminConfigReady(): bool
{
    $config = loadAdminConfig();
    $hash = (string) ($config['admin_password_hash'] ?? '');

    return !empty($config['admin_username'])
        && strlen($hash) > 20
        && !str_contains($hash, 'replaceThisWithYourGeneratedPasswordHash');
}

function isAdminAuthenticated(): bool
{
    startAdminSession();
    return !empty($_SESSION['admin_authenticated']);
}

function requireAdmin(): void
{
    startAdminSession();

    if (!isAdminAuthenticated()) {
        header('Location: /admin/login.php', true, 302);
        exit;
    }
}

function redirectIfAdmin(): void
{
    if (isAdminAuthenticated()) {
        header('Location: /admin/dashboard.php', true, 302);
        exit;
    }
}

function generateCsrfToken(): string
{
    startAdminSession();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function validateCsrfToken(?string $token): bool
{
    startAdminSession();

    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals((string) $_SESSION['csrf_token'], $token);
}

function loginIsLocked(): bool
{
    startAdminSession();
    $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);

    if ($lockedUntil <= time()) {
        unset($_SESSION['login_locked_until']);
        return false;
    }

    return true;
}

function loginLockRemainingSeconds(): int
{
    startAdminSession();
    return max(0, (int) ($_SESSION['login_locked_until'] ?? 0) - time());
}

function registerFailedLogin(): void
{
    startAdminSession();

    $_SESSION['login_failed_attempts'] = (int) ($_SESSION['login_failed_attempts'] ?? 0) + 1;

    if ($_SESSION['login_failed_attempts'] >= ADMIN_MAX_LOGIN_ATTEMPTS) {
        $_SESSION['login_locked_until'] = time() + ADMIN_LOCK_SECONDS;
    }
}

function clearLoginLimiter(): void
{
    unset($_SESSION['login_failed_attempts'], $_SESSION['login_locked_until']);
}

function attemptAdminLogin(string $username, string $password): bool
{
    startAdminSession();

    if (loginIsLocked() || !adminConfigReady()) {
        return false;
    }

    $config = loadAdminConfig();
    $isValid = hash_equals((string) $config['admin_username'], $username)
        && password_verify($password, (string) $config['admin_password_hash']);

    if (!$isValid) {
        registerFailedLogin();
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_username'] = (string) $config['admin_username'];
    clearLoginLimiter();

    return true;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash(string $type, string $message): void
{
    startAdminSession();
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function consumeFlashMessages(): array
{
    startAdminSession();
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return is_array($messages) ? $messages : [];
}
