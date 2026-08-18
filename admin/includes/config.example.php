<?php

declare(strict_types=1);

/**
 * Copy this file to config.php and replace the values below.
 *
 * Generate a password hash:
 * php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
 */
return [
    'admin_username' => 'admin',
    'admin_password_hash' => '$2y$10$replaceThisWithYourGeneratedPasswordHash',
    'db_host' => '127.0.0.1',
    'db_name' => 'stroim_so_vkusom',
    'db_user' => 'database_user',
    'db_password' => 'database_password',
    'db_charset' => 'utf8mb4',
];
