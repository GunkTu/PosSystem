<?php
namespace App\Controllers;

use App\Core\{Controller, Auth, Flash};

class AuthController extends Controller
{
    public function home(): void
    {
        redirect(Auth::check() ? '/dashboard' : '/login');
    }

    public function form(): void
    {
        if (Auth::check()) redirect('/dashboard');
        $this->view('auth/login', ['title' => 'Login'], 'auth');
    }

    public function login(): void
    {
        $username = (string)$this->input('username');
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            $this->fail('Username dan password harus diisi.', '/login');
        }
        $err = Auth::attempt($username, $password);
        if ($err) {
            Flash::set('danger', $err);
            redirect('/login');
        }
        redirect('/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        session_start();
        Flash::set('success', 'Anda telah keluar.');
        redirect('/login');
    }
}
