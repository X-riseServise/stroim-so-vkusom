<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/episodes.php';

requireAdmin();

$csrfToken = generateCsrfToken();
$flashMessages = consumeFlashMessages();
$episodes = [];
$loadError = '';

try {
    $episodes = listEpisodes();
} catch (Throwable $exception) {
    $loadError = dbConfigReady()
        ? 'Не удалось загрузить выпуски. Проверьте настройки БД и выполните миграцию.'
        : 'База данных пока не настроена.';
}
?>
<!doctype html>
<html lang="ru">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Выпуски | Администрирование</title>
    <link rel="stylesheet" href="/admin/assets/admin.css">
  </head>
  <body class="admin-page">
    <header class="admin-topbar">
      <a class="admin-brand" href="/admin/dashboard.php">Строим со вкусом — Администрирование</a>
      <a class="admin-logout" href="/admin/logout.php">Выйти</a>
    </header>

    <main class="admin-shell" aria-labelledby="dashboard-title">
      <section class="admin-panel">
        <div class="admin-panel__head">
          <div>
            <p class="admin-kicker">CMS</p>
            <h1 id="dashboard-title">Выпуски</h1>
          </div>
          <a class="admin-button admin-button--primary" href="/admin/episode-create.php">+ Добавить выпуск</a>
        </div>

        <?php foreach ($flashMessages as $message): ?>
          <div class="admin-alert admin-alert--<?= e((string) $message['type']) ?>" role="status">
            <?= e((string) $message['message']) ?>
          </div>
        <?php endforeach; ?>

        <?php if ($loadError !== ''): ?>
          <div class="admin-alert admin-alert--error" role="alert"><?= e($loadError) ?></div>
        <?php elseif (count($episodes) === 0): ?>
          <div class="empty-state">
            <p>Пока нет ни одного выпуска.</p>
            <a class="admin-button admin-button--primary" href="/admin/episode-create.php">+ Добавить первый выпуск</a>
          </div>
        <?php else: ?>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>№</th>
                  <th>Заголовок</th>
                  <th>Гость</th>
                  <th>Статус</th>
                  <th>Блюд</th>
                  <th>Дата публикации</th>
                  <th>Обновлён</th>
                  <th>Действия</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($episodes as $episode): ?>
                  <tr>
                    <td data-label="№"><?= e((string) $episode['episode_number']) ?></td>
                    <td data-label="Заголовок"><?= e((string) $episode['title']) ?></td>
                    <td data-label="Гость"><?= e((string) $episode['guest_name']) ?></td>
                    <td data-label="Статус">
                      <span class="status-badge status-badge--<?= e((string) $episode['status']) ?>">
                        <?= e(statusLabel((string) $episode['status'])) ?>
                      </span>
                    </td>
                    <td data-label="Блюд"><?= e((string) $episode['recipes_count']) ?></td>
                    <td data-label="Дата публикации"><?= e(formatAdminDate($episode['published_at'] ?? null)) ?></td>
                    <td data-label="Обновлён"><?= e(formatAdminDate($episode['updated_at'] ?? null)) ?></td>
                    <td data-label="Действия">
                      <div class="admin-actions">
                        <a class="admin-link-button" href="/admin/episode-edit.php?id=<?= e((string) $episode['id']) ?>">Редактировать</a>
                        <form method="post" action="/admin/episode-delete.php" data-confirm-delete="Удалить выпуск №<?= e((string) $episode['episode_number']) ?>?">
                          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                          <input type="hidden" name="id" value="<?= e((string) $episode['id']) ?>">
                          <button class="admin-danger-button" type="submit">Удалить</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </main>
    <script src="/admin/assets/admin.js" defer></script>
  </body>
</html>
