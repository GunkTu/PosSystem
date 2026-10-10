<?php
namespace App\Controllers;

use App\Core\{Controller, Auth, Flash};
use App\Services\{LaporanService, KopService};

class LaporanController extends Controller
{
    private function params(): array
    {
        $jenis = (string)$this->input('jenis', 'laba_rugi');
        $dari = (string)$this->input('dari', date('Y-m-01'));
        $sampai = (string)$this->input('sampai', date('Y-m-t'));
        if (!isset(LaporanService::JENIS[$jenis])) throw new \DomainException('Jenis laporan tidak valid.');
        if (!valid_date($dari) || !valid_date($sampai)) throw new \DomainException('Periode harus diisi dengan tanggal yang valid.');
        if ($dari > $sampai) throw new \DomainException('Tanggal awal tidak boleh setelah tanggal akhir.');
        return [$jenis, $dari, $sampai];
    }

    private function build(): array
    {
        [$jenis, $dari, $sampai] = $this->params();
        return [$jenis, $dari, $sampai, LaporanService::build($jenis, $dari, $sampai)];
    }

    public function index(): void
    {
        $this->view('laporan/index', [
            'title' => 'Laporan Keuangan', 'jenisList' => LaporanService::JENIS,
            'jenis' => (string)$this->input('jenis', 'laba_rugi'),
            'dari' => date('Y-m-01'), 'sampai' => date('Y-m-t'),
        ]);
    }

    public function preview(): void
    {
        try {
            [$jenis, $dari, $sampai, $rep] = $this->build();
        } catch (\DomainException $e) {
            Flash::set('danger', $e->getMessage());
            redirect('/laporan');
        }
        $this->view('laporan/preview', [
            'title' => 'Laporan ' . LaporanService::JENIS[$jenis], 'rep' => $rep, 'kop' => KopService::siap(),
            'jenis' => $jenis, 'dari' => $dari, 'sampai' => $sampai, 'q' => http_build_query(['jenis' => $jenis, 'dari' => $dari, 'sampai' => $sampai]),
        ]);
    }

    public function pdf(): void
    {
        try { [$jenis, $dari, $sampai, $rep] = $this->build(); }
        catch (\DomainException $e) { Flash::set('danger', $e->getMessage()); redirect('/laporan'); }
        LaporanService::keluarkanPdf($rep, 'Laporan ' . LaporanService::JENIS[$jenis], LaporanService::periodeLabel($dari, $sampai), Auth::id(), isset($_GET['unduh']));
    }

    public function excel(): void
    {
        try { [$jenis, $dari, $sampai, $rep] = $this->build(); }
        catch (\DomainException $e) { Flash::set('danger', $e->getMessage()); redirect('/laporan'); }
        LaporanService::keluarkanExcel($rep, 'Laporan ' . LaporanService::JENIS[$jenis], LaporanService::periodeLabel($dari, $sampai), Auth::id());
    }
}
