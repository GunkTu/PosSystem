<?php
namespace App\Controllers;

use App\Core\{Controller, Auth, Database};
use App\Services\LaporanService;

class DashboardController extends Controller
{
    public function index(): void
    {
        $awal = date('Y-m-01');
        $akhir = date('Y-m-t');
        $masuk = (float)Database::val("SELECT COALESCE(SUM(nominal_pembayaran),0) FROM transaksi_pemasukan WHERE status='aktif' AND tanggal_pembayaran BETWEEN ? AND ?", [$awal, $akhir]);
        $keluar = (float)Database::val("SELECT COALESCE(SUM(nominal_pengeluaran),0) FROM transaksi_pengeluaran WHERE status='aktif' AND tanggal_pengeluaran BETWEEN ? AND ?", [$awal, $akhir]);
        $saldo = (float)Database::val("SELECT COALESCE(SUM(saldo),0) FROM kas_bank WHERE status='aktif'");
        $inv = Database::one("SELECT COUNT(*) AS n, COALESCE(SUM(total - total_terbayar),0) AS sisa FROM invoice WHERE status_invoice IN ('belum_lunas','sebagian')");

        $labels = $dm = $dk = [];
        for ($i = 5; $i >= 0; $i--) {
            $t = strtotime("first day of -$i month");
            $a = date('Y-m-01', $t); $b = date('Y-m-t', $t);
            $bln = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][(int)date('n', $t)];
            $labels[] = $bln . ' ' . date('y', $t);
            $dm[] = (float)Database::val("SELECT COALESCE(SUM(nominal_pembayaran),0) FROM transaksi_pemasukan WHERE status='aktif' AND tanggal_pembayaran BETWEEN ? AND ?", [$a, $b]);
            $dk[] = (float)Database::val("SELECT COALESCE(SUM(nominal_pengeluaran),0) FROM transaksi_pengeluaran WHERE status='aktif' AND tanggal_pengeluaran BETWEEN ? AND ?", [$a, $b]);
        }

        $terbaru = Database::all(
            "SELECT * FROM (
               SELECT tanggal_pembayaran AS tanggal, no_bukti_pemasukan AS no_bukti, keterangan_pembayaran AS ket, nominal_pembayaran AS nominal, 'masuk' AS jenis, id_pemasukan AS id FROM transaksi_pemasukan WHERE status='aktif'
               UNION ALL
               SELECT tanggal_pengeluaran, no_bukti_pengeluaran, keterangan_pengeluaran, nominal_pengeluaran, 'keluar', id_pengeluaran FROM transaksi_pengeluaran WHERE status='aktif'
             ) x ORDER BY tanggal DESC, id DESC LIMIT 10");

        $jatuhTempo = Database::all(
            "SELECT i.id_invoice, i.no_invoice, i.jatuh_tempo, i.total - i.total_terbayar AS sisa, p.nama_pelanggan
               FROM invoice i JOIN pelanggan p ON p.id_pelanggan = i.id_pelanggan
              WHERE i.status_invoice IN ('belum_lunas','sebagian') AND i.jatuh_tempo <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
              ORDER BY i.jatuh_tempo LIMIT 10");

        $this->view('dashboard/index', [
            'title' => 'Dashboard' . (Auth::isAdmin() ? '' : ' Pimpinan'),
            'admin' => Auth::isAdmin(),
            'masuk' => $masuk, 'keluar' => $keluar, 'saldo' => $saldo, 'inv' => $inv,
            'laba' => Auth::isAdmin() ? null : LaporanService::laba($awal, $akhir),
            'labels' => $labels, 'dm' => $dm, 'dk' => $dk, 'terbaru' => $terbaru, 'jatuhTempo' => $jatuhTempo,
            'kasBank' => Database::all("SELECT nama_kas_bank, jenis_kas_bank, saldo FROM kas_bank WHERE status='aktif' ORDER BY kode_kas_bank"),
            'bulanIni' => tgl($awal) . ' s/d ' . tgl($akhir),
        ]);
    }
}
