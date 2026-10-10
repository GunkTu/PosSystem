<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Flash, HttpException};
use App\Services\KopService;

class PengaturanController extends Controller
{
    public function kop(): void
    {
        $this->view('pengaturan/kop', ['title' => 'Pengaturan Kop Perusahaan', 'k' => KopService::get(), 'kop' => KopService::siap()]);
    }

    /** @return ?string nama file baru, null bila tidak ada unggahan */
    private function unggahGambar(string $field, int $minLebar): ?string
    {
        $f = $_FILES[$field] ?? null;
        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) return null;
        if ($f['error'] !== UPLOAD_ERR_OK) throw new \DomainException('Unggah gambar gagal.');
        if ($f['size'] > 2 * 1024 * 1024) throw new \DomainException('Ukuran gambar maksimal 2 MB.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
        if (!$ext || !@getimagesize($f['tmp_name'])) throw new \DomainException('Gambar harus berformat JPG atau PNG. Jika kop berupa PDF, konversi dulu ke JPG/PNG.');
        $nama = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], STORAGE_PATH . '/kop/' . $nama)) throw new \DomainException('Gagal menyimpan gambar.');
        return $nama;
    }

    public function simpan(): void
    {
        $k = KopService::get();
        $d = [];
        $max = ['nama_perusahaan' => 100, 'alamat' => 255, 'telepon' => 30, 'email' => 50, 'npwp' => 30, 'bank_nama' => 50,
                'bank_no_rekening' => 30, 'bank_atas_nama' => 100, 'ttd_nama' => 100, 'ttd_jabatan' => 50, 'catatan_invoice' => 255];
        foreach ($max as $f => $len) {
            $d[$f] = (string)$this->input($f);
            if (mb_strlen($d[$f]) > $len) $this->fail("Isian $f terlalu panjang (maks $len karakter).", '/pengaturan/kop');
        }
        if ($d['nama_perusahaan'] === '') $this->fail('Nama perusahaan harus diisi.', '/pengaturan/kop');
        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $this->fail('Format email tidak valid.', '/pengaturan/kop');
        $ppn = dec($this->input('ppn_default', 11));
        if ($ppn < 0 || $ppn > 100) $this->fail('Tarif PPN harus antara 0 dan 100.', '/pengaturan/kop');
        $d['ppn_default'] = $ppn;
        $d['mode_kop'] = $this->input('mode_kop') === 'gambar' ? 'gambar' : 'sistem';

        $baru = [];
        try {
            if ($logo = $this->unggahGambar('logo', 0)) { $d['logo_path'] = $baru[] = $logo; }
            if ($img = $this->unggahGambar('header_image', 1200)) { $d['header_image_path'] = $baru[] = $img; }
        } catch (\DomainException $e) {
            foreach ($baru as $b) @unlink(STORAGE_PATH . '/kop/' . $b);
            $this->fail($e->getMessage(), '/pengaturan/kop');
        }
        if ($this->input('hapus_logo') === '1' && !isset($d['logo_path'])) $d['logo_path'] = null;
        if ($this->input('hapus_header') === '1' && !isset($d['header_image_path'])) $d['header_image_path'] = null;
        $headerAkhir = array_key_exists('header_image_path', $d) ? $d['header_image_path'] : $k['header_image_path'];
        if ($d['mode_kop'] === 'gambar' && empty($headerAkhir)) {
            foreach ($baru as $b) @unlink(STORAGE_PATH . '/kop/' . $b);
            $this->fail('Mode "kop gambar" memerlukan file kop (JPG/PNG).', '/pengaturan/kop');
        }
        Database::update('pengaturan_perusahaan', $d, 'id', 1);
        // hapus berkas lama yang tergantikan
        foreach (['logo_path', 'header_image_path'] as $c) {
            if (array_key_exists($c, $d) && $k[$c] && $d[$c] !== $k[$c]) @unlink(STORAGE_PATH . '/kop/' . basename($k[$c]));
        }
        Flash::set('success', 'Pengaturan kop berhasil disimpan.');
        redirect('/pengaturan/kop');
    }

    /** Sajikan gambar kop/logo ke pengguna yang login. */
    public function berkas(string $jenis): void
    {
        $k = KopService::get();
        $f = $jenis === 'logo' ? $k['logo_path'] : ($jenis === 'header' ? $k['header_image_path'] : null);
        $path = $f ? STORAGE_PATH . '/kop/' . basename($f) : '';
        if (!$f || !is_file($path)) throw new HttpException(404, 'Berkas tidak ada.');
        header('Content-Type: ' . mime_content_type($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
