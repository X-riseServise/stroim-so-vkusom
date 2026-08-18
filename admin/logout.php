<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';

destroyAdminSession();

header('Location: /admin/login.php', true, 302);
exit;
