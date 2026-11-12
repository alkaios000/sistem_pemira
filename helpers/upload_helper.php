<?php
/**
 * Pustaka Validasi & Penanganan Unggahan Berkas (Upload Helper)
 */

/**
 * Mengunggah foto kandidat dengan validasi ketat terhadap MIME type, ekstensi, dan ukuran berkas
 */
function upload_foto_kandidat(array $file, string $sub_folder = 'foto_kandidat', int $max_size_bytes = 2097152): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['status' => false, 'message' => 'Parameter berkas unggahan tidak valid.', 'filename' => null];
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['status' => true, 'message' => 'Tidak ada berkas baru yang diunggah.', 'filename' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'Terjadi kegagalan saat mengunggah berkas (Kode: ' . $file['error'] . ').', 'filename' => null];
    }

    if ($file['size'] > $max_size_bytes) {
        return ['status' => false, 'message' => 'Ukuran foto melebihi batas maksimal 2 MB.', 'filename' => null];
    }

    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);

    if (!isset($allowed_mimes[$mime_type])) {
        return ['status' => false, 'message' => 'Format foto harus berupa JPG, PNG, atau WEBP.', 'filename' => null];
    }

    $extension = $allowed_mimes[$mime_type];
    $target_dir = BASE_PATH . '/uploads/' . trim($sub_folder, '/');

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $safe_filename = 'kandidat_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $destination = $target_dir . '/' . $safe_filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['status' => false, 'message' => 'Gagal menyimpan berkas foto ke direktori server.', 'filename' => null];
    }

    return [
        'status'   => true,
        'message'  => 'Foto berhasil diunggah.',
        'filename' => $sub_folder . '/' . $safe_filename,
    ];
}

/**
 * Menghapus berkas foto lama dari folder uploads secara aman
 */
function delete_uploaded_file(?string $relative_path): void
{
    if (empty($relative_path)) {
        return;
    }

    $clean_path = str_replace(['..', '\\'], ['', '/'], $relative_path);
    $full_path = BASE_PATH . '/uploads/' . ltrim($clean_path, '/');

    if (is_file($full_path) && file_exists($full_path)) {
        @unlink($full_path);
    }
}

