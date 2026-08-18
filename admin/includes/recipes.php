<?php

declare(strict_types=1);

require_once __DIR__ . '/episodes.php';

function validateRecipeInput(array $input): array
{
    $data = [
        'title' => trim((string) ($input['title'] ?? '')),
        'description' => trim((string) ($input['description'] ?? '')),
        'ingredients' => trim((string) ($input['ingredients'] ?? '')),
        'instructions' => trim((string) ($input['instructions'] ?? '')),
        'sort_order' => (int) ($input['sort_order'] ?? 1),
        'image' => null,
    ];

    $errors = [];

    if ($data['title'] === '') {
        $errors['title'] = 'Укажите название блюда.';
    }

    if ($data['ingredients'] === '') {
        $errors['ingredients'] = 'Добавьте ингредиенты.';
    }

    if ($data['instructions'] === '') {
        $errors['instructions'] = 'Добавьте рецепт.';
    }

    if ($data['sort_order'] < 1 || $data['sort_order'] > 999) {
        $errors['sort_order'] = 'Порядок должен быть числом от 1 до 999.';
    }

    if ($data['description'] === '') {
        $data['description'] = null;
    }

    return [$data, $errors];
}

function listRecipesByEpisode(int $episodeId): array
{
    $statement = db()->prepare(
        'SELECT * FROM recipes
         WHERE episode_id = :episode_id
         ORDER BY sort_order ASC, id ASC'
    );
    $statement->execute(['episode_id' => $episodeId]);

    return $statement->fetchAll();
}

function listRecipeImagesByEpisode(int $episodeId): array
{
    $statement = db()->prepare('SELECT image FROM recipes WHERE episode_id = :episode_id AND image IS NOT NULL');
    $statement->execute(['episode_id' => $episodeId]);

    return array_filter(array_column($statement->fetchAll(), 'image'));
}

function findRecipe(int $id): ?array
{
    $statement = db()->prepare('SELECT * FROM recipes WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $recipe = $statement->fetch();

    return $recipe ?: null;
}

function createRecipe(int $episodeId, array $data): int
{
    $data['episode_id'] = $episodeId;
    $statement = db()->prepare(
        'INSERT INTO recipes
            (episode_id, title, description, ingredients, instructions, image, sort_order)
         VALUES
            (:episode_id, :title, :description, :ingredients, :instructions, :image, :sort_order)'
    );
    $statement->execute($data);

    return (int) db()->lastInsertId();
}

function updateRecipe(int $id, array $data): void
{
    $data['id'] = $id;
    $statement = db()->prepare(
        'UPDATE recipes
         SET title = :title,
             description = :description,
             ingredients = :ingredients,
             instructions = :instructions,
             image = :image,
             sort_order = :sort_order,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $statement->execute($data);
}

function deleteRecipe(int $id): ?int
{
    $recipe = findRecipe($id);

    if (!$recipe) {
        return null;
    }

    $statement = db()->prepare('DELETE FROM recipes WHERE id = :id');
    $statement->execute(['id' => $id]);
    deleteUploadedFile($recipe['image'] ?? null);

    return (int) $recipe['episode_id'];
}
