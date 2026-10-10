<?php
namespace App\Services;

use App\Core\Database;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Fill, NumberFormat};

/**
 * Semua laporan keuangan dibaca dari jurnal (jurnal.status = 'aktif').
 * Struktur laporan: ['title','subtitle','columns'=>[[label,align]],'rows'=>[['cells'=>[],'style'=>...]],'notes'=>[]]
 * style: normal | head | sub | total ; sel bertipe float/int dianggap nominal uang.
 */
class LaporanService
{
    public const JENIS = [
        'laba_rugi' => 'Laba Rugi',
        'neraca' => 'Neraca',
        'arus_kas' => 'Arus Kas',
        'perubahan_modal' => 'Perubahan Modal',
        'jurnal_umum' => 'Jurnal Umum',
    ];

    public static function build(string $jenis, string $dari, string $sampai): array
    {
        return match ($jenis) {
            'laba_rugi' => self::labaRugi($dari, $sampai),
            'neraca' => self::neraca($sampai),
            'arus_kas' => self::arusKas($dari, $sampai),
            'perubahan_modal' => self::perubahanModal($dari, $sampai),
            'jurnal_umum' => self::jurnalUmum($dari, $sampai),
            default => throw new \DomainException('Jenis laporan tidak valid.'),
        };
    }

    public static function periodeLabel(string $dari, string $sampai): string
    {
        return $dari . ' s/d ' . $sampai;
    }

    // ---------- util query ----------

    /** Saldo per akun: [id_akun => [kode,nama,tipe,saldo_normal,debit,kredit]] pada rentang tanggal (null = tak terbatas). */
    private static function mutasiAkun(?string $dari, ?string $sampai, array $tipe): array
    {
        $w = ["j.status = 'aktif'"]; $p = [];
        if ($dari) { $w[] = 'j.tanggal >= ?'; $p[] = $dari; }
        if ($sampai) { $w[] = 'j.tanggal <= ?'; $p[] = $sampai; }
        $in = implode(',', array_fill(0, count($tipe), '?'));
        $rows = Database::all(
            "SELECT a.id_akun, a.kode_akun, a.nama_akun, a.tipe_akun, a.saldo_normal,
                    COALESCE(SUM(jd.debit),0) AS debit, COALESCE(SUM(jd.kredit),0) AS kredit
               FROM jurnal_detail jd JOIN jurnal j ON j.id_jurnal = jd.id_jurnal JOIN akun a ON a.id_akun = jd.id_akun
              WHERE " . implode(' AND ', $w) . " AND a.tipe_akun IN ($in)
              GROUP BY a.id_akun, a.kode_akun, a.nama_akun, a.tipe_akun, a.saldo_normal ORDER BY a.kode_akun", [...$p, ...$tipe]);
        return $rows;
    }

    private static function net(array $r, string $normal): float
    {
        return round($normal === 'debit' ? $r['debit'] - $r['kredit'] : $r['kredit'] - $r['debit'], 2);
    }

    /** Laba bersih pada rentang. */
    public static function laba(?string $dari, ?string $sampai): float
    {
        $pend = 0.0; $beban = 0.0;
        foreach (self::mutasiAkun($dari, $sampai, ['pendapatan', 'beban']) as $r) {
            if ($r['tipe_akun'] === 'pendapatan') $pend += self::net($r, 'kredit');
            else $beban += self::net($r, 'debit');
        }
        return round($pend - $beban, 2);
    }

    private static function head(string $title, string $subtitle, array $columns): array
    {
        return ['title' => $title, 'subtitle' => $subtitle, 'columns' => $columns, 'rows' => [], 'notes' => []];
    }

    private static function row(array &$rep, array $cells, string $style = 'normal'): void
    {
        $rep['rows'][] = ['cells' => $cells, 'style' => $style];
    }

    // ---------- Laba Rugi ----------

    public static function labaRugi(string $dari, string $sampai): array
    {
        $rep = self::head('LAPORAN LABA RUGI', 'Periode ' . tgl($dari) . ' s/d ' . tgl($sampai), [['Keterangan', 'l'], ['Jumlah (Rp)', 'r']]);
        $pend = $beban = 0.0;
        $rows = self::mutasiAkun($dari, $sampai, ['pendapatan', 'beban']);
        self::row($rep, ['PENDAPATAN', ''], 'head');
        $ada = false;
        foreach ($rows as $r) if ($r['tipe_akun'] === 'pendapatan') {
            $n = self::net($r, 'kredit'); $pend += $n; $ada = true;
            self::row($rep, [$r['kode_akun'] . '  ' . $r['nama_akun'], $n]);
        }
        if (!$ada) self::row($rep, ['(tidak ada pendapatan)', ''], 'sub');
        self::row($rep, ['Total Pendapatan', round($pend, 2)], 'total');
        self::row($rep, ['BEBAN', ''], 'head');
        $ada = false;
        foreach ($rows as $r) if ($r['tipe_akun'] === 'beban') {
            $n = self::net($r, 'debit'); $beban += $n; $ada = true;
            self::row($rep, [$r['kode_akun'] . '  ' . $r['nama_akun'], $n]);
        }
        if (!$ada) self::row($rep, ['(tidak ada beban)', ''], 'sub');
        self::row($rep, ['Total Beban', round($beban, 2)], 'total');
        $laba = round($pend - $beban, 2);
        self::row($rep, [$laba >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH', $laba], 'total');
        return $rep;
    }

    // ---------- Neraca ----------

    public static function neraca(string $sampai): array
    {
        $rep = self::head('NERACA', 'Per tanggal ' . tgl($sampai), [['Keterangan', 'l'], ['Jumlah (Rp)', 'r']]);
        $rows = self::mutasiAkun(null, $sampai, ['aset', 'kewajiban', 'modal']);
        $aset = $kew = $modal = 0.0;

        self::row($rep, ['ASET', ''], 'head');
        foreach ($rows as $r) if ($r['tipe_akun'] === 'aset') {
            $n = self::net($r, 'debit'); $aset += $n; self::row($rep, [$r['kode_akun'] . '  ' . $r['nama_akun'], $n]);
        }
        $aset = round($aset, 2);
        self::row($rep, ['Total Aset', $aset], 'total');

        self::row($rep, ['KEWAJIBAN', ''], 'head');
        foreach ($rows as $r) if ($r['tipe_akun'] === 'kewajiban') {
            $n = self::net($r, 'kredit'); $kew += $n; self::row($rep, [$r['kode_akun'] . '  ' . $r['nama_akun'], $n]);
        }
        $kew = round($kew, 2);
        self::row($rep, ['Total Kewajiban', $kew], 'total');

        self::row($rep, ['MODAL', ''], 'head');
        foreach ($rows as $r) if ($r['tipe_akun'] === 'modal') {
            $n = $r['saldo_normal'] === 'kredit' ? self::net($r, 'kredit') : -self::net($r, 'debit');
            $modal += $n; self::row($rep, [$r['kode_akun'] . '  ' . $r['nama_akun'], $n]);
        }
        $laba = self::laba(null, $sampai);
        $modal += $laba;
        self::row($rep, ['Laba (Rugi) Berjalan', $laba]);
        $modal = round($modal, 2);
        self::row($rep, ['Total Modal', $modal], 'total');
        self::row($rep, ['TOTAL KEWAJIBAN + MODAL', round($kew + $modal, 2)], 'total');

        $seimbang = abs($aset - ($kew + $modal)) < 0.01;
        $rep['notes'][] = 'Status neraca: ' . ($seimbang ? 'SEIMBANG (Aset = Kewajiban + Modal)' : 'TIDAK SEIMBANG, selisih ' . angka($aset - ($kew + $modal)));
        $rep['seimbang'] = $seimbang;

        $piutang = (float)Database::val(
            "SELECT COALESCE(SUM(i.total - COALESCE((SELECT SUM(t.nominal_pembayaran) FROM transaksi_pemasukan t
                      WHERE t.id_invoice = i.id_invoice AND t.status='aktif' AND t.tanggal_pembayaran <= ?),0)),0)
               FROM invoice i WHERE i.status_invoice <> 'batal' AND i.tanggal <= ?", [$sampai, $sampai]);
        $rep['notes'][] = 'Informasi (basis kas, tidak dijurnal): piutang usaha belum tertagih per ' . tgl($sampai) . ' sebesar ' . rupiah($piutang) . '.';
        return $rep;
    }

    // ---------- Perubahan Modal ----------

    public static function perubahanModal(string $dari, string $sampai): array
    {
        $rep = self::head('LAPORAN PERUBAHAN MODAL', 'Periode ' . tgl($dari) . ' s/d ' . tgl($sampai), [['Keterangan', 'l'], ['Jumlah (Rp)', 'r']]);
        $sebelum = date('Y-m-d', strtotime($dari . ' -1 day'));
        $hitung = function (?string $a, ?string $b): array {
            $tambah = $prive = 0.0;
            foreach (self::mutasiAkun($a, $b, ['modal']) as $r) {
                if ($r['saldo_normal'] === 'kredit') $tambah += self::net($r, 'kredit'); else $prive += self::net($r, 'debit');
            }
            return [round($tambah, 2), round($prive, 2)];
        };
        [$tbSebelum, $pvSebelum] = $hitung(null, $sebelum);
        $modalAwal = round($tbSebelum - $pvSebelum + self::laba(null, $sebelum), 2);
        [$tambah, $prive] = $hitung($dari, $sampai);
        $laba = self::laba($dari, $sampai);
        $akhir = round($modalAwal + $tambah + $laba - $prive, 2);
        self::row($rep, ['Modal awal periode', $modalAwal], 'head');
        self::row($rep, ['Penambahan modal / setoran modal', $tambah]);
        self::row($rep, [$laba >= 0 ? 'Laba bersih periode' : 'Rugi bersih periode', $laba]);
        self::row($rep, ['Prive (pengambilan pemilik)', -$prive]);
        self::row($rep, ['MODAL AKHIR PERIODE', $akhir], 'total');
        return $rep;
    }

    // ---------- Jurnal Umum ----------

    public static function jurnalUmum(string $dari, string $sampai): array
    {
        $rep = self::head('JURNAL UMUM', 'Periode ' . tgl($dari) . ' s/d ' . tgl($sampai),
            [['Tanggal', 'l'], ['No. Bukti', 'l'], ['Keterangan / Akun', 'l'], ['Debit (Rp)', 'r'], ['Kredit (Rp)', 'r']]);
        $js = Database::all("SELECT * FROM jurnal WHERE status='aktif' AND tanggal BETWEEN ? AND ? ORDER BY tanggal, id_jurnal", [$dari, $sampai]);
        $td = $tk = 0.0;
        foreach ($js as $j) {
            self::row($rep, [tgl($j['tanggal']), $j['no_bukti'], $j['keterangan'], '', ''], 'head');
            $det = Database::all('SELECT jd.*, a.kode_akun, a.nama_akun FROM jurnal_detail jd JOIN akun a ON a.id_akun = jd.id_akun WHERE jd.id_jurnal = ? ORDER BY jd.debit DESC, jd.id_detail', [$j['id_jurnal']]);
            foreach ($det as $d) {
                $indent = $d['kredit'] > 0 ? '      ' : '';
                self::row($rep, ['', '', $indent . $d['kode_akun'] . '  ' . $d['nama_akun'], $d['debit'] > 0 ? (float)$d['debit'] : '', $d['kredit'] > 0 ? (float)$d['kredit'] : '']);
                $td += $d['debit']; $tk += $d['kredit'];
            }
        }
        if (!$js) self::row($rep, ['', '', '(tidak ada jurnal pada periode ini)', '', ''], 'sub');
        self::row($rep, ['', '', 'TOTAL', round($td, 2), round($tk, 2)], 'total');
        $rep['notes'][] = abs($td - $tk) < 0.01 ? 'Total debit sama dengan total kredit (seimbang).' : 'PERINGATAN: total debit tidak sama dengan total kredit!';
        return $rep;
    }

    // ---------- Arus Kas ----------

    public static function arusKas(string $dari, string $sampai): array
    {
        $rep = self::head('LAPORAN ARUS KAS', 'Periode ' . tgl($dari) . ' s/d ' . tgl($sampai),
            [['Tanggal', 'l'], ['No. Bukti', 'l'], ['Keterangan', 'l'], ['Masuk (Rp)', 'r'], ['Keluar (Rp)', 'r'], ['Saldo (Rp)', 'r']]);
        $grandAwal = $grandMasuk = $grandKeluar = 0.0;
        foreach (Database::all('SELECT * FROM kas_bank ORDER BY kode_kas_bank') as $kb) {
            $id = (int)$kb['id_kas_bank'];
            $tglAwal = Database::val("SELECT tanggal FROM jurnal WHERE sumber='saldo_awal' AND id_sumber=? AND status='aktif'", [$id]);
            $awalDlmPeriode = $tglAwal && $tglAwal >= $dari && $tglAwal <= $sampai ? (float)$kb['saldo_awal'] : 0.0;
            $opening = ($tglAwal && $tglAwal < $dari ? (float)$kb['saldo_awal'] : 0.0)
                + (float)Database::val("SELECT COALESCE(SUM(nominal_pembayaran),0) FROM transaksi_pemasukan WHERE id_kas_bank=? AND status='aktif' AND tanggal_pembayaran < ?", [$id, $dari])
                - (float)Database::val("SELECT COALESCE(SUM(nominal_pengeluaran),0) FROM transaksi_pengeluaran WHERE id_kas_bank=? AND status='aktif' AND tanggal_pengeluaran < ?", [$id, $dari]);
            $opening = round($opening, 2);
            $tx = Database::all(
                "SELECT tanggal, no_bukti, keterangan, masuk, keluar FROM (
                   SELECT tanggal_pembayaran AS tanggal, no_bukti_pemasukan AS no_bukti, keterangan_pembayaran AS keterangan, nominal_pembayaran AS masuk, 0 AS keluar FROM transaksi_pemasukan WHERE id_kas_bank=:a AND status='aktif' AND tanggal_pembayaran BETWEEN :d1 AND :s1
                   UNION ALL
                   SELECT tanggal_pengeluaran, no_bukti_pengeluaran, keterangan_pengeluaran, 0, nominal_pengeluaran FROM transaksi_pengeluaran WHERE id_kas_bank=:b AND status='aktif' AND tanggal_pengeluaran BETWEEN :d2 AND :s2
                 ) x ORDER BY tanggal, no_bukti", ['a' => $id, 'd1' => $dari, 's1' => $sampai, 'b' => $id, 'd2' => $dari, 's2' => $sampai]);

            self::row($rep, [$kb['kode_kas_bank'] . ' - ' . $kb['nama_kas_bank'], '', '', '', '', ''], 'head');
            $saldo = $opening;
            self::row($rep, ['', '', 'Saldo awal periode', '', '', $saldo], 'sub');
            $masuk = $keluar = 0.0;
            if ($awalDlmPeriode > 0) {
                $saldo += $awalDlmPeriode; $masuk += $awalDlmPeriode;
                self::row($rep, [tgl($tglAwal), 'SALDO-' . $id, 'Saldo awal kas/bank', $awalDlmPeriode, '', round($saldo, 2)]);
            }
            foreach ($tx as $t) {
                $saldo += $t['masuk'] - $t['keluar']; $masuk += $t['masuk']; $keluar += $t['keluar'];
                self::row($rep, [tgl($t['tanggal']), $t['no_bukti'], $t['keterangan'], $t['masuk'] > 0 ? (float)$t['masuk'] : '', $t['keluar'] > 0 ? (float)$t['keluar'] : '', round($saldo, 2)]);
            }
            self::row($rep, ['', '', 'Saldo akhir ' . $kb['nama_kas_bank'], round($masuk, 2), round($keluar, 2), round($saldo, 2)], 'total');
            $grandAwal += $opening; $grandMasuk += $masuk; $grandKeluar += $keluar;
        }
        self::row($rep, ['', '', 'TOTAL KAS DAN BANK', round($grandMasuk, 2), round($grandKeluar, 2), round($grandAwal + $grandMasuk - $grandKeluar, 2)], 'total');
        $rep['notes'][] = 'Saldo awal periode seluruh kas/bank: ' . rupiah($grandAwal) . '. Saldo akhir = saldo awal + masuk - keluar.';
        return $rep;
    }

    // ---------- Daftar transaksi (cetak pemasukan / pengeluaran) ----------

    public static function cetakDaftar(string $judul, array $f, array $rows, string $tipe, int $userId): never
    {
        $periode = ($f['dari'] ?: 'awal') . ' s/d ' . ($f['sampai'] ?: 'sekarang');
        $rep = self::head(strtoupper($judul), 'Periode ' . ($f['dari'] ? tgl($f['dari']) : 'awal') . ' s/d ' . ($f['sampai'] ? tgl($f['sampai']) : 'sekarang'),
            [['Tanggal', 'l'], ['No. Bukti', 'l'], [$tipe === 'pemasukan' ? 'Invoice / Akun' : 'Akun', 'l'], ['Kas/Bank', 'l'], ['Keterangan', 'l'], ['Nominal (Rp)', 'r']]);
        $tot = 0.0;
        foreach ($rows as $r) {
            $isMasuk = $tipe === 'pemasukan';
            $n = (float)($isMasuk ? $r['nominal_pembayaran'] : $r['nominal_pengeluaran']);
            $tot += $n;
            self::row($rep, [
                tgl($isMasuk ? $r['tanggal_pembayaran'] : $r['tanggal_pengeluaran']),
                $isMasuk ? $r['no_bukti_pemasukan'] : $r['no_bukti_pengeluaran'],
                $isMasuk ? trim(($r['no_invoice'] ?? '') . ' ' . $r['nama_akun']) : $r['nama_akun'],
                $r['nama_kas_bank'], $isMasuk ? $r['keterangan_pembayaran'] : $r['keterangan_pengeluaran'], $n,
            ]);
        }
        if (!$rows) self::row($rep, ['', '', '(tidak ada data)', '', '', ''], 'sub');
        self::row($rep, ['', '', '', '', 'TOTAL', round($tot, 2)], 'total');
        self::keluarkanPdf($rep, $judul, $periode, $userId, isset($_GET['unduh']));
    }

    // ---------- Render: PDF, Excel, riwayat ----------

    public static function pdf(array $rep): string
    {
        $kop = KopService::siap();
        $land = count($rep['columns']) >= 6;
        ob_start();
        include APP_PATH . '/views/pdf/laporan.php';
        return PdfService::render(ob_get_clean(), 'A4', $land ? 'landscape' : 'portrait');
    }

    private static function namaFile(string $jenis, string $periode, string $ext): string
    {
        $s = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $jenis . '_' . $periode);
        return trim($s, '_') . '.' . $ext;
    }

    /** Simpan file ke storage/laporan lalu catat ke riwayat. @return string nama file di storage */
    private static function simpanDanCatat(string $isi, string $jenis, string $periode, string $format, int $userId): string
    {
        $disk = bin2hex(random_bytes(12)) . '.' . ($format === 'pdf' ? 'pdf' : 'xlsx');
        file_put_contents(STORAGE_PATH . '/laporan/' . $disk, $isi);
        Database::insert('riwayat_laporan', ['id_user' => $userId, 'jenis_laporan' => $jenis, 'periode' => $periode, 'format' => $format, 'file_laporan' => $disk]);
        return $disk;
    }

    public static function keluarkanPdf(array $rep, string $jenis, string $periode, int $userId, bool $unduh): never
    {
        $pdf = self::pdf($rep);
        self::simpanDanCatat($pdf, $jenis, $periode, 'pdf', $userId);
        PdfService::kirim($pdf, self::namaFile($jenis, $periode, 'pdf'), $unduh);
    }

    public static function excel(array $rep): string
    {
        $kop = KopService::get();
        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet();
        $ws->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $rep['title']), 0, 30));
        $ncol = count($rep['columns']);
        $last = chr(64 + $ncol);
        $r = 1;
        foreach ([[$kop['nama_perusahaan'] ?: 'Nama Perusahaan', true, 14], [$kop['alamat'] ?: 'Alamat perusahaan', false, 10], [$rep['title'], true, 13], [$rep['subtitle'], false, 10]] as [$txt, $bold, $size]) {
            $ws->setCellValue("A$r", $txt);
            $ws->mergeCells("A$r:$last$r");
            $ws->getStyle("A$r")->getFont()->setBold($bold)->setSize($size);
            $r++;
        }
        $r++;
        foreach ($rep['columns'] as $i => [$label, $al]) {
            $c = chr(65 + $i);
            $ws->setCellValue("$c$r", $label);
            $ws->getStyle("$c$r")->getAlignment()->setHorizontal($al === 'r' ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT);
        }
        $ws->getStyle("A$r:$last$r")->getFont()->setBold(true);
        $ws->getStyle("A$r:$last$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2F8');
        $r++;
        foreach ($rep['rows'] as $row) {
            foreach ($row['cells'] as $i => $cell) {
                $c = chr(65 + $i);
                if (is_int($cell) || is_float($cell)) {
                    $ws->setCellValue("$c$r", $cell);
                    $ws->getStyle("$c$r")->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
                } else {
                    $ws->setCellValueExplicit("$c$r", (string)$cell, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                if (($rep['columns'][$i][1] ?? 'l') === 'r') $ws->getStyle("$c$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            if ($row['style'] === 'head' || $row['style'] === 'total') $ws->getStyle("A$r:$last$r")->getFont()->setBold(true);
            if ($row['style'] === 'head') $ws->getStyle("A$r:$last$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F4F6FA');
            if ($row['style'] === 'sub') $ws->getStyle("A$r:$last$r")->getFont()->setItalic(true);
            $r++;
        }
        foreach ($rep['notes'] as $n) { $r++; $ws->setCellValue("A$r", $n); $ws->mergeCells("A$r:$last$r"); $ws->getStyle("A$r")->getFont()->setItalic(true); }
        for ($i = 0; $i < $ncol; $i++) $ws->getColumnDimension(chr(65 + $i))->setAutoSize(true);

        $tmp = tempnam(sys_get_temp_dir(), 'xls');
        (new Xlsx($ss))->save($tmp);
        $isi = (string)file_get_contents($tmp);
        @unlink($tmp);
        return $isi;
    }

    public static function keluarkanExcel(array $rep, string $jenis, string $periode, int $userId): never
    {
        $isi = self::excel($rep);
        self::simpanDanCatat($isi, $jenis, $periode, 'excel', $userId);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . self::namaFile($jenis, $periode, 'xlsx') . '"');
        header('Content-Length: ' . strlen($isi));
        echo $isi;
        exit;
    }

    public static function namaUnduh(array $riwayat): string
    {
        return self::namaFile($riwayat['jenis_laporan'], $riwayat['periode'], $riwayat['format'] === 'pdf' ? 'pdf' : 'xlsx');
    }
}
