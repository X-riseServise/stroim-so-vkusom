<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit;
}

destroyAdminSession();

header('Location: /admin/login.php', true, 302);
exit;
