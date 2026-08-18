<?php

declare(strict_types=1);

const ADMIN_UPLOAD_MAX_BYTES = 8388608;

function uploadBasePath(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads';
}

function publicUploadPath(string $relativePath): string
{
    return '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
}

function deleteUploadedFile(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }

    $normalized = str_replace('\\', '/', $relativePath);
    if (!str_starts_with($normalized, 'uploads/')) {
        return;
    }

    $fullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    $uploadsRoot = realpath(uploadBasePath());
    $filePath = realpath($fullPath);

    if ($uploadsRoot && $filePath && str_starts_with($filePath, $uploadsRoot) && is_file($filePath)) {
        unlink($filePath);
    }
}

function hasUploadedFile(array $file): bool
{
    return isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
}

function handleImageUpload(array $file, string $folder, string $prefix, int $maxWidth): array
{
    if (!hasUploadedFile($file)) {
        return [null, null];
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Не удалось загрузить изображение. Попробуйте ещё раз.'];
    }

    if ((int) $file['size'] > ADMIN_UPLOAD_MAX_BYTES) {
        return [null, 'Размер изображения не должен превышать 8 MB.'];
    }

    $tmpName = (string) $file['tmp_name'];
    $imageInfo = @getimagesize($tmpName);

    if (!$imageInfo) {
        return [null, 'Файл должен быть настоящим изображением JPEG, PNG или WebP.'];
    }

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mime = (string) ($imageInfo['mime'] ?? '');

    if (!isset($allowedMime[$mime])) {
        return [null, 'Разрешены только JPEG, PNG и WebP.'];
    }

    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($extension, $allowedExtensions, true)) {
        return [null, 'Расширение файла должно быть jpg, jpeg, png или webp.'];
    }

    $targetDirectory = uploadBasePath() . DIRECTORY_SEPARATOR . $folder;
    if (!is_dir($targetDirectory)) {
        mkdir($targetDirectory, 0755, true);
    }

    $safeName = sprintf('%s-%s', $prefix, bin2hex(random_bytes(5)));
    $canWebp = function_exists('imagewebp') && function_exists('imagecreatetruecolor');
    $targetExtension = $canWebp ? 'webp' : $allowedMime[$mime];
    $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $safeName . '.' . $targetExtension;
    $relativePath = 'uploads/' . $folder . '/' . $safeName . '.' . $targetExtension;

    if ($canWebp && optimizeImageToWebp($tmpName, $targetPath, $mime, $imageInfo, $maxWidth)) {
        return [$relativePath, null];
    }

    $fallbackPath = $targetDirectory . DIRECTORY_SEPARATOR . $safeName . '.' . $allowedMime[$mime];
    $fallbackRelative = 'uploads/' . $folder . '/' . $safeName . '.' . $allowedMime[$mime];

    if (is_uploaded_file($tmpName)) {
        $saved = move_uploaded_file($tmpName, $fallbackPath);
    } else {
        $saved = rename($tmpName, $fallbackPath);
    }

    if (!$saved) {
        return [null, 'Не удалось сохранить изображение на сервере.'];
    }

    return [$fallbackRelative, null];
}

function optimizeImageToWebp(string $sourcePath, string $targetPath, string $mime, array $imageInfo, int $maxWidth): bool
{
    $source = match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
        default => false,
    };

    if (!$source) {
        return false;
    }

    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];
    $targetWidth = min($width, $maxWidth);
    $targetHeight = $width > $maxWidth ? (int) round($height * ($targetWidth / $width)) : $height;

    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

    $saved = imagewebp($canvas, $targetPath, 82);
    imagedestroy($source);
    imagedestroy($canvas);

    return $saved;
}
