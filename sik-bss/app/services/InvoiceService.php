<?php
namespace App\Services;

use App\Core\Database;

class InvoiceService
{
    public const LAYANAN = ['freight' => 'Freight', 'gudang' => 'Gudang', 'pengiriman' => 'Pengiriman', 'lainnya' => 'Lainnya'];

    /** Nomor otomatis INV-YYYYMM-0001. Panggil di dalam transaksi. */
    public static function nomor(string $tanggal): string
    {
        $prefix = 'INV-' . date('Ym', strtotime($tanggal)) . '-';
        $last = Database::val('SELECT no_invoice FROM invoice WHERE no_invoice LIKE ? ORDER BY no_invoice DESC LIMIT 1 FOR UPDATE', [$prefix . '%']);
        $n = $last ? (int)substr($last, -4) + 1 : 1;
        return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
    }

    /** Ambil item dari $_POST (array paralel) dan validasi. @return array item ternormalisasi */
    public static function parseItems(array $post): array
    {
        $items = [];
        foreach (['item_deskripsi', 'item_qty', 'item_harga', 'item_jenis', 'item_satuan'] as $f) {
            if (isset($post[$f]) && !is_array($post[$f])) throw new \DomainException('Data item tidak valid.');
        }
        $n = count($post['item_deskripsi'] ?? []);
        for ($i = 0; $i < $n; $i++) {
            $desk = trim((string)($post['item_deskripsi'][$i] ?? ''));
            $qty = dec($post['item_qty'][$i] ?? 0);
            $harga = dec($post['item_harga'][$i] ?? 0);
            $jenis = (string)($post['item_jenis'][$i] ?? 'lainnya');
            $satuan = trim((string)($post['item_satuan'][$i] ?? ''));
            if ($desk === '' && $qty == 0 && $harga == 0) continue;      // baris kosong
            if ($desk === '') throw new \DomainException('Deskripsi item baris ' . ($i + 1) . ' harus diisi.');
            if (mb_strlen($desk) > 150 || mb_strlen($satuan) > 20) throw new \DomainException('Deskripsi/satuan item terlalu panjang.');
            if ($qty <= 0) throw new \DomainException('Qty item baris ' . ($i + 1) . ' harus lebih dari 0.');
            if ($harga <= 0) throw new \DomainException('Harga item baris ' . ($i + 1) . ' harus lebih dari 0.');
            if (!isset(self::LAYANAN[$jenis])) $jenis = 'lainnya';
            $items[] = ['jenis_layanan' => $jenis, 'deskripsi' => $desk, 'qty' => $qty, 'satuan' => $satuan,
                        'harga_satuan' => $harga, 'subtotal' => round($qty * $harga, 2)];
        }
        if (!$items) throw new \DomainException('Invoice minimal memiliki satu item layanan.');
        return $items;
    }

    /** @return array [subtotal, ppn_nominal, total] */
    public static function hitung(array $items, float $ppnPersen): array
    {
        $sub = round(array_sum(array_column($items, 'subtotal')), 2);
        $ppn = round($sub * $ppnPersen / 100, 2);
        return [$sub, $ppn, round($sub + $ppn, 2)];
    }

    private static function cekPpn($v): float
    {
        $p = dec($v);
        if ($p < 0 || $p > 100) throw new \DomainException('Tarif PPN harus antara 0 dan 100.');
        return $p;
    }

    public static function simpan(array $head, array $items, int $userId): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $ppnP = self::cekPpn($head['ppn_persen']);
            [$sub, $ppn, $total] = self::hitung($items, $ppnP);
            $id = Database::insert('invoice', [
                'id_pelanggan' => $head['id_pelanggan'], 'no_invoice' => self::nomor($head['tanggal']),
                'tanggal' => $head['tanggal'], 'jatuh_tempo' => $head['jatuh_tempo'], 'keterangan' => $head['keterangan'],
                'subtotal' => $sub, 'ppn_persen' => $ppnP, 'ppn_nominal' => $ppn, 'total' => $total, 'created_by' => $userId,
            ]);
            self::simpanItem($id, $items);
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    private static function simpanItem(int $id, array $items): void
    {
        foreach ($items as $it) Database::insert('invoice_item', ['id_invoice' => $id] + $it);
    }

    public static function punyaPembayaran(int $id): bool
    {
        return (bool)Database::val("SELECT COUNT(*) FROM transaksi_pemasukan WHERE id_invoice = ? AND status = 'aktif'", [$id]);
    }

    /** Jika sudah ada pembayaran, hanya header (tanggal, jatuh tempo, keterangan) yang boleh berubah. */
    public static function ubah(int $id, array $head, ?array $items): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $inv = Database::one('SELECT * FROM invoice WHERE id_invoice = ? FOR UPDATE', [$id]);
            if (!$inv) throw new \DomainException('Invoice tidak ditemukan.');
            if ($inv['status_invoice'] === 'batal') throw new \DomainException('Invoice yang dibatalkan tidak dapat diubah.');
            $upd = ['jatuh_tempo' => $head['jatuh_tempo'], 'keterangan' => $head['keterangan']];
            if (!self::punyaPembayaran($id)) {
                if ($items === null) throw new \DomainException('Invoice minimal memiliki satu item layanan.');
                $ppnP = self::cekPpn($head['ppn_persen']);
                [$sub, $ppn, $total] = self::hitung($items, $ppnP);
                $upd += ['id_pelanggan' => $head['id_pelanggan'], 'tanggal' => $head['tanggal'],
                         'subtotal' => $sub, 'ppn_persen' => $ppnP, 'ppn_nominal' => $ppn, 'total' => $total];
                Database::exec('DELETE FROM invoice_item WHERE id_invoice = ?', [$id]);
                self::simpanItem($id, $items);
            }
            Database::update('invoice', $upd, 'id_invoice', $id);
            $pdo->commit();
        } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    /** Hitung ulang total_terbayar dan status dari pemasukan aktif (BR-02). */
    public static function hitungStatus(int $id): void
    {
        $inv = Database::one('SELECT total, status_invoice FROM invoice WHERE id_invoice = ?', [$id]);
        if (!$inv || $inv['status_invoice'] === 'batal') return;
        $bayar = round((float)Database::val("SELECT COALESCE(SUM(nominal_pembayaran),0) FROM transaksi_pemasukan WHERE id_invoice = ? AND status = 'aktif'", [$id]), 2);
        $status = $bayar <= 0 ? 'belum_lunas' : ($bayar + 0.004 >= (float)$inv['total'] ? 'lunas' : 'sebagian');
        Database::update('invoice', ['total_terbayar' => $bayar, 'status_invoice' => $status], 'id_invoice', $id);
    }

    public static function toggleBatal(int $id): string
    {
        $inv = Database::one('SELECT * FROM invoice WHERE id_invoice = ?', [$id]);
        if (!$inv) throw new \DomainException('Invoice tidak ditemukan.');
        if ($inv['status_invoice'] === 'batal') {
            Database::update('invoice', ['status_invoice' => 'belum_lunas'], 'id_invoice', $id);
            self::hitungStatus($id);
            return 'Invoice diaktifkan kembali.';
        }
        if (self::punyaPembayaran($id)) {
            throw new \DomainException('Invoice sudah memiliki pembayaran aktif. Batalkan pembayarannya terlebih dahulu.');
        }
        Database::update('invoice', ['status_invoice' => 'batal'], 'id_invoice', $id);
        return 'Invoice dibatalkan.';
    }

    /**
     * Pecah nominal pembayaran menjadi [pendapatan, ppn] secara proporsional (BR-11).
     * Pelunasan memakai sisa PPN agar total PPN yang diakui = PPN invoice.
     */
    public static function pecahPpn(array $inv, float $nominal, ?int $kecualiPemasukan = null): array
    {
        $ppnInv = (float)$inv['ppn_nominal'];
        $total = (float)$inv['total'];
        if ($ppnInv <= 0 || $total <= 0) return [round($nominal, 2), 0.0];

        $akunPpn = Database::val("SELECT id_akun FROM akun WHERE kode_akun='2-101'");
        $ex = $kecualiPemasukan ?? 0;
        $sudahBayar = (float)Database::val("SELECT COALESCE(SUM(nominal_pembayaran),0) FROM transaksi_pemasukan WHERE id_invoice=? AND status='aktif' AND id_pemasukan<>?", [$inv['id_invoice'], $ex]);
        $sudahPpn = (float)Database::val(
            "SELECT COALESCE(SUM(jd.kredit),0) FROM jurnal_detail jd
               JOIN jurnal j ON j.id_jurnal = jd.id_jurnal AND j.status='aktif' AND j.sumber='pemasukan'
               JOIN transaksi_pemasukan t ON t.id_pemasukan = j.id_sumber AND t.status='aktif'
              WHERE t.id_invoice = ? AND t.id_pemasukan <> ? AND jd.id_akun = ?", [$inv['id_invoice'], $ex, $akunPpn]);
        $sisaPpn = max(0.0, round($ppnInv - $sudahPpn, 2));
        $sisaBayar = round($total - $sudahBayar, 2);
        $ppn = ($nominal + 0.004 >= $sisaBayar) ? $sisaPpn : round($nominal * $ppnInv / $total, 2);
        $ppn = min($ppn, $sisaPpn, $nominal);
        return [round($nominal - $ppn, 2), round($ppn, 2)];
    }
}
