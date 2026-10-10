<?php
use App\Core\{Auth, Flash};
$u = Auth::user();
$admin = Auth::isAdmin();
$cfgApp = config('app');
$menu = [
    ['/dashboard', 'Dashboard', 'bi-speedometer2', true],
    ['/akun', 'Data Akun', 'bi-journal-text', false],
    ['/pelanggan', 'Data Pelanggan', 'bi-people', false],
    ['/kas-bank', 'Kas dan Bank', 'bi-bank', false],
    ['/invoice', 'Invoice', 'bi-receipt', false],
    ['/pemasukan', 'Transaksi Pemasukan', 'bi-box-arrow-in-down', false],
    ['/pengeluaran', 'Transaksi Pengeluaran', 'bi-box-arrow-up', false],
    ['/laporan', 'Laporan Keuangan', 'bi-bar-chart-line', true],
    ['/riwayat', 'Riwayat Laporan', 'bi-clock-history', true],
    ['/pengaturan/kop', 'Pengaturan Kop', 'bi-building-gear', false],
];
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ? $title . ' | ' : '') ?><?= e($cfgApp['name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="<?= url('assets/css/app.css') ?>" rel="stylesheet">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar">
    <div class="sidebar-brand">
      <i class="bi bi-wallet2"></i>
      <div><strong>SIK</strong><small><?= e($cfgApp['company']) ?></small></div>
    </div>
    <nav class="nav flex-column">
      <?php foreach ($menu as [$href, $label, $icon, $all]): if (!$all && !$admin) continue; ?>
        <a class="nav-link <?= is_active_menu($href) ?>" href="<?= url($href) ?>"><i class="bi <?= $icon ?>"></i><?= e($label) ?></a>
      <?php endforeach; ?>
      <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="bi bi-box-arrow-left"></i>Logout</a>
    </nav>
  </aside>
  <div class="main">
    <header class="topbar">
      <button class="btn btn-light d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Menu"><i class="bi bi-list"></i></button>
      <h1 class="page-title"><?= e($title) ?></h1>
      <div class="dropdown ms-auto">
        <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown">
          <i class="bi bi-person-circle"></i> <?= e($u['nama']) ?> <span class="badge text-bg-secondary"><?= e(ucfirst($u['role'])) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= url('/profil/password') ?>"><i class="bi bi-key"></i> Ganti Password</a></li>
          <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="bi bi-box-arrow-left"></i> Logout</a></li>
        </ul>
      </div>
    </header>
    <main class="content">
      <?php foreach (Flash::pull() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show <?= $f['type'] === 'success' ? 'auto-dismiss' : '' ?>" role="alert">
          <?= e($f['msg']) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
      <?php endforeach; ?>
      <?= $content ?>
    </main>
  </div>
</div>

<div class="modal fade" id="logoutModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
  <form method="post" action="<?= url('/logout') ?>"><?= csrf_field() ?>
  <div class="modal-body text-center pt-4"><i class="bi bi-box-arrow-left fs-1 text-primary"></i><p class="mt-2 mb-0 fw-semibold">Anda yakin ingin keluar?</p></div>
  <div class="modal-footer justify-content-center border-0"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Ya, Logout</button></div>
  </form></div></div></div>

<div class="modal fade" id="confirmModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <form method="post" id="confirmForm"><?= csrf_field() ?>
  <div class="modal-body"><p class="mb-0 fw-semibold" id="confirmText">Yakin?</p></div>
  <div class="modal-footer border-0"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-danger" id="confirmYes">Ya</button></div>
  </form></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<?= $scripts ?? '' ?>
</body>
</html>
