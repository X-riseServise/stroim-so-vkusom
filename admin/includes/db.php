<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function dbConfigReady(): bool
{
    $config = loadAdminConfig();

    return !empty($config['db_host'])
        && !empty($config['db_name'])
        && !empty($config['db_user'])
        && array_key_exists('db_password', $config);
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = loadAdminConfig();
    $charset = (string) ($config['db_charset'] ?? 'utf8mb4');

    if (!dbConfigReady()) {
        throw new RuntimeException('Настройки базы данных не заполнены в admin/includes/config.php.');
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        (string) $config['db_host'],
        (string) $config['db_name'],
        $charset
    );

    $pdo = new PDO($dsn, (string) $config['db_user'], (string) $config['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
