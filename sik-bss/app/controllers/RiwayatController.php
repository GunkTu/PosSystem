<?php
namespace App\Controllers;

use App\Core\{Controller, Database, HttpException};
use App\Services\LaporanService;

class RiwayatController extends Controller
{
    public function index(): void
    {
        $jenis = (string)$this->input('jenis');
        $w = ['1=1']; $p = [];
        if ($jenis !== '') { $w[] = 'r.jenis_laporan LIKE ?'; $p[] = "%$jenis%"; }
        $pg = Database::paged('SELECT r.*, u.nama', 'FROM riwayat_laporan r JOIN pengguna u ON u.id_user = r.id_user WHERE ' . implode(' AND ', $w),
            $p, 'r.created_at DESC, r.id_laporan DESC', $this->page(), $this->per());
        $this->view('riwayat/index', ['title' => 'Riwayat Laporan', 'pg' => $pg, 'jenis' => $jenis]);
    }

    private function kirim(string $id, bool $unduh): never
    {
        $r = Database::one('SELECT * FROM riwayat_laporan WHERE id_laporan = ?', [(int)$id]);
        $path = $r ? STORAGE_PATH . '/laporan/' . basename((string)$r['file_laporan']) : '';
        if (!$r || !is_file($path)) throw new HttpException(404, 'File laporan tidak ditemukan.');
        $pdf = $r['format'] === 'pdf';
        header('Content-Type: ' . ($pdf ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
        header('Content-Disposition: ' . (($unduh || !$pdf) ? 'attachment' : 'inline') . '; filename="' . LaporanService::namaUnduh($r) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function buka(string $id): void { $this->kirim($id, false); }
    public function unduh(string $id): void { $this->kirim($id, true); }
}
