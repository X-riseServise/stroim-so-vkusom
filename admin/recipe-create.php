<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/recipes.php';

requireAdmin();

$episodeId = filter_input(INPUT_GET, 'episode_id', FILTER_VALIDATE_INT);
$episode = $episodeId ? findEpisode($episodeId) : null;

if (!$episode) {
    http_response_code(404);
}

$errors = [];
$values = [
    'title' => '',
    'description' => '',
    'ingredients' => '',
    'instructions' => '',
    'sort_order' => '1',
];

if ($episode && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Сессия формы устарела. Обновите страницу и попробуйте снова.';
        $values = array_merge($values, array_intersect_key($_POST, $values));
    } else {
        [$data, $errors] = validateRecipeInput($_POST);
        $values = array_merge($values, array_intersect_key($data, $values));

        if ($errors === []) {
            [$imagePath, $imageError] = handleImageUpload($_FILES['image'] ?? [], 'recipes', 'recipe-' . $episode['episode_number'], 1600);

            if ($imageError) {
                $errors['image'] = $imageError;
            } else {
                $data['image'] = $imagePath;
            }
        }

        if ($errors === []) {
            try {
                createRecipe((int) $episode['id'], $data);
                flash('success', 'Блюдо добавлено.');
                header('Location: /admin/episode-edit.php?id=' . (int) $episode['id'], true, 302);
                exit;
            } catch (Throwable $exception) {
                $errors['form'] = 'Не удалось сохранить блюдо. Проверьте подключение к БД.';
            }
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!doctype html>
<html lang="ru">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Добавить блюдо | Администрирование</title>
    <link rel="stylesheet" href="/admin/assets/admin.css">
  </head>
  <body class="admin-page">
    <header class="admin-topbar">
      <a class="admin-brand" href="/admin/dashboard.php">Строим со вкусом — Администрирование</a>
      <form method="post" action="/admin/logout.php">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <button class="admin-logout" type="submit">Выйти</button>
      </form>
    </header>

    <main class="admin-shell" aria-labelledby="recipe-create-title">
      <section class="admin-panel">
        <?php if (!$episode): ?>
          <div class="admin-not-found">
            <p class="admin-kicker">404</p>
            <h1 id="recipe-create-title">Выпуск не найден</h1>
            <p>Нельзя добавить блюдо без существующего выпуска.</p>
            <a class="admin-button admin-button--primary" href="/admin/dashboard.php">Вернуться к выпускам</a>
          </div>
        <?php else: ?>
          <div class="admin-panel__head">
            <div>
              <p class="admin-kicker">Выпуск №<?= e((string) $episode['episode_number']) ?></p>
              <h1 id="recipe-create-title">Добавить блюдо</h1>
            </div>
            <a class="admin-button admin-button--secondary" href="/admin/episode-edit.php?id=<?= e((string) $episode['id']) ?>">Отмена</a>
          </div>

          <?php if (!empty($errors['form'])): ?>
            <div class="admin-alert admin-alert--error" role="alert"><?= e($errors['form']) ?></div>
          <?php endif; ?>

          <form class="admin-form admin-form--grid" method="post" action="/admin/recipe-create.php?episode_id=<?= e((string) $episode['id']) ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <label class="admin-field">
              <span>Название блюда</span>
              <input type="text" name="title" value="<?= e((string) $values['title']) ?>" required>
              <?php if (!empty($errors['title'])): ?><small><?= e($errors['title']) ?></small><?php endif; ?>
            </label>

            <label class="admin-field">
              <span>Порядок</span>
              <input type="number" name="sort_order" min="1" max="999" value="<?= e((string) $values['sort_order']) ?>">
              <?php if (!empty($errors['sort_order'])): ?><small><?= e($errors['sort_order']) ?></small><?php endif; ?>
            </label>

            <label class="admin-field admin-field--wide">
              <span>Краткое описание</span>
              <textarea name="description" rows="3"><?= e((string) $values['description']) ?></textarea>
            </label>

            <label class="admin-field admin-field--wide">
              <span>Ингредиенты</span>
              <textarea name="ingredients" rows="6" required><?= e((string) $values['ingredients']) ?></textarea>
              <em>Каждый ингредиент — с новой строки.</em>
              <?php if (!empty($errors['ingredients'])): ?><small><?= e($errors['ingredients']) ?></small><?php endif; ?>
            </label>

            <label class="admin-field admin-field--wide">
              <span>Рецепт</span>
              <textarea name="instructions" rows="8" required><?= e((string) $values['instructions']) ?></textarea>
              <?php if (!empty($errors['instructions'])): ?><small><?= e($errors['instructions']) ?></small><?php endif; ?>
            </label>

            <label class="admin-field admin-field--wide">
              <span>Изображение блюда</span>
              <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
              <?php if (!empty($errors['image'])): ?><small><?= e($errors['image']) ?></small><?php endif; ?>
            </label>

            <div class="admin-form__actions admin-field--wide">
              <button class="admin-button admin-button--primary" type="submit">Сохранить</button>
              <a class="admin-button admin-button--secondary" href="/admin/episode-edit.php?id=<?= e((string) $episode['id']) ?>">Отмена</a>
            </div>
          </form>
        <?php endif; ?>
      </section>
    </main>
    <script src="/admin/assets/admin.js" defer></script>
  </body>
</html>
