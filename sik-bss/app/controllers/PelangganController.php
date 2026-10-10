<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Flash, HttpException};

class PelangganController extends Controller
{
    public function index(): void
    {
        $q = (string)$this->input('q');
        $status = (string)$this->input('status');
        $w = ['1=1']; $p = [];
        if ($q !== '') { $w[] = '(kode_pelanggan LIKE ? OR nama_pelanggan LIKE ? OR telepon LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
        if (in_array($status, ['aktif', 'nonaktif'], true)) { $w[] = 'status = ?'; $p[] = $status; }
        $pg = Database::paged('SELECT *', 'FROM pelanggan WHERE ' . implode(' AND ', $w), $p, 'kode_pelanggan', $this->page(), $this->per());
        $this->view('pelanggan/index', ['title' => 'Data Pelanggan', 'pg' => $pg, 'q' => $q, 'status' => $status]);
    }

    private function find(string $id): array
    {
        $r = Database::one('SELECT * FROM pelanggan WHERE id_pelanggan = ?', [(int)$id]);
        if (!$r) throw new HttpException(404, 'Pelanggan tidak ditemukan.');
        return $r;
    }

    private function data(?array $ex): array
    {
        $d = [
            'kode_pelanggan' => (string)$this->input('kode_pelanggan'),
            'nama_pelanggan' => (string)$this->input('nama_pelanggan'),
            'alamat' => (string)$this->input('alamat'),
            'telepon' => (string)$this->input('telepon'),
            'email' => (string)$this->input('email'),
        ];
        if ($d['kode_pelanggan'] === '' || $d['nama_pelanggan'] === '') $this->fail('Kode dan nama pelanggan harus diisi.', '/pelanggan');
        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $this->fail('Format email tidak valid.', '/pelanggan');
        if (mb_strlen($d['kode_pelanggan']) > 20 || mb_strlen($d['nama_pelanggan']) > 100 || mb_strlen($d['alamat']) > 255
            || mb_strlen($d['telepon']) > 20 || mb_strlen($d['email']) > 50) $this->fail('Isian terlalu panjang.', '/pelanggan');
        $dup = Database::val('SELECT id_pelanggan FROM pelanggan WHERE kode_pelanggan = ?' . ($ex ? ' AND id_pelanggan <> ?' : ''),
            $ex ? [$d['kode_pelanggan'], $ex['id_pelanggan']] : [$d['kode_pelanggan']]);
        if ($dup) $this->fail('Kode pelanggan sudah digunakan.', '/pelanggan');
        return $d;
    }

    public function store(): void
    {
        Database::insert('pelanggan', $this->data(null));
        Flash::set('success', 'Pelanggan berhasil disimpan.');
        redirect('/pelanggan');
    }

    public function update(string $id): void
    {
        $p = $this->find($id);
        Database::update('pelanggan', $this->data($p), 'id_pelanggan', $p['id_pelanggan']);
        Flash::set('success', 'Pelanggan berhasil diubah.');
        redirect('/pelanggan');
    }

    public function status(string $id): void
    {
        $p = $this->find($id);
        $baru = $p['status'] === 'aktif' ? 'nonaktif' : 'aktif';
        Database::update('pelanggan', ['status' => $baru], 'id_pelanggan', $p['id_pelanggan']);
        Flash::set('success', 'Status pelanggan diperbarui menjadi ' . $baru . '.');
        redirect('/pelanggan');
    }
}
