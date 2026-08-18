<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

redirectIfAdmin();

$error = '';
$notice = '';

if (!adminConfigReady()) {
    $notice = 'Создайте admin/includes/config.php на основе config.example.php и укажите password hash.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Сессия формы устарела. Обновите страницу и попробуйте снова.';
    } elseif (loginIsLocked()) {
        $minutes = max(1, (int) ceil(loginLockRemainingSeconds() / 60));
        $error = 'Слишком много попыток входа. Повторите через ' . $minutes . ' мин.';
    } elseif (attemptAdminLogin(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''))) {
        header('Location: /admin/dashboard.php', true, 302);
        exit;
    } else {
        $error = 'Неверный логин или пароль.';
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
    <title>Вход в админку | Строим со вкусом</title>
    <link rel="stylesheet" href="/admin/assets/admin.css">
  </head>
  <body class="admin-page admin-page--login">
    <main class="auth-shell" aria-labelledby="login-title">
      <section class="auth-card">
        <p class="admin-kicker">Строим со вкусом</p>
        <h1 id="login-title">Вход в админку</h1>

        <?php if ($notice !== ''): ?>
          <div class="admin-alert admin-alert--notice" role="status"><?= e($notice) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
          <div class="admin-alert admin-alert--error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form class="admin-form" method="post" action="/admin/login.php" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

          <label class="admin-field">
            <span>Логин</span>
            <input type="text" name="username" autocomplete="username" required>
          </label>

          <label class="admin-field">
            <span>Пароль</span>
            <input type="password" name="password" autocomplete="current-password" required>
          </label>

          <button class="admin-button admin-button--primary" type="submit" <?php if (!adminConfigReady() || loginIsLocked()) echo 'disabled'; ?>>
            Войти
          </button>
        </form>
      </section>
    </main>
  </body>
</html>
