# Строим со вкусом

Статический одностраничный сайт с модульной архитектурой.

## Стек

- HTML5
- CSS3
- Vanilla JavaScript
- без React/Vue/Angular
- без Bootstrap
- без jQuery
- без сборщика

## Архитектура

Главная страница `index.html` подключает базовые стили, стили отдельных секций и `assets/js/main.js`.

HTML каждой секции хранится отдельно в папке `sections/`. Загрузка секций выполняется через `fetch()` в заданном порядке.

CSS каждой секции хранится отдельно в папке `assets/css/sections/`.

## Оригинальные видео

Оригинальные MP4-файлы находятся локально в папке:

```text
originals/video/
```

Эта папка исключена из Git, чтобы не добавлять большие исходники в репозиторий.

Папки `assets/video/intro/` и `assets/video/episodes/` оставлены для будущих оптимизированных web-версий.

## Локальный запуск

Так как секции загружаются через `fetch()`, открывать страницу через `file://` не нужно. Запустите простой HTTP-сервер из корня проекта:

```bash
python -m http.server 8765
```

Затем откройте:

```text
http://127.0.0.1:8765/
```

Для CMS и публичного API нужен PHP server, потому что `python -m http.server` не исполняет PHP:

```bash
php -S 127.0.0.1:8765
```

В этом режиме лендинг и API доступны с одного origin:

```text
http://127.0.0.1:8765/
http://127.0.0.1:8765/api/latest-episode.php
http://127.0.0.1:8765/api/episodes.php
```

Удобный запуск PHP-сервера на Windows:

```powershell
.\start-local.ps1
```

Адреса:

```text
http://127.0.0.1:8765/
http://127.0.0.1:8765/admin/
```

Для первого входа создайте локальный `admin/includes/config.php`, если его ещё нет, и установите пароль администратора:

```powershell
php tools/set-admin-password.php
```

Скрипт записывает только `password_hash()` в локальный `config.php`; исходный пароль не сохраняется и не выводится.

## Admin

Скрытая административная часть находится по адресу:

```text
/admin/
```

Публичный сайт не содержит ссылок на админку. Для работы нужен PHP 8+ с поддержкой sessions.

### Настройка доступа

1. Скопируйте пример конфига:

```bash
cp admin/includes/config.example.php admin/includes/config.php
```

2. Сгенерируйте hash пароля:

```bash
php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
```

3. В `admin/includes/config.php` укажите логин администратора и полученный `admin_password_hash`.

Файл `admin/includes/config.php` добавлен в `.gitignore`, поэтому реальный password hash не должен попадать в репозиторий. Сам пароль в коде не хранится.

### Как войти

Откройте `/admin/`. Если сессии нет, сайт перенаправит на `/admin/login.php`. После успешного входа откроется dashboard со списком выпусков.

### Что уже есть

- авторизация по логину и password hash через `password_verify()`;
- защищённая PHP-сессия с `HttpOnly`, `SameSite=Lax` и `Secure` на HTTPS;
- `session_regenerate_id(true)` после успешного входа;
- CSRF-token для login и будущих admin-form;
- session-based limiter входа: 5 неудачных попыток и временная блокировка;
- logout с очисткой и уничтожением сессии;
- заготовка формы `Добавить выпуск`.

Данные выпусков пока не сохраняются: база данных, CRUD, загрузка обложек, рецепты и публикация на публичном сайте будут подключаться следующими этапами.

### MySQL и выпуски

Для управления выпусками нужна MySQL 8+ или совместимая база с `utf8mb4`.

1. Создайте базу данных, например:

```sql
CREATE DATABASE stroim_so_vkusom CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. В `admin/includes/config.php` добавьте параметры подключения:

```php
'db_host' => '127.0.0.1',
'db_name' => 'stroim_so_vkusom',
'db_user' => 'database_user',
'db_password' => 'database_password',
'db_charset' => 'utf8mb4',
```

3. Выполните миграцию:

```bash
mysql -u database_user -p stroim_so_vkusom < database/migrations/001_create_episodes.sql
```

Таблица `episodes` содержит:

```text
id, episode_number, title, guest_name, guest_position, description,
vk_video_url, cover_image, published_at, status, created_at, updated_at
```

`episode_number` уникален и нормализуется к формату вроде `01`. Статусы ограничены значениями `draft` и `published`. Все операции создания, редактирования и удаления доступны только после входа в `/admin/`, используют CSRF-token и подготовленные PDO-запросы.

Чтобы создать выпуск, войдите в `/admin/`, откройте `+ Добавить выпуск`, заполните обязательные поля и нажмите `Сохранить`. Поле `vk_video_url` сохранено в схеме для обратной совместимости, но принимает официальные RUTUBE и VK embed-ссылки. Публикация выпусков из CMS на публичном сайте пока не подключена.

### Обложки, блюда и рецепты

Выполните вторую миграцию после `001_create_episodes.sql`:

```bash
mysql -u database_user -p stroim_so_vkusom < database/migrations/002_create_recipes.sql
```

Таблица `recipes` содержит:

```text
id, episode_id, title, description, ingredients, instructions,
image, sort_order, created_at, updated_at
```

`recipes.episode_id` связан с `episodes.id` через foreign key с `ON DELETE CASCADE`.

Загруженные файлы хранятся вне админки:

```text
uploads/episodes/
uploads/recipes/
```

Реальные uploads исключены из Git, в репозитории остаются только `.gitkeep` для структуры. Для Apache добавлен `uploads/.htaccess`, который запрещает листинг и выполнение опасных расширений. На другом web-server нужно отдельно запретить выполнение PHP/HTML в `uploads/`.

Разрешены только JPEG, PNG и WebP до 8 MB. SVG, GIF, PHP, HTML и файлы с поддельным расширением отклоняются backend-проверкой MIME/расширения/`getimagesize()`. Если на сервере доступен PHP GD, изображения уменьшаются и сохраняются web-оптимизированно в WebP: обложки до 1920 px по ширине, блюда до 1600 px. Если GD недоступен, безопасно сохраняется исходный разрешённый файл без падения.

Чтобы добавить блюдо, откройте редактирование выпуска и нажмите `+ Добавить блюдо`. Ингредиенты вводятся по одному с новой строки, рецепт сохраняется обычным текстом без HTML. Порядок блюд задаётся числом `sort_order`; drag-and-drop пока не подключён.

### Public API

Публичное API находится вне `/admin/` и доступно только на чтение:

```text
/api/latest-episode.php
/api/episodes.php
/api/episode.php?id=1
```

API возвращает только выпуски со статусом `published` и датой публикации не в будущем. Черновики не возвращаются даже по прямому ID.

Формат ошибок единый:

```json
{
  "success": false,
  "error": {
    "code": "episode_not_found",
    "message": "Выпуск не найден."
  }
}
```

`latest-episode.php` и `episode.php` возвращают связанные блюда. Ингредиенты преобразуются из textarea в массив строк, пустые строки удаляются. API возвращает нейтральное поле `video_url`; подготовленный Vanilla JS-клиент не вставляет пользовательский текст через `innerHTML` и поддерживает официальные RUTUBE и VK embed-ссылки.
