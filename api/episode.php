<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    apiError('invalid_episode_id', 'Некорректный ID выпуска.', 400);
}

try {
    $statement = db()->prepare(
        'SELECT *
         FROM episodes
         WHERE id = :id AND ' . apiPublishedWhere() . '
         LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $episode = $statement->fetch();

    if (!$episode) {
        apiError('episode_not_found', 'Выпуск не найден.', 404);
    }

    $recipes = apiFetchRecipes((int) $episode['id']);

    apiRespond([
        'success' => true,
        'episode' => apiEpisodePayload($episode, $recipes, true),
    ]);
} catch (Throwable $exception) {
    apiError('server_error', 'Не удалось загрузить выпуск.', 500);
}
