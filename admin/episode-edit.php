<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/recipes.php';

requireAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$episode = $id ? findEpisode($id) : null;

if (!$episode) {
    http_response_code(404);
}

$errors = [];
$flashMessages = consumeFlashMessages();
$values = $episode ? [
    'episode_number' => (string) $episode['episode_number'],
    'title' => (string) $episode['title'],
    'guest_name' => (string) $episode['guest_name'],
    'guest_position' => (string) ($episode['guest_position'] ?? ''),
    'description' => (string) $episode['description'],
    'vk_video_url' => (string) ($episode['vk_video_url'] ?? ''),
    'published_at' => formDateValue($episode['published_at'] ?? null),
    'status' => (string) $episode['status'],
] : [];
$recipes = [];

if ($episode) {
    try {
        $recipes = listRecipesByEpisode((int) $episode['id']);
    } catch (Throwable $exception) {
        flash('error', 'Не удалось загрузить блюда выпуска. Проверьте миграцию recipes.');
    }
}

if ($episode && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Сессия формы устарела. Обновите страницу и попробуйте снова.';
        $values = array_merge($values, array_intersect_key($_POST, $values));
    } else {
        [$data, $errors] = validateEpisodeInput($_POST);
        $values = array_merge($values, array_intersect_key($data, $values));
        $values['published_at'] = formDateValue($data['published_at']);

        if ($errors === []) {
            [$coverPath, $coverError] = handleImageUpload($_FILES['cover'] ?? [], 'episodes', 'episode-' . $data['episode_number'], 1920);

            if ($coverError) {
                $errors['cover'] = $coverError;
            } else {
                $data['cover_image'] = $coverPath ?: ($episode['cover_image'] ?? null);
            }
        }

        if ($errors === []) {
            try {
                updateEpisode((int) $id, $data);
                if (!empty($coverPath) && !empty($episode['cover_image'])) {
                    deleteUploadedFile((string) $episode['cover_image']);
                }
                flash('success', 'Изменения сохранены.');
                header('Location: /admin/episode-edit.php?id=' . (int) $id, true, 302);
                exit;
            } catch (Throwable $exception) {
                if (duplicateEpisodeError($exception)) {
                    $errors['episode_number'] = 'Выпуск с таким номером уже существует.';
                } else {
                    $errors['form'] = 'Не удалось сохранить изменения. Проверьте подключение к БД.';
                }
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
    <title>Редактировать выпуск | Администрирование</title>
    <link rel="stylesheet" href="/admin/assets/admin.css">
  </head>
  <body class="admin-page">
    <header class="admin-topbar">
      <a class="admin-brand" href="/admin/dashboard.php">Строим со вкусом — Администрирование</a>
      <a class="admin-logout" href="/admin/logout.php">Выйти</a>
    </header>

    <main class="admin-shell" aria-labelledby="episode-edit-title">
      <section class="admin-panel">
        <?php if (!$episode): ?>
          <div class="admin-not-found">
            <p class="admin-kicker">404</p>
            <h1 id="episode-edit-title">Выпуск не найден</h1>
            <p>Возможно, он был удалён или указан неверный ID.</p>
            <a class="admin-button admin-button--primary" href="/admin/dashboard.php">Вернуться к выпускам</a>
          </div>
        <?php else: ?>
          <div class="admin-panel__head">
            <div>
              <p class="admin-kicker">Выпуск №<?= e((string) $values['episode_number']) ?></p>
              <h1 id="episode-edit-title">Редактировать выпуск</h1>
            </div>
            <a class="admin-button admin-button--secondary" href="/admin/dashboard.php">Отмена</a>
          </div>

          <?php foreach ($flashMessages as $message): ?>
            <div class="admin-alert admin-alert--<?= e((string) $message['type']) ?>" role="status">
              <?= e((string) $message['message']) ?>
            </div>
          <?php endforeach; ?>

          <?php if (!empty($errors['form'])): ?>
            <div class="admin-alert admin-alert--error" role="alert"><?= e($errors['form']) ?></div>
          <?php endif; ?>

          <h2 class="admin-section-title">Основная информация</h2>
          <form class="admin-form admin-form--grid" method="post" action="/admin/episode-edit.php?id=<?= e((string) $id) ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <label class="admin-field">
              <span>Номер выпуска</span>
              <input type="text" name="episode_number" value="<?= e((string) $values['episode_number']) ?>" placeholder="01" inputmode="numeric" required>
              <?php if (!empty($errors['episode_number'])): ?><small><?= e($errors['episode_number']) ?></small><?php endif; ?>
            </label>

            <label class="admin-field">
              <span>Заголовок выпуска</span>
              <input type="text" name="title" value="<?= e((string) $values['title']) ?>" required>
              <?php if (!empty($errors['title'])): ?><small><?= e($errors['title']) ?></small><?php endif; ?>
            </label>

            <label class="admin-field">
              <span>Имя гостя</span>
              <input type="text" name="guest_name" value="<?= e((string) $values['guest_name']) ?>" required>
              <?php if (!empty($errors['guest_name'])): ?><small><?= e($errors['guest_name']) ?></small><?php endif; ?>
            </label>

            <label class="admin-field">
              <span>Должность / компания</span>
              <input type="text" name="guest_position" value="<?= e((string) $values['guest_position']) ?>">
            </label>

            <label class="admin-field admin-field--wide">
              <span>Краткое описание</span>
              <textarea name="description" rows="5" required><?= e((string) $values['description']) ?></textarea>
              <?php if (!empty($errors['description'])): ?><small><?= e($errors['description']) ?></small><?php endif; ?>
            </label>

            <label class="admin-field">
              <span>Ссылка на VK Видео</span>
              <input type="text" name="vk_video_url" value="<?= e((string) $values['vk_video_url']) ?>" placeholder="https://vkvideo.ru/video_ext.php?oid=-127401043&id=456254970&hd=4" inputmode="url">
              <em>Можно вставить ссылку VK Video embed; если вставить iframe, сохранится только src.</em>
              <?php if (!empty($errors['vk_video_url'])): ?><small><?= e($errors['vk_video_url']) ?></small><?php endif; ?>
            </label>

            <label class="admin-field">
              <span>Дата публикации</span>
              <input type="date" name="published_at" value="<?= e((string) $values['published_at']) ?>">
              <?php if (!empty($errors['published_at'])): ?><small><?= e($errors['published_at']) ?></small><?php endif; ?>
            </label>

            <label class="admin-field">
              <span>Статус</span>
              <select name="status">
                <option value="draft" <?php if ($values['status'] === 'draft') echo 'selected'; ?>>Черновик</option>
                <option value="published" <?php if ($values['status'] === 'published') echo 'selected'; ?>>Опубликован</option>
              </select>
              <?php if (!empty($errors['status'])): ?><small><?= e($errors['status']) ?></small><?php endif; ?>
            </label>

            <label class="admin-field">
              <span>Обложка</span>
              <?php if (!empty($episode['cover_image'])): ?>
                <img class="admin-cover-preview" src="<?= e(publicUploadPath((string) $episode['cover_image'])) ?>" alt="Текущая обложка выпуска">
              <?php endif; ?>
              <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
              <?php if (!empty($errors['cover'])): ?><small><?= e($errors['cover']) ?></small><?php endif; ?>
            </label>

            <div class="admin-note admin-field--wide">
              Блюда и рецепты будут добавлены на следующем этапе.
            </div>

            <div class="admin-form__actions admin-field--wide">
              <button class="admin-button admin-button--primary" type="submit">Сохранить</button>
              <a class="admin-button admin-button--secondary" href="/admin/dashboard.php">Отмена</a>
            </div>
          </form>

          <section class="admin-subsection" aria-labelledby="recipes-title">
            <div class="admin-panel__head admin-panel__head--compact">
              <div>
                <p class="admin-kicker">Блюда</p>
                <h2 id="recipes-title" class="admin-section-title">Блюда выпуска</h2>
              </div>
              <a class="admin-button admin-button--primary" href="/admin/recipe-create.php?episode_id=<?= e((string) $id) ?>">+ Добавить блюдо</a>
            </div>

            <?php if (count($recipes) === 0): ?>
              <div class="empty-state">
                <p>Блюда пока не добавлены.</p>
                <a class="admin-button admin-button--primary" href="/admin/recipe-create.php?episode_id=<?= e((string) $id) ?>">+ Добавить блюдо</a>
              </div>
            <?php else: ?>
              <div class="recipe-list">
                <?php foreach ($recipes as $recipe): ?>
                  <article class="recipe-card">
                    <?php if (!empty($recipe['image'])): ?>
                      <img src="<?= e(publicUploadPath((string) $recipe['image'])) ?>" alt="">
                    <?php else: ?>
                      <div class="recipe-card__placeholder" aria-hidden="true">Нет фото</div>
                    <?php endif; ?>
                    <div>
                      <p class="recipe-card__order">Порядок: <?= e((string) $recipe['sort_order']) ?></p>
                      <h3><?= e((string) $recipe['title']) ?></h3>
                      <?php if (!empty($recipe['description'])): ?>
                        <p><?= e((string) $recipe['description']) ?></p>
                      <?php endif; ?>
                    </div>
                    <div class="admin-actions">
                      <a class="admin-link-button" href="/admin/recipe-edit.php?id=<?= e((string) $recipe['id']) ?>">Редактировать</a>
                      <form method="post" action="/admin/recipe-delete.php" data-confirm-delete="Удалить блюдо «<?= e((string) $recipe['title']) ?>»?">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $recipe['id']) ?>">
                        <button class="admin-danger-button" type="submit">Удалить</button>
                      </form>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        <?php endif; ?>
      </section>
    </main>
    <script src="/admin/assets/admin.js" defer></script>
  </body>
</html>
