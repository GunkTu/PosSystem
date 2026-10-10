<?php
namespace App\Services;

use App\Core\Database;

class JurnalService
{
    /**
     * Buat jurnal. $lines = [['akun'=>id, 'debit'=>x] | ['akun'=>id, 'kredit'=>x], ...]
     * Wajib dipanggil di dalam transaksi database.
     */
    public static function buat(string $tanggal, string $noBukti, string $ket, string $sumber, ?int $idSumber, array $lines): int
    {
        $d = $k = 0.0;
        $lines = array_values(array_filter($lines));
        foreach ($lines as $l) { $d += $l['debit'] ?? 0; $k += $l['kredit'] ?? 0; }
        if (abs($d - $k) > 0.001 || $d <= 0) {
            throw new \RuntimeException('Jurnal tidak seimbang (debit ≠ kredit).');
        }
        $id = Database::insert('jurnal', [
            'tanggal' => $tanggal, 'no_bukti' => $noBukti, 'keterangan' => mb_substr($ket, 0, 150),
            'sumber' => $sumber, 'id_sumber' => $idSumber,
        ]);
        foreach ($lines as $l) {
            Database::insert('jurnal_detail', [
                'id_jurnal' => $id, 'id_akun' => $l['akun'],
                'debit' => round($l['debit'] ?? 0, 2), 'kredit' => round($l['kredit'] ?? 0, 2),
            ]);
        }
        return $id;
    }

    public static function batalkan(string $sumber, int $idSumber): void
    {
        Database::exec("UPDATE jurnal SET status='batal' WHERE sumber=? AND id_sumber=? AND status='aktif'", [$sumber, $idSumber]);
    }
}
