<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/recipes.php';

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
    flash('error', 'Не удалось определить блюдо для удаления.');
    header('Location: /admin/dashboard.php', true, 302);
    exit;
}

try {
    $episodeId = deleteRecipe($id);
    if (!$episodeId) {
        flash('error', 'Блюдо не найдено.');
        header('Location: /admin/dashboard.php', true, 302);
        exit;
    }

    flash('success', 'Блюдо удалено.');
    header('Location: /admin/episode-edit.php?id=' . (int) $episodeId, true, 302);
    exit;
} catch (Throwable $exception) {
    flash('error', 'Не удалось удалить блюдо. Проверьте подключение к БД.');
    header('Location: /admin/dashboard.php', true, 302);
    exit;
}
