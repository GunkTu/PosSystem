<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Flash, HttpException};

class AkunController extends Controller
{
    private const TIPE = ['aset', 'kewajiban', 'modal', 'pendapatan', 'beban'];

    public function index(): void
    {
        $q = (string)$this->input('q');
        $status = (string)$this->input('status');
        $tipe = (string)$this->input('tipe');
        $w = ['1=1']; $p = [];
        if ($q !== '') { $w[] = '(kode_akun LIKE ? OR nama_akun LIKE ?)'; $p[] = "%$q%"; $p[] = "%$q%"; }
        if (in_array($status, ['aktif', 'nonaktif'], true)) { $w[] = 'status = ?'; $p[] = $status; }
        if (in_array($tipe, self::TIPE, true)) { $w[] = 'tipe_akun = ?'; $p[] = $tipe; }
        $pg = Database::paged('SELECT *, (SELECT COUNT(*) FROM jurnal_detail jd WHERE jd.id_akun = akun.id_akun) AS dipakai',
            'FROM akun WHERE ' . implode(' AND ', $w), $p, 'kode_akun', $this->page(), $this->per());
        $this->view('akun/index', ['title' => 'Data Akun', 'pg' => $pg, 'q' => $q, 'status' => $status, 'tipe' => $tipe, 'tipeList' => self::TIPE]);
    }

    private function data(?array $existing): array
    {
        $d = [
            'kode_akun' => (string)$this->input('kode_akun'),
            'nama_akun' => (string)$this->input('nama_akun'),
            'tipe_akun' => (string)$this->input('tipe_akun'),
            'kategori_akun' => (string)$this->input('kategori_akun'),
            'saldo_normal' => (string)$this->input('saldo_normal'),
        ];
        if ($existing && $existing['dipakai'] > 0) {   // akun yang sudah dipakai: kode/tipe/saldo normal dikunci
            $d['kode_akun'] = $existing['kode_akun'];
            $d['tipe_akun'] = $existing['tipe_akun'];
            $d['saldo_normal'] = $existing['saldo_normal'];
        }
        if ($d['kode_akun'] === '' || $d['nama_akun'] === '' || $d['tipe_akun'] === '') {
            $this->fail('Kode, nama, dan tipe akun harus diisi.', '/akun');
        }
        if (!in_array($d['tipe_akun'], self::TIPE, true)) $this->fail('Tipe akun tidak valid.', '/akun');
        if (!in_array($d['saldo_normal'], ['debit', 'kredit'], true)) {
            $d['saldo_normal'] = in_array($d['tipe_akun'], ['aset', 'beban'], true) ? 'debit' : 'kredit';
        }
        if (mb_strlen($d['kode_akun']) > 20 || mb_strlen($d['nama_akun']) > 100 || mb_strlen($d['kategori_akun']) > 30) {
            $this->fail('Isian terlalu panjang.', '/akun');
        }
        $dup = Database::val('SELECT id_akun FROM akun WHERE kode_akun = ?' . ($existing ? ' AND id_akun <> ?' : ''),
            $existing ? [$d['kode_akun'], $existing['id_akun']] : [$d['kode_akun']]);
        if ($dup) $this->fail('Kode akun sudah digunakan.', '/akun');
        return $d;
    }

    public function store(): void
    {
        Database::insert('akun', $this->data(null));
        Flash::set('success', 'Akun berhasil disimpan.');
        redirect('/akun');
    }

    private function find(string $id): array
    {
        $r = Database::one('SELECT *, (SELECT COUNT(*) FROM jurnal_detail jd WHERE jd.id_akun = akun.id_akun) AS dipakai FROM akun WHERE id_akun = ?', [(int)$id]);
        if (!$r) throw new HttpException(404, 'Akun tidak ditemukan.');
        return $r;
    }

    public function update(string $id): void
    {
        $a = $this->find($id);
        Database::update('akun', $this->data($a), 'id_akun', $a['id_akun']);
        Flash::set('success', 'Akun berhasil diubah.');
        redirect('/akun');
    }

    public function status(string $id): void
    {
        $a = $this->find($id);
        $baru = $a['status'] === 'aktif' ? 'nonaktif' : 'aktif';
        if ($baru === 'nonaktif' && Database::val("SELECT COUNT(*) FROM kas_bank WHERE id_akun = ? AND status = 'aktif'", [$a['id_akun']])) {
            $this->fail('Akun masih dipakai oleh kas/bank aktif. Nonaktifkan kas/bank terlebih dahulu.', '/akun');
        }
        Database::update('akun', ['status' => $baru], 'id_akun', $a['id_akun']);
        Flash::set('success', 'Status akun diperbarui menjadi ' . $baru . '.');
        redirect('/akun');
    }
}
