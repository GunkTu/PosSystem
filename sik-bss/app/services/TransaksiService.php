<?php
namespace App\Services;

use App\Core\Database;

/**
 * Pemasukan & pengeluaran. Semua perubahan saldo, status invoice, dan jurnal
 * berjalan dalam satu transaksi database (BR-01).
 */
class TransaksiService
{
    private static function nomor(string $prefix, string $tanggal, string $table, string $col): string
    {
        $p = $prefix . '-' . date('Ym', strtotime($tanggal)) . '-';
        $last = Database::val("SELECT $col FROM $table WHERE $col LIKE ? ORDER BY $col DESC LIMIT 1 FOR UPDATE", [$p . '%']);
        return $p . str_pad((string)(($last ? (int)substr($last, -4) : 0) + 1), 4, '0', STR_PAD_LEFT);
    }

    private static function saldo(int $idKb, float $delta): void
    {
        Database::exec('UPDATE kas_bank SET saldo = saldo + ? WHERE id_kas_bank = ?', [$delta, $idKb]);
    }

    private static function cekSaldo(array $idKbs): void
    {
        foreach (array_unique($idKbs) as $id) {
            $s = Database::one('SELECT nama_kas_bank, saldo FROM kas_bank WHERE id_kas_bank = ?', [$id]);
            if ($s && (float)$s['saldo'] < -0.004) {
                throw new \DomainException('Saldo ' . $s['nama_kas_bank'] . ' tidak mencukupi (saldo tidak boleh negatif).');
            }
        }
    }

    private static function kasBank(int $id): array
    {
        $kb = Database::one('SELECT * FROM kas_bank WHERE id_kas_bank = ? FOR UPDATE', [$id]);
        if (!$kb) throw new \DomainException('Kas/Bank tidak ditemukan.');
        if ($kb['status'] !== 'aktif') throw new \DomainException('Kas/Bank ' . $kb['nama_kas_bank'] . ' tidak aktif.');
        return $kb;
    }

    private static function validTanggalNominal(array $d): void
    {
        if (!valid_date($d['tanggal'] ?? null)) throw new \DomainException('Tanggal tidak valid.');
        if (($d['nominal'] ?? 0) <= 0) throw new \DomainException('Nominal harus lebih dari 0.');
        if ($d['nominal'] > 9999999999999) throw new \DomainException('Nominal terlalu besar.');
        if (mb_strlen($d['keterangan'] ?? '') > 100) throw new \DomainException('Keterangan maksimal 100 karakter.');
    }

    private static function transaksi(callable $fn)
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try { $r = $fn(); $pdo->commit(); return $r; }
        catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }

    // ======================= PEMASUKAN =======================

    private static function terapkanPemasukan(array $row): void
    {
        $kb = self::kasBank((int)$row['id_kas_bank']);
        $akun = Database::one('SELECT * FROM akun WHERE id_akun = ?', [$row['id_akun']]);
        if (!$akun || $akun['status'] !== 'aktif') throw new \DomainException('Akun pendapatan tidak valid atau tidak aktif.');

        $pendapatan = $nominal = (float)$row['nominal_pembayaran'];
        $ppn = 0.0;
        if ($row['id_invoice']) {
            if ($akun['tipe_akun'] !== 'pendapatan') throw new \DomainException('Pembayaran invoice harus ke akun bertipe pendapatan.');
            $inv = Database::one('SELECT * FROM invoice WHERE id_invoice = ? FOR UPDATE', [$row['id_invoice']]);
            if (!$inv) throw new \DomainException('Invoice tidak ditemukan.');
            if ($inv['status_invoice'] === 'batal') throw new \DomainException('Invoice sudah dibatalkan.');
            $lain = (float)Database::val("SELECT COALESCE(SUM(nominal_pembayaran),0) FROM transaksi_pemasukan WHERE id_invoice=? AND status='aktif' AND id_pemasukan<>?", [$inv['id_invoice'], $row['id_pemasukan']]);
            $sisa = round((float)$inv['total'] - $lain, 2);
            if ($nominal > $sisa + 0.004) {
                throw new \DomainException('Nominal melebihi sisa tagihan invoice (' . rupiah($sisa) . ').');
            }
            [$pendapatan, $ppn] = InvoiceService::pecahPpn($inv, $nominal, (int)$row['id_pemasukan']);
        } elseif (!($akun['tipe_akun'] === 'pendapatan' || ($akun['tipe_akun'] === 'modal' && $akun['saldo_normal'] === 'kredit'))) {
            throw new \DomainException('Akun harus bertipe pendapatan atau modal (setoran modal).');
        }

        self::saldo((int)$row['id_kas_bank'], $nominal);
        $akunPpn = (int)Database::val("SELECT id_akun FROM akun WHERE kode_akun='2-101'");
        JurnalService::buat($row['tanggal_pembayaran'], $row['no_bukti_pemasukan'], $row['keterangan_pembayaran'] ?: 'Pemasukan', 'pemasukan', (int)$row['id_pemasukan'], [
            ['akun' => (int)$kb['id_akun'], 'debit' => $nominal],
            ['akun' => (int)$row['id_akun'], 'kredit' => $pendapatan],
            $ppn > 0 ? ['akun' => $akunPpn, 'kredit' => $ppn] : null,
        ]);
    }

    private static function balikPemasukan(array $row): void
    {
        self::saldo((int)$row['id_kas_bank'], -(float)$row['nominal_pembayaran']);
        JurnalService::batalkan('pemasukan', (int)$row['id_pemasukan']);
    }

    /** $d: id_invoice(?), id_kas_bank, id_akun, tanggal, keterangan, nominal */
    public static function simpanPemasukan(array $d, int $userId): int
    {
        self::validTanggalNominal($d);
        return self::transaksi(function () use ($d, $userId) {
            $id = Database::insert('transaksi_pemasukan', [
                'id_kas_bank' => $d['id_kas_bank'], 'id_invoice' => $d['id_invoice'] ?: null, 'id_akun' => $d['id_akun'],
                'no_bukti_pemasukan' => self::nomor('BKM', $d['tanggal'], 'transaksi_pemasukan', 'no_bukti_pemasukan'),
                'tanggal_pembayaran' => $d['tanggal'], 'keterangan_pembayaran' => $d['keterangan'],
                'nominal_pembayaran' => $d['nominal'], 'created_by' => $userId,
            ]);
            $row = Database::one('SELECT * FROM transaksi_pemasukan WHERE id_pemasukan = ?', [$id]);
            self::terapkanPemasukan($row);
            if ($row['id_invoice']) InvoiceService::hitungStatus((int)$row['id_invoice']);
            self::cekSaldo([(int)$row['id_kas_bank']]);
            return $id;
        });
    }

    public static function ubahPemasukan(int $id, array $d): void
    {
        self::validTanggalNominal($d);
        self::transaksi(function () use ($id, $d) {
            $old = Database::one('SELECT * FROM transaksi_pemasukan WHERE id_pemasukan = ? FOR UPDATE', [$id]);
            if (!$old) throw new \DomainException('Transaksi tidak ditemukan.');
            if ($old['status'] !== 'aktif') throw new \DomainException('Transaksi yang dibatalkan tidak dapat diubah.');
            self::balikPemasukan($old);
            Database::update('transaksi_pemasukan', [
                'id_kas_bank' => $d['id_kas_bank'], 'id_akun' => $d['id_akun'], 'tanggal_pembayaran' => $d['tanggal'],
                'keterangan_pembayaran' => $d['keterangan'], 'nominal_pembayaran' => $d['nominal'],
            ], 'id_pemasukan', $id);
            $new = Database::one('SELECT * FROM transaksi_pemasukan WHERE id_pemasukan = ?', [$id]);
            self::terapkanPemasukan($new);
            if ($new['id_invoice']) InvoiceService::hitungStatus((int)$new['id_invoice']);
            self::cekSaldo([(int)$old['id_kas_bank'], (int)$new['id_kas_bank']]);
        });
    }

    public static function batalPemasukan(int $id): void
    {
        self::transaksi(function () use ($id) {
            $row = Database::one('SELECT * FROM transaksi_pemasukan WHERE id_pemasukan = ? FOR UPDATE', [$id]);
            if (!$row) throw new \DomainException('Transaksi tidak ditemukan.');
            if ($row['status'] !== 'aktif') throw new \DomainException('Transaksi sudah dibatalkan.');
            self::balikPemasukan($row);
            Database::update('transaksi_pemasukan', ['status' => 'batal'], 'id_pemasukan', $id);
            if ($row['id_invoice']) InvoiceService::hitungStatus((int)$row['id_invoice']);
            self::cekSaldo([(int)$row['id_kas_bank']]);
        });
    }

    // ======================= PENGELUARAN =======================

    private static function terapkanPengeluaran(array $row): void
    {
        $kb = self::kasBank((int)$row['id_kas_bank']);
        $akun = Database::one('SELECT * FROM akun WHERE id_akun = ?', [$row['id_akun']]);
        $ok = $akun && $akun['status'] === 'aktif'
            && ($akun['tipe_akun'] === 'beban' || ($akun['tipe_akun'] === 'modal' && $akun['saldo_normal'] === 'debit'));
        if (!$ok) throw new \DomainException('Akun harus bertipe beban (atau Prive) dan aktif.');
        $n = (float)$row['nominal_pengeluaran'];
        self::saldo((int)$row['id_kas_bank'], -$n);
        JurnalService::buat($row['tanggal_pengeluaran'], $row['no_bukti_pengeluaran'], $row['keterangan_pengeluaran'] ?: 'Pengeluaran', 'pengeluaran', (int)$row['id_pengeluaran'], [
            ['akun' => (int)$row['id_akun'], 'debit' => $n],
            ['akun' => (int)$kb['id_akun'], 'kredit' => $n],
        ]);
    }

    private static function balikPengeluaran(array $row): void
    {
        self::saldo((int)$row['id_kas_bank'], (float)$row['nominal_pengeluaran']);
        JurnalService::batalkan('pengeluaran', (int)$row['id_pengeluaran']);
    }

    /** $d: id_kas_bank, id_akun, tanggal, keterangan, nominal, file_bukti(?) */
    public static function simpanPengeluaran(array $d, int $userId): int
    {
        self::validTanggalNominal($d);
        return self::transaksi(function () use ($d, $userId) {
            $id = Database::insert('transaksi_pengeluaran', [
                'id_kas_bank' => $d['id_kas_bank'], 'id_akun' => $d['id_akun'],
                'no_bukti_pengeluaran' => self::nomor('BKK', $d['tanggal'], 'transaksi_pengeluaran', 'no_bukti_pengeluaran'),
                'tanggal_pengeluaran' => $d['tanggal'], 'keterangan_pengeluaran' => $d['keterangan'],
                'nominal_pengeluaran' => $d['nominal'], 'file_bukti' => $d['file_bukti'] ?? null, 'created_by' => $userId,
            ]);
            self::terapkanPengeluaran(Database::one('SELECT * FROM transaksi_pengeluaran WHERE id_pengeluaran = ?', [$id]));
            self::cekSaldo([(int)$d['id_kas_bank']]);
            return $id;
        });
    }

    public static function ubahPengeluaran(int $id, array $d): void
    {
        self::validTanggalNominal($d);
        self::transaksi(function () use ($id, $d) {
            $old = Database::one('SELECT * FROM transaksi_pengeluaran WHERE id_pengeluaran = ? FOR UPDATE', [$id]);
            if (!$old) throw new \DomainException('Transaksi tidak ditemukan.');
            if ($old['status'] !== 'aktif') throw new \DomainException('Transaksi yang dibatalkan tidak dapat diubah.');
            self::balikPengeluaran($old);
            $upd = ['id_kas_bank' => $d['id_kas_bank'], 'id_akun' => $d['id_akun'], 'tanggal_pengeluaran' => $d['tanggal'],
                    'keterangan_pengeluaran' => $d['keterangan'], 'nominal_pengeluaran' => $d['nominal']];
            if (!empty($d['file_bukti'])) $upd['file_bukti'] = $d['file_bukti'];
            Database::update('transaksi_pengeluaran', $upd, 'id_pengeluaran', $id);
            self::terapkanPengeluaran(Database::one('SELECT * FROM transaksi_pengeluaran WHERE id_pengeluaran = ?', [$id]));
            self::cekSaldo([(int)$old['id_kas_bank'], (int)$d['id_kas_bank']]);
        });
    }

    public static function batalPengeluaran(int $id): void
    {
        self::transaksi(function () use ($id) {
            $row = Database::one('SELECT * FROM transaksi_pengeluaran WHERE id_pengeluaran = ? FOR UPDATE', [$id]);
            if (!$row) throw new \DomainException('Transaksi tidak ditemukan.');
            if ($row['status'] !== 'aktif') throw new \DomainException('Transaksi sudah dibatalkan.');
            self::balikPengeluaran($row);
            Database::update('transaksi_pengeluaran', ['status' => 'batal'], 'id_pengeluaran', $id);
        });
    }
}
