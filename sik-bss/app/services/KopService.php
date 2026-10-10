<?php
namespace App\Services;

use App\Core\Database;

class KopService
{
    public static function get(): array
    {
        $r = Database::one('SELECT * FROM pengaturan_perusahaan WHERE id = 1');
        if (!$r) {
            Database::exec('INSERT INTO pengaturan_perusahaan (id) VALUES (1)');
            $r = Database::one('SELECT * FROM pengaturan_perusahaan WHERE id = 1');
        }
        return $r;
    }

    public static function dataUri(?string $file): ?string
    {
        if (!$file) return null;
        $path = STORAGE_PATH . '/kop/' . basename($file);
        if (!is_file($path)) return null;
        $mime = mime_content_type($path);
        return 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($path));
    }

    /** Data kop siap pakai untuk partial kop (placeholder bila belum diisi). */
    public static function siap(): array
    {
        $k = self::get();
        $gambar = $k['mode_kop'] === 'gambar' ? self::dataUri($k['header_image_path']) : null;
        return $k + [
            'gambar_uri' => $gambar,
            'logo_uri' => self::dataUri($k['logo_path']),
            'pakai_gambar' => $gambar !== null,
        ];
    }
}
