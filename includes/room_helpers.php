<?php

declare(strict_types=1);

const ROOM_TYPES = [
    'Classroom', 'Computer Lab', 'Science Lab', 'Seminar Hall',
    'Auditorium', 'Meeting Room', 'Staff Room', 'Other',
];

const ROOM_STATUSES = ['Active', 'Under Maintenance', 'Inactive', 'Reserved'];
const ROOM_AMENITIES = [
    'Projector', 'Smart Board', 'Whiteboard', 'AC', 'Wi-Fi',
    'Computers', 'Sound System', 'Stage',
];
const ROOM_ACCESSIBILITY = ['Wheelchair Accessible', 'Elevator Access', 'Special Seating'];

function roomJsonValues(array $values, array $allowed): string
{
    $values = array_values(array_unique(array_intersect($values, $allowed)));
    return json_encode($values, JSON_UNESCAPED_SLASHES);
}

function roomDecodeValues(?string $json): array
{
    $values = json_decode((string) $json, true);
    return is_array($values) ? array_values(array_filter($values, 'is_string')) : [];
}

function roomUploadImages(array $files, string $roomCode, int $maxFiles, string $subdirectory): array
{
    $stored = [];
    $uploadRoot = dirname(__DIR__) . '/uploads/rooms/' . $subdirectory;
    if (!is_dir($uploadRoot)) {
        mkdir($uploadRoot, 0755, true);
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $count = isset($files['name']) && is_array($files['name']) ? count($files['name']) : 0;
    if ($count > $maxFiles) {
        throw new RuntimeException("You may upload at most {$maxFiles} images.");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (($files['error'][$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($files['size'][$i] ?? 0) > 5 * 1024 * 1024) {
            throw new RuntimeException('Each image must be smaller than 5 MB.');
        }
        $mime = $finfo->file($files['tmp_name'][$i]);
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Only JPG, PNG, and WebP images are allowed.');
        }
        $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($files['tmp_name'][$i], $uploadRoot . '/' . $filename)) {
            throw new RuntimeException('Unable to store an uploaded image.');
        }
        $stored[] = 'uploads/rooms/' . $subdirectory . '/' . $filename;
    }
    return $stored;
}

function roomUploadImage(?array $file, string $roomCode, string $subdirectory): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $stored = roomUploadImages([
        'name' => [$file['name']], 'type' => [$file['type']], 'tmp_name' => [$file['tmp_name']],
        'error' => [$file['error']], 'size' => [$file['size']],
    ], $roomCode, 1, $subdirectory);
    return $stored[0] ?? null;
}
