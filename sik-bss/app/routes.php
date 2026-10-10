<?php
/** @var App\Core\Router $router */
$A = ['admin'];
$ALL = ['*'];

$router->get('/', 'AuthController@home', null);
$router->get('/login', 'AuthController@form', null);
$router->post('/login', 'AuthController@login', null);
$router->post('/logout', 'AuthController@logout', $ALL);

$router->get('/dashboard', 'DashboardController@index', $ALL);

// Master data
foreach (['akun' => 'Akun', 'pelanggan' => 'Pelanggan', 'kas-bank' => 'KasBank'] as $seg => $c) {
    $router->get("/$seg", "{$c}Controller@index", $A);
    $router->post("/$seg/store", "{$c}Controller@store", $A);
    $router->post("/$seg/update/{id}", "{$c}Controller@update", $A);
    $router->post("/$seg/status/{id}", "{$c}Controller@status", $A);
}
$router->get('/kas-bank/mutasi/{id}', 'KasBankController@mutasi', $A);

// Invoice
$router->get('/invoice', 'InvoiceController@index', $A);
$router->get('/invoice/create', 'InvoiceController@create', $A);
$router->post('/invoice/store', 'InvoiceController@store', $A);
$router->get('/invoice/{id}', 'InvoiceController@show', $A);
$router->get('/invoice/{id}/edit', 'InvoiceController@edit', $A);
$router->post('/invoice/update/{id}', 'InvoiceController@update', $A);
$router->post('/invoice/status/{id}', 'InvoiceController@status', $A);
$router->get('/invoice/{id}/cetak', 'InvoiceController@cetak', $A);

// Transaksi
foreach (['pemasukan' => 'Pemasukan', 'pengeluaran' => 'Pengeluaran'] as $seg => $c) {
    $router->get("/$seg", "{$c}Controller@index", $A);
    $router->get("/$seg/create", "{$c}Controller@create", $A);
    $router->post("/$seg/store", "{$c}Controller@store", $A);
    $router->get("/$seg/cetak", "{$c}Controller@cetak", $A);
    $router->get("/$seg/{id}/edit", "{$c}Controller@edit", $A);
    $router->post("/$seg/update/{id}", "{$c}Controller@update", $A);
    $router->post("/$seg/batal/{id}", "{$c}Controller@batal", $A);
}
$router->get('/pengeluaran/{id}/bukti', 'PengeluaranController@bukti', $A);

// Laporan & riwayat (admin + pimpinan)
$router->get('/laporan', 'LaporanController@index', $ALL);
$router->get('/laporan/preview', 'LaporanController@preview', $ALL);
$router->get('/laporan/pdf', 'LaporanController@pdf', $ALL);
$router->get('/laporan/excel', 'LaporanController@excel', $ALL);
$router->get('/riwayat', 'RiwayatController@index', $ALL);
$router->get('/riwayat/{id}/buka', 'RiwayatController@buka', $ALL);
$router->get('/riwayat/{id}/unduh', 'RiwayatController@unduh', $ALL);

// Pengaturan & profil
$router->get('/pengaturan/kop', 'PengaturanController@kop', $A);
$router->post('/pengaturan/kop', 'PengaturanController@simpan', $A);
$router->get('/kop/{jenis}', 'PengaturanController@berkas', $ALL);
$router->get('/profil/password', 'ProfilController@form', $ALL);
$router->post('/profil/password', 'ProfilController@simpan', $ALL);
