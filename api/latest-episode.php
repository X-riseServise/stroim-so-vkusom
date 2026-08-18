<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $statement = db()->query(
        'SELECT *
         FROM episodes
         WHERE ' . apiPublishedWhere() . '
         ORDER BY published_at DESC, CAST(episode_number AS UNSIGNED) DESC, id DESC
         LIMIT 1'
    );
    $episode = $statement->fetch();

    if (!$episode) {
        apiRespond([
            'success' => true,
            'episode' => null,
        ]);
    }

    $recipes = apiFetchRecipes((int) $episode['id']);

    apiRespond([
        'success' => true,
        'episode' => apiEpisodePayload($episode, $recipes, true),
    ]);
} catch (Throwable $exception) {
    apiError('server_error', 'Не удалось загрузить выпуск.', 500);
}
