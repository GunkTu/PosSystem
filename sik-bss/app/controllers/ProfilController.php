<?php
namespace App\Controllers;

use App\Core\{Controller, Auth, Database, Flash};

class ProfilController extends Controller
{
    public function form(): void
    {
        $this->view('profil/password', ['title' => 'Ganti Password']);
    }

    public function simpan(): void
    {
        $lama = (string)($_POST['password_lama'] ?? '');
        $baru = (string)($_POST['password_baru'] ?? '');
        $konf = (string)($_POST['konfirmasi'] ?? '');
        $u = Database::one('SELECT * FROM pengguna WHERE id_user = ?', [Auth::id()]);
        if ($lama === '' || $baru === '' || $konf === '') $this->fail('Semua field harus diisi.', '/profil/password');
        if (!password_verify($lama, $u['password'])) $this->fail('Password lama salah.', '/profil/password');
        if (strlen($baru) < 8) $this->fail('Password baru minimal 8 karakter.', '/profil/password');
        if ($baru !== $konf) $this->fail('Konfirmasi password tidak sama.', '/profil/password');
        Database::update('pengguna', ['password' => password_hash($baru, PASSWORD_DEFAULT)], 'id_user', Auth::id());
        Flash::set('success', 'Password berhasil diganti.');
        redirect('/dashboard');
    }
}
