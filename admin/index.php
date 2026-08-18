<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (isAdminAuthenticated()) {
    header('Location: /admin/dashboard.php', true, 302);
    exit;
}

header('Location: /admin/login.php', true, 302);
exit;
