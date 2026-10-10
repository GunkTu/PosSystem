<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Flash, HttpException};
use App\Services\JurnalService;

class KasBankController extends Controller
{
    public function index(): void
    {
        $q = (string)$this->input('q');
        $status = (string)$this->input('status');
        $w = ['1=1']; $p = [];
        if ($q !== '') { $w[] = '(k.kode_kas_bank LIKE ? OR k.nama_kas_bank LIKE ? OR k.no_rekening LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
        if (in_array($status, ['aktif', 'nonaktif'], true)) { $w[] = 'k.status = ?'; $p[] = $status; }
        $pg = Database::paged(
            'SELECT k.*, a.nama_akun, (SELECT COUNT(*) FROM transaksi_pemasukan t WHERE t.id_kas_bank=k.id_kas_bank) + (SELECT COUNT(*) FROM transaksi_pengeluaran t2 WHERE t2.id_kas_bank=k.id_kas_bank) AS mutasi',
            'FROM kas_bank k JOIN akun a ON a.id_akun = k.id_akun WHERE ' . implode(' AND ', $w), $p, 'k.kode_kas_bank', $this->page(), $this->per());
        $akun = Database::all("SELECT id_akun, kode_akun, nama_akun FROM akun WHERE tipe_akun='aset' AND status='aktif' ORDER BY kode_akun");
        $this->view('kas_bank/index', ['title' => 'Kas dan Bank', 'pg' => $pg, 'q' => $q, 'status' => $status, 'akun' => $akun]);
    }

    private function find(string $id): array
    {
        $r = Database::one('SELECT k.*, (SELECT COUNT(*) FROM transaksi_pemasukan t WHERE t.id_kas_bank=k.id_kas_bank) + (SELECT COUNT(*) FROM transaksi_pengeluaran t2 WHERE t2.id_kas_bank=k.id_kas_bank) AS mutasi FROM kas_bank k WHERE id_kas_bank = ?', [(int)$id]);
        if (!$r) throw new HttpException(404, 'Kas/Bank tidak ditemukan.');
        return $r;
    }

    private function data(?array $ex): array
    {
        $d = [
            'id_akun' => (int)$this->input('id_akun'),
            'kode_kas_bank' => (string)$this->input('kode_kas_bank'),
            'nama_kas_bank' => (string)$this->input('nama_kas_bank'),
            'jenis_kas_bank' => (string)$this->input('jenis_kas_bank'),
            'no_rekening' => (string)$this->input('no_rekening'),
        ];
        $saldoAwal = dec($this->input('saldo_awal', 0));
        $tglSaldo = (string)$this->input('tanggal_saldo', date('Y-m-d'));
        if ($ex && $ex['mutasi'] > 0) {   // sudah ada mutasi: akun & saldo awal dikunci
            $d['id_akun'] = (int)$ex['id_akun']; $saldoAwal = (float)$ex['saldo_awal'];
        }
        if ($d['kode_kas_bank'] === '' || $d['nama_kas_bank'] === '') $this->fail('Kode dan nama harus diisi.', '/kas-bank');
        if (!in_array($d['jenis_kas_bank'], ['kas', 'bank'], true)) $this->fail('Jenis harus Kas atau Bank.', '/kas-bank');
        if (mb_strlen($d['kode_kas_bank']) > 20 || mb_strlen($d['nama_kas_bank']) > 100 || mb_strlen($d['no_rekening']) > 30) $this->fail('Isian terlalu panjang.', '/kas-bank');
        if ($saldoAwal < 0) $this->fail('Saldo awal tidak boleh negatif.', '/kas-bank');
        if ($saldoAwal > 0 && !valid_date($tglSaldo)) $this->fail('Tanggal saldo awal tidak valid.', '/kas-bank');
        if (!Database::val("SELECT id_akun FROM akun WHERE id_akun=? AND tipe_akun='aset' AND status='aktif'", [$d['id_akun']])) {
            $this->fail('Pilih akun aset yang aktif.', '/kas-bank');
        }
        $dup = Database::val('SELECT id_kas_bank FROM kas_bank WHERE kode_kas_bank = ?' . ($ex ? ' AND id_kas_bank <> ?' : ''),
            $ex ? [$d['kode_kas_bank'], $ex['id_kas_bank']] : [$d['kode_kas_bank']]);
        if ($dup) $this->fail('Kode kas/bank sudah digunakan.', '/kas-bank');
        return [$d, $saldoAwal, $tglSaldo];
    }

    /** Buat ulang jurnal saldo awal: Debit kas/bank, Kredit Modal Pemilik. */
    private function jurnalSaldoAwal(int $idKb, int $idAkun, string $nama, float $saldo, string $tgl): void
    {
        JurnalService::batalkan('saldo_awal', $idKb);
        if ($saldo <= 0) return;
        $modal = Database::val("SELECT id_akun FROM akun WHERE kode_akun='3-101'");
        if (!$modal) throw new \RuntimeException('Akun Modal Pemilik (3-101) tidak ditemukan.');
        JurnalService::buat($tgl, 'SALDO-' . $idKb, 'Saldo awal ' . $nama, 'saldo_awal', $idKb, [
            ['akun' => $idAkun, 'debit' => $saldo], ['akun' => (int)$modal, 'kredit' => $saldo],
        ]);
    }

    public function store(): void
    {
        [$d, $saldo, $tgl] = $this->data(null);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $id = Database::insert('kas_bank', $d + ['saldo_awal' => $saldo, 'saldo' => $saldo]);
            $this->jurnalSaldoAwal($id, $d['id_akun'], $d['nama_kas_bank'], $saldo, $tgl);
            $pdo->commit();
        } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
        Flash::set('success', 'Kas/Bank berhasil disimpan.');
        redirect('/kas-bank');
    }

    public function update(string $id): void
    {
        $k = $this->find($id);
        [$d, $saldo, $tgl] = $this->data($k);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $upd = $d;
            if ($k['mutasi'] == 0) {
                $upd += ['saldo_awal' => $saldo, 'saldo' => $saldo];
            }
            Database::update('kas_bank', $upd, 'id_kas_bank', $k['id_kas_bank']);
            if ($k['mutasi'] == 0) $this->jurnalSaldoAwal((int)$k['id_kas_bank'], $d['id_akun'], $d['nama_kas_bank'], $saldo, $tgl);
            $pdo->commit();
        } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
        Flash::set('success', 'Kas/Bank berhasil diubah.');
        redirect('/kas-bank');
    }

    public function status(string $id): void
    {
        $k = $this->find($id);
        $baru = $k['status'] === 'aktif' ? 'nonaktif' : 'aktif';
        Database::update('kas_bank', ['status' => $baru], 'id_kas_bank', $k['id_kas_bank']);
        Flash::set('success', 'Status kas/bank diperbarui menjadi ' . $baru . '.');
        redirect('/kas-bank');
    }

    public function mutasi(string $id): void
    {
        $k = $this->find($id);
        $rows = Database::all(
            "SELECT tanggal, no_bukti, keterangan, masuk, keluar, urut FROM (
               SELECT j.tanggal, 'Saldo awal' AS no_bukti, j.keterangan, k.saldo_awal AS masuk, 0 AS keluar, 0 AS urut
                 FROM jurnal j JOIN kas_bank k ON k.id_kas_bank = j.id_sumber WHERE j.sumber='saldo_awal' AND j.status='aktif' AND j.id_sumber = :id
               UNION ALL
               SELECT tanggal_pembayaran, no_bukti_pemasukan, keterangan_pembayaran, nominal_pembayaran, 0, id_pemasukan FROM transaksi_pemasukan WHERE id_kas_bank = :id2 AND status='aktif'
               UNION ALL
               SELECT tanggal_pengeluaran, no_bukti_pengeluaran, keterangan_pengeluaran, 0, nominal_pengeluaran, id_pengeluaran FROM transaksi_pengeluaran WHERE id_kas_bank = :id3 AND status='aktif'
             ) m ORDER BY tanggal, no_bukti",
            ['id' => $k['id_kas_bank'], 'id2' => $k['id_kas_bank'], 'id3' => $k['id_kas_bank']]);
        $this->view('kas_bank/mutasi', ['title' => 'Mutasi ' . $k['nama_kas_bank'], 'k' => $k, 'rows' => $rows]);
    }
}
