<?php

declare(strict_types=1);

require_once __DIR__ . '/../admin/includes/db.php';

function apiRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiError(string $code, string $message, int $status): void
{
    apiRespond([
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ], $status);
}

function apiPublicPath(?string $path): ?string
{
    if (!$path) {
        return null;
    }

    $normalized = str_replace('\\', '/', $path);

    if (!str_starts_with($normalized, 'uploads/')) {
        return null;
    }

    return '/' . $normalized;
}

function apiIngredientsToArray(?string $ingredients): array
{
    if (!$ingredients) {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn (string $line): string => trim($line),
        preg_split('/\R/u', $ingredients) ?: []
    )));
}

function apiRecipePayload(array $recipe): array
{
    return [
        'id' => (int) $recipe['id'],
        'title' => (string) $recipe['title'],
        'description' => $recipe['description'] !== null ? (string) $recipe['description'] : null,
        'ingredients' => apiIngredientsToArray($recipe['ingredients'] ?? null),
        'instructions' => (string) $recipe['instructions'],
        'image' => apiPublicPath($recipe['image'] ?? null),
        'sort_order' => (int) $recipe['sort_order'],
    ];
}

function apiEpisodePayload(array $episode, array $recipes = [], bool $includeRecipes = false): array
{
    $payload = [
        'id' => (int) $episode['id'],
        'number' => (int) $episode['episode_number'],
        'number_label' => (string) $episode['episode_number'],
        'title' => (string) $episode['title'],
        'guest' => [
            'name' => (string) $episode['guest_name'],
            'position' => $episode['guest_position'] !== null ? (string) $episode['guest_position'] : null,
        ],
        'description' => (string) $episode['description'],
        'video_url' => $episode['vk_video_url'] !== null ? (string) $episode['vk_video_url'] : null,
        'cover_image' => apiPublicPath($episode['cover_image'] ?? null),
        'published_at' => $episode['published_at'] !== null ? (string) $episode['published_at'] : null,
    ];

    if ($includeRecipes) {
        $payload['recipes'] = array_map('apiRecipePayload', $recipes);
    }

    return $payload;
}

function apiFetchRecipes(int $episodeId): array
{
    $statement = db()->prepare(
        'SELECT id, title, description, ingredients, instructions, image, sort_order
         FROM recipes
         WHERE episode_id = :episode_id
         ORDER BY sort_order ASC, id ASC'
    );
    $statement->execute(['episode_id' => $episodeId]);

    return $statement->fetchAll();
}

function apiPublishedWhere(): string
{
    return "status = 'published' AND (published_at IS NULL OR published_at <= NOW())";
}
