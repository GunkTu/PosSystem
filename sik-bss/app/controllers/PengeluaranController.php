<?php
namespace App\Controllers;

use App\Core\{Controller, Auth, Database, Flash, HttpException};
use App\Services\{TransaksiService, LaporanService};

class PengeluaranController extends Controller
{
    private const FROM = 'FROM transaksi_pengeluaran t JOIN kas_bank k ON k.id_kas_bank = t.id_kas_bank JOIN akun a ON a.id_akun = t.id_akun';

    private function filter(): array
    {
        $q = (string)$this->input('q');
        $dari = (string)$this->input('dari');
        $sampai = (string)$this->input('sampai');
        $status = (string)$this->input('status');
        $w = ['1=1']; $p = [];
        if ($q !== '') {
            $w[] = '(t.no_bukti_pengeluaran LIKE ? OR a.nama_akun LIKE ? OR t.keterangan_pengeluaran LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%");
        }
        if (valid_date($dari)) { $w[] = 't.tanggal_pengeluaran >= ?'; $p[] = $dari; }
        if (valid_date($sampai)) { $w[] = 't.tanggal_pengeluaran <= ?'; $p[] = $sampai; }
        if (in_array($status, ['aktif', 'batal'], true)) { $w[] = 't.status = ?'; $p[] = $status; }
        return [$w, $p, compact('q', 'dari', 'sampai', 'status')];
    }

    public function index(): void
    {
        [$w, $p, $f] = $this->filter();
        $pg = Database::paged('SELECT t.*, k.nama_kas_bank, a.nama_akun', self::FROM . ' WHERE ' . implode(' AND ', $w), $p,
            't.tanggal_pengeluaran DESC, t.id_pengeluaran DESC', $this->page(), $this->per());
        $total = (float)Database::val('SELECT COALESCE(SUM(t.nominal_pengeluaran),0) ' . self::FROM . ' WHERE ' . implode(' AND ', $w) . " AND t.status='aktif'", $p);
        $this->view('pengeluaran/index', ['title' => 'Transaksi Pengeluaran', 'pg' => $pg, 'total' => $total] + $f);
    }

    private function refs(): array
    {
        return [
            'kasBank' => Database::all("SELECT id_kas_bank, kode_kas_bank, nama_kas_bank, saldo FROM kas_bank WHERE status='aktif' ORDER BY kode_kas_bank"),
            'akunBeban' => Database::all("SELECT id_akun, kode_akun, nama_akun, tipe_akun FROM akun WHERE status='aktif' AND (tipe_akun='beban' OR (tipe_akun='modal' AND saldo_normal='debit')) ORDER BY kode_akun"),
        ];
    }

    public function create(): void
    {
        $this->view('pengeluaran/form', $this->refs() + ['title' => 'Catat Pengeluaran', 'row' => null, 'action' => url('/pengeluaran/store')]);
    }

    /** @return ?string nama file tersimpan */
    private function unggah(): ?string
    {
        $f = $_FILES['file_bukti'] ?? null;
        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) return null;
        if ($f['error'] !== UPLOAD_ERR_OK) throw new \DomainException('Unggah bukti gagal.');
        if ($f['size'] > 2 * 1024 * 1024) throw new \DomainException('Ukuran bukti maksimal 2 MB.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$mime] ?? null;
        if (!$ext) throw new \DomainException('Bukti harus berupa JPG, PNG, atau PDF.');
        $nama = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], STORAGE_PATH . '/bukti/' . $nama)) throw new \DomainException('Gagal menyimpan bukti.');
        return $nama;
    }

    private function data(): array
    {
        return [
            'id_kas_bank' => (int)$this->input('id_kas_bank'),
            'id_akun' => (int)$this->input('id_akun'),
            'tanggal' => (string)$this->input('tanggal'),
            'keterangan' => (string)$this->input('keterangan'),
            'nominal' => dec($this->input('nominal', 0)),
        ];
    }

    public function store(): void
    {
        $file = null;
        try {
            $d = $this->data();
            $d['file_bukti'] = $file = $this->unggah();
            TransaksiService::simpanPengeluaran($d, Auth::id());
        } catch (\DomainException $e) {
            if ($file) @unlink(STORAGE_PATH . '/bukti/' . $file);
            $this->fail($e->getMessage(), '/pengeluaran/create');
        }
        Flash::set('success', 'Pengeluaran berhasil disimpan. Saldo diperbarui.');
        redirect('/pengeluaran');
    }

    private function find(string $id): array
    {
        $r = Database::one('SELECT * FROM transaksi_pengeluaran WHERE id_pengeluaran = ?', [(int)$id]);
        if (!$r) throw new HttpException(404, 'Transaksi tidak ditemukan.');
        return $r;
    }

    public function edit(string $id): void
    {
        $r = $this->find($id);
        if ($r['status'] !== 'aktif') {
            Flash::set('warning', 'Transaksi yang dibatalkan tidak dapat diubah.');
            redirect('/pengeluaran');
        }
        $this->view('pengeluaran/form', $this->refs() + ['title' => 'Ubah Pengeluaran ' . $r['no_bukti_pengeluaran'], 'row' => $r, 'action' => url('/pengeluaran/update/' . $r['id_pengeluaran'])]);
    }

    public function update(string $id): void
    {
        $r = $this->find($id);
        $file = null;
        try {
            $d = $this->data();
            $d['file_bukti'] = $file = $this->unggah();
            TransaksiService::ubahPengeluaran((int)$r['id_pengeluaran'], $d);
        } catch (\DomainException $e) {
            if ($file) @unlink(STORAGE_PATH . '/bukti/' . $file);
            $this->fail($e->getMessage(), '/pengeluaran/' . $r['id_pengeluaran'] . '/edit');
        }
        if ($file && $r['file_bukti']) @unlink(STORAGE_PATH . '/bukti/' . basename($r['file_bukti']));
        Flash::set('success', 'Pengeluaran berhasil diubah.');
        redirect('/pengeluaran');
    }

    public function batal(string $id): void
    {
        $r = $this->find($id);
        try {
            TransaksiService::batalPengeluaran((int)$r['id_pengeluaran']);
            Flash::set('success', 'Pengeluaran dibatalkan. Saldo dikoreksi.');
        } catch (\DomainException $e) {
            Flash::set('danger', $e->getMessage());
        }
        redirect('/pengeluaran');
    }

    public function bukti(string $id): void
    {
        $r = $this->find($id);
        $path = STORAGE_PATH . '/bukti/' . basename((string)$r['file_bukti']);
        if (!$r['file_bukti'] || !is_file($path)) throw new HttpException(404, 'Bukti tidak ada.');
        header('Content-Type: ' . mime_content_type($path));
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function cetak(): void
    {
        [$w, $p, $f] = $this->filter();
        $rows = Database::all('SELECT t.*, k.nama_kas_bank, a.nama_akun ' . self::FROM . ' WHERE ' . implode(' AND ', $w) . " AND t.status='aktif' ORDER BY t.tanggal_pengeluaran, t.id_pengeluaran", $p);
        LaporanService::cetakDaftar('Laporan Pengeluaran', $f, $rows, 'pengeluaran', Auth::id());
    }
}
