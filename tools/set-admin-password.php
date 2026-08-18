<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$configPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'config.php';

function readHiddenPassword(string $prompt): ?string
{
    if (PHP_OS_FAMILY === 'Windows') {
        $command = 'powershell -NoProfile -Command "$secure = Read-Host ' . escapeshellarg($prompt) . ' -AsSecureString; ' .
            '$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure); ' .
            'try { [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }"';
        $password = shell_exec($command);

        return $password !== null ? rtrim($password, "\r\n") : null;
    }

    if (function_exists('shell_exec')) {
        shell_exec('stty -echo');
        fwrite(STDOUT, $prompt . ': ');
        $password = fgets(STDIN);
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);

        return $password !== false ? rtrim($password, "\r\n") : null;
    }

    return null;
}

if (!is_file($configPath)) {
    fwrite(STDERR, "admin/includes/config.php not found. Create it from config.example.php first.\n");
    exit(1);
}

$password = readHiddenPassword('New admin password');

if ($password === null || $password === '') {
    fwrite(STDERR, "Password was not set.\n");
    exit(1);
}

$confirm = readHiddenPassword('Repeat admin password');

if ($password !== $confirm) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}

$config = require $configPath;

if (!is_array($config)) {
    fwrite(STDERR, "Invalid config.php format.\n");
    exit(1);
}

$config['admin_username'] = $config['admin_username'] ?? 'admin';
$config['admin_password_hash'] = password_hash($password, PASSWORD_DEFAULT);

$export = var_export($config, true);
$content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . $export . ";\n";

if (file_put_contents($configPath, $content, LOCK_EX) === false) {
    fwrite(STDERR, "Could not write admin/includes/config.php.\n");
    exit(1);
}

fwrite(STDOUT, "Пароль администратора успешно установлен.\n");
