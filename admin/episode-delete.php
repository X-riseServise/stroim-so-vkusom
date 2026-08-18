<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/episodes.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Удаление доступно только через POST.';
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    flash('error', 'Сессия формы устарела. Повторите удаление.');
    header('Location: /admin/dashboard.php', true, 302);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    flash('error', 'Не удалось определить выпуск для удаления.');
    header('Location: /admin/dashboard.php', true, 302);
    exit;
}

try {
    deleteEpisode($id);
    flash('success', 'Выпуск удалён.');
} catch (Throwable $exception) {
    flash('error', 'Не удалось удалить выпуск. Проверьте подключение к БД.');
}

header('Location: /admin/dashboard.php', true, 302);
exit;
