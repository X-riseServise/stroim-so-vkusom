<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/episodes.php';

requireAdmin();

$errors = [];
$values = [
    'episode_number' => '',
    'title' => '',
    'guest_name' => '',
    'guest_position' => '',
    'description' => '',
    'vk_video_url' => '',
    'published_at' => '',
    'status' => 'draft',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                $data['cover_image'] = $coverPath;
            }
        }

        if ($errors === []) {
            try {
                createEpisode($data);
                flash('success', 'Выпуск успешно создан.');
                header('Location: /admin/dashboard.php', true, 302);
                exit;
            } catch (Throwable $exception) {
                if (duplicateEpisodeError($exception)) {
                    $errors['episode_number'] = 'Выпуск с таким номером уже существует.';
                } else {
                    $errors['form'] = 'Не удалось сохранить выпуск. Проверьте подключение к БД.';
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
    <title>Добавить выпуск | Администрирование</title>
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

    <main class="admin-shell" aria-labelledby="episode-create-title">
      <section class="admin-panel">
        <div class="admin-panel__head">
          <div>
            <p class="admin-kicker">Новый выпуск</p>
            <h1 id="episode-create-title">Добавить выпуск</h1>
          </div>
          <a class="admin-button admin-button--secondary" href="/admin/dashboard.php">Отмена</a>
        </div>

        <?php if (!empty($errors['form'])): ?>
          <div class="admin-alert admin-alert--error" role="alert"><?= e($errors['form']) ?></div>
        <?php endif; ?>

        <form class="admin-form admin-form--grid" method="post" action="/admin/episode-create.php" enctype="multipart/form-data">
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
            <span>Ссылка на видео</span>
            <input type="text" name="video_url" value="<?= e((string) $values['vk_video_url']) ?>" placeholder="https://rutube.ru/play/embed/..." inputmode="url">
            <em>Поддерживаются RUTUBE и VK Video; из iframe сохранится только src.</em>
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
      </section>
    </main>
    <script src="/admin/assets/admin.js" defer></script>
  </body>
</html>
