<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/upload.php';

const EPISODE_STATUSES = ['draft', 'published'];

function normalizeEpisodeNumber(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (ctype_digit($value)) {
        return str_pad((string) ((int) $value), 2, '0', STR_PAD_LEFT);
    }

    return $value;
}

function statusLabel(string $status): string
{
    return $status === 'published' ? 'Опубликован' : 'Черновик';
}

function formatAdminDate(?string $value): string
{
    if (!$value) {
        return '—';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d.m.Y H:i', $timestamp) : $value;
}

function formDateValue(?string $value): string
{
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d', $timestamp) : '';
}

function extractVideoUrl(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (preg_match('/<iframe\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $value, $matches)) {
        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return $value;
}

function normalizeVideoUrl(string $value): ?string
{
    $value = extractVideoUrl($value);

    if ($value === '') {
        return null;
    }

    if (preg_match('/^(javascript|data):/i', $value)) {
        return null;
    }

    if (!preg_match('/^https?:\/\//i', $value)) {
        $value = 'https://' . $value;
    }

    $url = filter_var($value, FILTER_VALIDATE_URL);

    if (!$url) {
        return null;
    }

    $parts = parse_url((string) $url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '');
    $query = [];

    if ($scheme !== 'https' && $scheme !== 'http') {
        return null;
    }

    $host = preg_replace('/^www\./', '', $host);

    if ($host === 'rutube.ru') {
        if (!preg_match('#^/(?:play/embed|video(?:/private)?)/([a-f\d]{32})/?$#i', $path, $matches)) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $embedUrl = 'https://rutube.ru/play/embed/' . strtolower($matches[1]) . '/';
        $accessKey = (string) ($query['p'] ?? '');

        if ($accessKey !== '' && preg_match('/^[a-zA-Z0-9_-]+$/', $accessKey)) {
            $embedUrl .= '?p=' . rawurlencode($accessKey);
        }

        return $embedUrl;
    }

    if ($host !== 'vk.com' && $host !== 'vkvideo.ru') {
        return null;
    }

    if ($path === '/video_ext.php') {
        parse_str((string) ($parts['query'] ?? ''), $query);

        $oid = (string) ($query['oid'] ?? '');
        $id = (string) ($query['id'] ?? '');

        if (!preg_match('/^-?\d+$/', $oid) || !preg_match('/^\d+$/', $id)) {
            return null;
        }
    } elseif (preg_match('/^\/video(-?\d+)_(\d+)$/', $path, $matches)) {
        $oid = $matches[1];
        $id = $matches[2];
    } else {
        return null;
    }

    $params = [
        'oid' => $oid,
        'id' => $id,
    ];

    $hash = (string) ($query['hash'] ?? '');
    if ($hash !== '' && preg_match('/^[a-zA-Z0-9_-]+$/', $hash)) {
        $params['hash'] = $hash;
    }

    $hd = (string) ($query['hd'] ?? '4');
    $params['hd'] = preg_match('/^[0-4]$/', $hd) ? $hd : '4';

    return 'https://vkvideo.ru/video_ext.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function validateEpisodeInput(array $input): array
{
    $data = [
        'episode_number' => normalizeEpisodeNumber((string) ($input['episode_number'] ?? '')),
        'title' => trim((string) ($input['title'] ?? '')),
        'guest_name' => trim((string) ($input['guest_name'] ?? '')),
        'guest_position' => trim((string) ($input['guest_position'] ?? '')),
        'description' => trim((string) ($input['description'] ?? '')),
        'vk_video_url' => extractVideoUrl((string) ($input['video_url'] ?? $input['vk_video_url'] ?? '')),
        'published_at' => trim((string) ($input['published_at'] ?? '')),
        'status' => (string) ($input['status'] ?? 'draft'),
        'cover_image' => null,
    ];

    $errors = [];

    if ($data['episode_number'] === '') {
        $errors['episode_number'] = 'Укажите номер выпуска.';
    } elseif (!preg_match('/^\d{2,4}$/', $data['episode_number'])) {
        $errors['episode_number'] = 'Номер должен быть числом, например 01.';
    }

    if ($data['title'] === '') {
        $errors['title'] = 'Укажите заголовок выпуска.';
    }

    if ($data['guest_name'] === '') {
        $errors['guest_name'] = 'Укажите имя гостя.';
    }

    if ($data['description'] === '') {
        $errors['description'] = 'Добавьте краткое описание.';
    }

    if (!in_array($data['status'], EPISODE_STATUSES, true)) {
        $errors['status'] = 'Выберите корректный статус.';
    }

    if ($data['vk_video_url'] !== '') {
        $normalizedVideoUrl = normalizeVideoUrl($data['vk_video_url']);

        if ($normalizedVideoUrl === null) {
            $errors['vk_video_url'] = 'Укажите корректную ссылку RUTUBE или VK Видео.';
        } else {
            $data['vk_video_url'] = $normalizedVideoUrl;
        }
    }

    if ($data['published_at'] !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $data['published_at']);
        $dateErrors = DateTime::getLastErrors();
        $hasDateErrors = is_array($dateErrors)
            && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0);

        if (!$date || $hasDateErrors) {
            $errors['published_at'] = 'Укажите корректную дату публикации.';
        } else {
            $data['published_at'] = $date->format('Y-m-d 00:00:00');
        }
    } elseif ($data['status'] === 'published') {
        $data['published_at'] = date('Y-m-d H:i:s');
    } else {
        $data['published_at'] = null;
    }

    if ($data['guest_position'] === '') {
        $data['guest_position'] = null;
    }

    if ($data['vk_video_url'] === '') {
        $data['vk_video_url'] = null;
    }

    return [$data, $errors];
}

function listEpisodes(): array
{
    $statement = db()->query(
        'SELECT episodes.id,
                episodes.episode_number,
                episodes.title,
                episodes.guest_name,
                episodes.status,
                episodes.published_at,
                episodes.updated_at,
                (SELECT COUNT(*)
                 FROM recipes
                 WHERE recipes.episode_id = episodes.id) AS recipes_count
         FROM episodes
         ORDER BY CAST(episode_number AS UNSIGNED) DESC, id DESC'
    );

    return $statement->fetchAll();
}

function findEpisode(int $id): ?array
{
    $statement = db()->prepare('SELECT * FROM episodes WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $episode = $statement->fetch();

    return $episode ?: null;
}

function createEpisode(array $data): int
{
    $statement = db()->prepare(
        'INSERT INTO episodes
            (episode_number, title, guest_name, guest_position, description, vk_video_url, cover_image, published_at, status)
         VALUES
            (:episode_number, :title, :guest_name, :guest_position, :description, :vk_video_url, :cover_image, :published_at, :status)'
    );
    $statement->execute($data);

    return (int) db()->lastInsertId();
}

function updateEpisode(int $id, array $data): void
{
    $data['id'] = $id;
    $statement = db()->prepare(
        'UPDATE episodes
         SET episode_number = :episode_number,
             title = :title,
             guest_name = :guest_name,
             guest_position = :guest_position,
             description = :description,
             vk_video_url = :vk_video_url,
             cover_image = :cover_image,
             published_at = :published_at,
             status = :status,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $statement->execute($data);
}

function deleteEpisode(int $id): void
{
    $episode = findEpisode($id);
    $imageStatement = db()->prepare('SELECT image FROM recipes WHERE episode_id = :episode_id AND image IS NOT NULL');
    $imageStatement->execute(['episode_id' => $id]);
    $recipeImages = array_filter(array_column($imageStatement->fetchAll(), 'image'));

    $statement = db()->prepare('DELETE FROM episodes WHERE id = :id');
    $statement->execute(['id' => $id]);

    if ($episode) {
        deleteUploadedFile($episode['cover_image'] ?? null);
    }

    foreach ($recipeImages as $image) {
        deleteUploadedFile($image);
    }
}

function duplicateEpisodeError(Throwable $exception): bool
{
    if (!$exception instanceof PDOException) {
        return false;
    }

    return (string) ($exception->errorInfo[1] ?? '') === '1062';
}
