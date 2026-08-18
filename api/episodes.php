<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $statement = db()->query(
        'SELECT id, episode_number, title, guest_name, guest_position, description, vk_video_url, cover_image, published_at
         FROM episodes
         WHERE ' . apiPublishedWhere() . '
         ORDER BY published_at DESC, CAST(episode_number AS UNSIGNED) DESC, id DESC'
    );

    apiRespond([
        'success' => true,
        'episodes' => array_map(
            static fn (array $episode): array => apiEpisodePayload($episode),
            $statement->fetchAll()
        ),
    ]);
} catch (Throwable $exception) {
    apiError('server_error', 'Не удалось загрузить список выпусков.', 500);
}
