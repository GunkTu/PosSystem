<?php
namespace App\Controllers;

use App\Core\{Controller, Auth, Database, Flash, HttpException};
use App\Services\{TransaksiService, LaporanService};

class PemasukanController extends Controller
{
    private function filter(): array
    {
        $q = (string)$this->input('q');
        $dari = (string)$this->input('dari');
        $sampai = (string)$this->input('sampai');
        $status = (string)$this->input('status');
        $w = ['1=1']; $p = [];
        if ($q !== '') {
            $w[] = '(t.no_bukti_pemasukan LIKE ? OR i.no_invoice LIKE ? OR a.nama_akun LIKE ? OR t.keterangan_pembayaran LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        if (valid_date($dari)) { $w[] = 't.tanggal_pembayaran >= ?'; $p[] = $dari; }
        if (valid_date($sampai)) { $w[] = 't.tanggal_pembayaran <= ?'; $p[] = $sampai; }
        if (in_array($status, ['aktif', 'batal'], true)) { $w[] = 't.status = ?'; $p[] = $status; }
        return [$w, $p, compact('q', 'dari', 'sampai', 'status')];
    }

    private const FROM = 'FROM transaksi_pemasukan t JOIN kas_bank k ON k.id_kas_bank = t.id_kas_bank
                          JOIN akun a ON a.id_akun = t.id_akun LEFT JOIN invoice i ON i.id_invoice = t.id_invoice';

    public function index(): void
    {
        [$w, $p, $f] = $this->filter();
        $pg = Database::paged('SELECT t.*, k.nama_kas_bank, a.nama_akun, i.no_invoice', self::FROM . ' WHERE ' . implode(' AND ', $w), $p,
            't.tanggal_pembayaran DESC, t.id_pemasukan DESC', $this->page(), $this->per());
        $total = (float)Database::val('SELECT COALESCE(SUM(t.nominal_pembayaran),0) ' . self::FROM . ' WHERE ' . implode(' AND ', $w) . " AND t.status='aktif'", $p);
        $this->view('pemasukan/index', ['title' => 'Transaksi Pemasukan', 'pg' => $pg, 'total' => $total] + $f);
    }

    private function formRefs(?int $selfId = null): array
    {
        $inv = Database::all(
            "SELECT i.id_invoice, i.no_invoice, i.total, p.nama_pelanggan,
                    i.total - COALESCE((SELECT SUM(t.nominal_pembayaran) FROM transaksi_pemasukan t WHERE t.id_invoice = i.id_invoice AND t.status='aktif' AND t.id_pemasukan <> ?),0) AS sisa
               FROM invoice i JOIN pelanggan p ON p.id_pelanggan = i.id_pelanggan
              WHERE i.status_invoice IN ('belum_lunas','sebagian') OR i.id_invoice = (SELECT id_invoice FROM transaksi_pemasukan WHERE id_pemasukan = ?)
              ORDER BY i.tanggal DESC", [$selfId ?? 0, $selfId ?? 0]);
        return [
            'invoices' => $inv,
            'kasBank' => Database::all("SELECT id_kas_bank, kode_kas_bank, nama_kas_bank, saldo FROM kas_bank WHERE status='aktif' ORDER BY kode_kas_bank"),
            'akunPendapatan' => Database::all("SELECT id_akun, kode_akun, nama_akun FROM akun WHERE tipe_akun='pendapatan' AND status='aktif' ORDER BY kode_akun"),
            'akunModal' => Database::all("SELECT id_akun, kode_akun, nama_akun FROM akun WHERE tipe_akun='modal' AND saldo_normal='kredit' AND status='aktif' ORDER BY kode_akun"),
        ];
    }

    public function create(): void
    {
        $this->view('pemasukan/form', $this->formRefs() + [
            'title' => 'Catat Pemasukan', 'row' => null, 'action' => url('/pemasukan/store'),
            'preInvoice' => (int)($_GET['invoice'] ?? 0),
        ]);
    }

    private function data(): array
    {
        return [
            'id_invoice' => (int)$this->input('id_invoice') ?: null,
            'id_kas_bank' => (int)$this->input('id_kas_bank'),
            'id_akun' => (int)$this->input('id_akun'),
            'tanggal' => (string)$this->input('tanggal'),
            'keterangan' => (string)$this->input('keterangan'),
            'nominal' => dec($this->input('nominal', 0)),
        ];
    }

    public function store(): void
    {
        try {
            $id = TransaksiService::simpanPemasukan($this->data(), Auth::id());
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), '/pemasukan/create' . (!empty($_POST['id_invoice']) ? '?invoice=' . (int)$_POST['id_invoice'] : ''));
        }
        Flash::set('success', 'Pemasukan berhasil disimpan. Saldo dan status invoice diperbarui.');
        redirect(!empty($_POST['id_invoice']) ? '/invoice/' . (int)$_POST['id_invoice'] : '/pemasukan');
    }

    private function find(string $id): array
    {
        $r = Database::one('SELECT * FROM transaksi_pemasukan WHERE id_pemasukan = ?', [(int)$id]);
        if (!$r) throw new HttpException(404, 'Transaksi tidak ditemukan.');
        return $r;
    }

    public function edit(string $id): void
    {
        $r = $this->find($id);
        if ($r['status'] !== 'aktif') {
            Flash::set('warning', 'Transaksi yang dibatalkan tidak dapat diubah.');
            redirect('/pemasukan');
        }
        $this->view('pemasukan/form', $this->formRefs((int)$r['id_pemasukan']) + [
            'title' => 'Ubah Pemasukan ' . $r['no_bukti_pemasukan'], 'row' => $r,
            'action' => url('/pemasukan/update/' . $r['id_pemasukan']), 'preInvoice' => (int)$r['id_invoice'],
        ]);
    }

    public function update(string $id): void
    {
        $r = $this->find($id);
        try {
            TransaksiService::ubahPemasukan((int)$r['id_pemasukan'], $this->data());
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), '/pemasukan/' . $r['id_pemasukan'] . '/edit');
        }
        Flash::set('success', 'Pemasukan berhasil diubah.');
        redirect('/pemasukan');
    }

    public function batal(string $id): void
    {
        $r = $this->find($id);
        try {
            TransaksiService::batalPemasukan((int)$r['id_pemasukan']);
            Flash::set('success', 'Pemasukan dibatalkan. Saldo dan status invoice dikoreksi.');
        } catch (\DomainException $e) {
            Flash::set('danger', $e->getMessage());
        }
        redirect('/pemasukan');
    }

    public function cetak(): void
    {
        [$w, $p, $f] = $this->filter();
        $rows = Database::all('SELECT t.*, k.nama_kas_bank, a.nama_akun, i.no_invoice ' . self::FROM . ' WHERE ' . implode(' AND ', $w) . " AND t.status='aktif' ORDER BY t.tanggal_pembayaran, t.id_pemasukan", $p);
        LaporanService::cetakDaftar('Laporan Pemasukan', $f, $rows, 'pemasukan', Auth::id());
    }
}
