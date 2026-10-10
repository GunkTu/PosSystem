<?php
/**
 * Uji end-to-end (black box + aturan bisnis) terhadap server yang berjalan.
 *   php -S 127.0.0.1:8080 -t public public/index.php     (terminal 1)
 *   php tests/e2e.php [http://127.0.0.1:8080]             (terminal 2)
 * PERINGATAN: skrip ini MERESET database sik_bss (schema.sql + seed.sql).
 */
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$root = dirname(__DIR__);
$cfg = require $root . '/app/config/database.php';
$mysql = getenv('MYSQL_BIN') ?: 'C:/xampp/mysql/bin/mysql.exe';
$pass = $cfg['pass'] !== '' ? ' -p' . escapeshellarg($cfg['pass']) : '';
foreach (['schema', 'seed'] as $f) {
    exec('"' . $mysql . '" -h' . $cfg['host'] . ' -u' . $cfg['user'] . $pass . ' < ' . escapeshellarg($root . "/database/$f.sql") . ' 2>&1', $o, $rc);
    if ($rc !== 0) { fwrite(STDERR, "Gagal reset DB: " . implode("\n", $o) . "\n"); exit(2); }
}
$pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['name']};charset=utf8mb4", $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$q = fn($sql, $p = []) => (function () use ($pdo, $sql, $p) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();

class Client {
    public string $jar; public ?string $token = null; public array $last = [];
    function __construct(public string $base) { $this->jar = tempnam(sys_get_temp_dir(), 'cj'); }
    function req(string $method, string $path, array $data = [], bool $follow = false): array {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => $follow]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $flat = [];
            foreach ($data as $k => $v) {
                if (is_array($v)) foreach ($v as $i => $x) $flat["{$k}[{$i}]"] = $x; else $flat[$k] = $v;
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $flat);   // array => multipart (mendukung file)
        }
        $raw = curl_exec($ch);
        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $r = ['code' => curl_getinfo($ch, CURLINFO_HTTP_CODE), 'head' => substr($raw, 0, $hs), 'body' => substr($raw, $hs)];
        curl_close($ch);
        return $this->last = $r;
    }
    function get(string $p, bool $f = true) { return $this->req('GET', $p, [], $f); }
    function csrf(): string {
        $r = $this->get('/profil/password');
        preg_match('/name="_token" value="([a-f0-9]+)"/', $r['body'], $m);
        return $this->token = $m[1] ?? '';
    }
    function post(string $p, array $d = [], bool $f = true) {
        $d['_token'] ??= $this->token ?? $this->csrf();
        return $this->req('POST', $p, $d, $f);
    }
}

$pass = $fail = 0;
function ok(bool $cond, string $name, string $extra = ''): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  [OK]   $name\n"; } else { $fail++; echo "  [GAGAL] $name $extra\n"; }
}
function section(string $s): void { echo "\n== $s ==\n"; }
$noErr = fn(string $b) => !preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Stack trace)/', $b);
$near = fn($a, $b) => abs((float)$a - (float)$b) < 0.005;

// ------------------------------------------------------------------ Login
section('Login & otorisasi');
$a = new Client($base);
$r = $a->get('/login');
preg_match('/name="_token" value="([a-f0-9]+)"/', $r['body'], $m);
$tok = $m[1];
$r = $a->post('/login', ['_token' => $tok, 'username' => '', 'password' => '']);
ok(str_contains($r['body'], 'harus diisi'), 'Login kosong -> pesan "harus diisi"');
$r = $a->post('/login', ['_token' => $tok, 'username' => 'admin', 'password' => 'salah']);
ok(str_contains($r['body'], 'salah'), 'Login password salah -> pesan gagal');
$r = $a->req('POST', '/login', ['username' => 'admin', 'password' => 'admin123']);
ok($r['code'] === 403, 'POST tanpa CSRF ditolak (403)');
$r = $a->post('/login', ['_token' => $tok, 'username' => 'admin', 'password' => 'admin123'], false);
ok($r['code'] === 302 && str_contains($r['head'], '/dashboard'), 'Login admin valid -> redirect dashboard');
$a->token = null; $a->csrf();
$r = $a->get('/dashboard');
ok($r['code'] === 200 && $noErr($r['body']) && str_contains($r['body'], 'Total Pemasukan'), 'Dashboard admin tampil');

$p = new Client($base);
$r = $p->get('/login'); preg_match('/name="_token" value="([a-f0-9]+)"/', $r['body'], $m);
$p->post('/login', ['_token' => $m[1], 'username' => 'pimpinan', 'password' => 'pimpinan123'], false);
$p->csrf();
$r = $p->get('/dashboard');
ok($r['code'] === 200 && str_contains($r['body'], 'Laba'), 'Dashboard pimpinan tampil dengan kartu Laba/Rugi');
foreach (['/invoice', '/akun', '/pelanggan', '/kas-bank', '/pemasukan', '/pengeluaran', '/pengaturan/kop', '/invoice/create'] as $u) {
    ok($p->get($u)['code'] === 403, "Pimpinan membuka $u -> 403");
}
ok($p->post('/akun/store', ['kode_akun' => 'X', 'nama_akun' => 'X', 'tipe_akun' => 'aset'])['code'] === 403, 'Pimpinan POST /akun/store -> 403');
foreach (['/laporan', '/riwayat'] as $u) ok($p->get($u)['code'] === 200, "Pimpinan membuka $u -> 200");
$anon = new Client($base);
$r = $anon->get('/invoice');
ok(str_contains($r['body'], 'Login') || $r['code'] === 200 && str_contains($r['body'], 'Username'), 'Tanpa login diarahkan ke halaman login');

// ------------------------------------------------------------------ Master
section('Data master');
$r = $a->post('/akun/store', ['kode_akun' => '5-999', 'nama_akun' => 'Beban Uji', 'tipe_akun' => 'beban', 'kategori_akun' => 'Uji']);
ok($q("SELECT COUNT(*) FROM akun WHERE kode_akun='5-999'") == 1, 'Tambah akun');
$r = $a->post('/akun/store', ['kode_akun' => '5-999', 'nama_akun' => 'Duplikat', 'tipe_akun' => 'beban']);
ok($q("SELECT COUNT(*) FROM akun WHERE kode_akun='5-999'") == 1 && str_contains($r['body'], 'sudah digunakan'), 'Kode akun duplikat ditolak');
$idAkunUji = $q("SELECT id_akun FROM akun WHERE kode_akun='5-999'");
$a->post("/akun/update/$idAkunUji", ['kode_akun' => '5-999', 'nama_akun' => 'Beban Uji 2', 'tipe_akun' => 'beban']);
ok($q("SELECT nama_akun FROM akun WHERE id_akun=?", [$idAkunUji]) === 'Beban Uji 2', 'Ubah akun');
$r = $a->get('/akun?q=Uji%202');
ok(str_contains($r['body'], 'Beban Uji 2') && !str_contains($r['body'], 'Pendapatan Freight'), 'Pencarian akun');
$a->post("/akun/status/$idAkunUji");
ok($q("SELECT status FROM akun WHERE id_akun=?", [$idAkunUji]) === 'nonaktif', 'Update status akun -> nonaktif');
$a->post("/akun/status/$idAkunUji");

$a->post('/pelanggan/store', ['kode_pelanggan' => 'C001', 'nama_pelanggan' => 'PT Maju Jaya', 'alamat' => 'Jl. Raya Kuta 1', 'telepon' => '0361123', 'email' => 'x@maju.id']);
$idP = $q("SELECT id_pelanggan FROM pelanggan WHERE kode_pelanggan='C001'");
ok((bool)$idP, 'Tambah pelanggan');
$r = $a->post('/pelanggan/store', ['kode_pelanggan' => 'C002', 'nama_pelanggan' => 'Salah Email', 'email' => 'bukan-email']);
ok($q("SELECT COUNT(*) FROM pelanggan") == 1, 'Email tidak valid ditolak');
$a->post("/pelanggan/update/$idP", ['kode_pelanggan' => 'C001', 'nama_pelanggan' => 'PT Maju Jaya Abadi', 'alamat' => 'Jl. Raya Kuta 1']);
ok($q("SELECT nama_pelanggan FROM pelanggan WHERE id_pelanggan=?", [$idP]) === 'PT Maju Jaya Abadi', 'Ubah pelanggan');

$a->post('/kas-bank/update/1', ['id_akun' => 1, 'kode_kas_bank' => 'KAS-01', 'nama_kas_bank' => 'Kas Kantor', 'jenis_kas_bank' => 'kas', 'saldo_awal' => '1000000', 'tanggal_saldo' => '2026-10-01']);
$a->post('/kas-bank/update/2', ['id_akun' => 2, 'kode_kas_bank' => 'BANK-01', 'nama_kas_bank' => 'Bank Utama', 'jenis_kas_bank' => 'bank', 'no_rekening' => '123', 'saldo_awal' => '5000000', 'tanggal_saldo' => '2026-10-01']);
ok($near($q("SELECT saldo FROM kas_bank WHERE id_kas_bank=1"), 1000000) && $near($q("SELECT saldo FROM kas_bank WHERE id_kas_bank=2"), 5000000), 'Saldo awal kas & bank tersimpan');
ok($q("SELECT COUNT(*) FROM jurnal WHERE sumber='saldo_awal' AND status='aktif'") == 2, 'Jurnal saldo awal terbentuk (2)');
$r = $a->get('/kas-bank/mutasi/2');
ok($r['code'] === 200 && $noErr($r['body']) && str_contains($r['body'], 'Saldo awal'), 'Halaman mutasi kas/bank');

// ------------------------------------------------------------------ Invoice
section('Invoice (multi-item + PPN)');
$items = ['item_jenis' => ['freight', 'gudang'], 'item_deskripsi' => ['Freight Surabaya-Denpasar', 'Sewa gudang'], 'item_qty' => ['2', '1'],
          'item_satuan' => ['kontainer', 'bulan'], 'item_harga' => ['1000000', '500000']];
$r = $a->post('/invoice/store', ['id_pelanggan' => $idP, 'tanggal' => '2026-10-05', 'jatuh_tempo' => '2026-11-04', 'ppn_persen' => '11', 'keterangan' => 'Uji'] + $items);
$inv = $pdo->query("SELECT * FROM invoice ORDER BY id_invoice DESC LIMIT 1")->fetch();
ok($inv && $inv['no_invoice'] === 'INV-202610-0001', 'Nomor invoice otomatis INV-202610-0001', $inv['no_invoice'] ?? '');
ok($inv && $near($inv['subtotal'], 2500000) && $near($inv['ppn_nominal'], 275000) && $near($inv['total'], 2775000), 'Subtotal 2.500.000 + PPN 11% 275.000 = 2.775.000');
ok($q("SELECT COUNT(*) FROM invoice_item WHERE id_invoice=?", [$inv['id_invoice']]) == 2, 'Dua item tersimpan');
$idInv = (int)$inv['id_invoice'];
$r = $a->post('/invoice/store', ['id_pelanggan' => $idP, 'tanggal' => '2026-10-05', 'jatuh_tempo' => '2026-10-01', 'ppn_persen' => '11'] + $items);
ok($q("SELECT COUNT(*) FROM invoice") == 1, 'Jatuh tempo sebelum tanggal ditolak');
$r = $a->post('/invoice/store', ['id_pelanggan' => $idP, 'tanggal' => '2026-10-05', 'jatuh_tempo' => '2026-11-05', 'ppn_persen' => '11', 'item_deskripsi' => [''], 'item_qty' => [''], 'item_harga' => [''], 'item_jenis' => ['freight'], 'item_satuan' => ['']]);
ok($q("SELECT COUNT(*) FROM invoice") == 1, 'Invoice tanpa item ditolak');
$r = $a->post('/invoice/store', ['id_pelanggan' => $idP, 'tanggal' => '2026-11-02', 'jatuh_tempo' => '2026-12-02', 'ppn_persen' => '0'] + $items);
ok($q("SELECT no_invoice FROM invoice ORDER BY id_invoice DESC LIMIT 1") === 'INV-202611-0001' && $near($q("SELECT total FROM invoice ORDER BY id_invoice DESC LIMIT 1"), 2500000), 'Invoice PPN 0% & nomor bulan baru');
$idInv2 = (int)$q("SELECT MAX(id_invoice) FROM invoice");
$r = $a->get("/invoice/$idInv");
ok($r['code'] === 200 && $noErr($r['body']) && str_contains($r['body'], 'Catat Pembayaran'), 'Detail invoice');
$r = $a->get("/invoice/$idInv/cetak");
ok(str_starts_with($r['body'], '%PDF'), 'Cetak invoice menghasilkan PDF');
$r = $a->get("/invoice/$idInv/edit");
ok($r['code'] === 200 && $noErr($r['body']), 'Form ubah invoice');
$r = $a->get('/invoice?q=INV-202610&status=belum_lunas');
ok(str_contains($r['body'], 'INV-202610-0001') && !str_contains($r['body'], 'INV-202611-0001'), 'Pencarian & filter invoice');
$a->post("/invoice/status/$idInv2");
ok($q("SELECT status_invoice FROM invoice WHERE id_invoice=?", [$idInv2]) === 'batal', 'Invoice dibatalkan (tanpa pembayaran)');
$a->post("/invoice/status/$idInv2");
ok($q("SELECT status_invoice FROM invoice WHERE id_invoice=?", [$idInv2]) === 'belum_lunas', 'Invoice diaktifkan kembali');

// ------------------------------------------------------------------ Pemasukan
section('Transaksi pemasukan');
$bayar = fn($nom, $tgl = '2026-10-10', $extra = []) => $a->post('/pemasukan/store', ['id_invoice' => $idInv, 'id_kas_bank' => 2, 'id_akun' => $q("SELECT id_akun FROM akun WHERE kode_akun='4-101'"), 'tanggal' => $tgl, 'nominal' => $nom, 'keterangan' => 'Bayar'] + $extra);
$bayar('1000000');
ok($q("SELECT COUNT(*) FROM transaksi_pemasukan") == 1, 'Pemasukan 1.000.000 tersimpan');
$inv = $pdo->query("SELECT * FROM invoice WHERE id_invoice=$idInv")->fetch();
ok($inv['status_invoice'] === 'sebagian' && $near($inv['total_terbayar'], 1000000), 'Status invoice -> sebagian');
ok($near($q("SELECT saldo FROM kas_bank WHERE id_kas_bank=2"), 6000000), 'Saldo bank bertambah menjadi 6.000.000');
ok($q("SELECT no_bukti_pemasukan FROM transaksi_pemasukan LIMIT 1") === 'BKM-202610-0001', 'Nomor bukti BKM-202610-0001');
$ppn1 = (float)$q("SELECT jd.kredit FROM jurnal_detail jd JOIN akun a ON a.id_akun=jd.id_akun JOIN jurnal j ON j.id_jurnal=jd.id_jurnal WHERE a.kode_akun='2-101' AND j.sumber='pemasukan' AND j.status='aktif' LIMIT 1");
ok($near($ppn1, round(1000000 * 275000 / 2775000, 2)), 'Jurnal memecah PPN proporsional', "ppn=$ppn1");
$bayar('2000000');
ok($q("SELECT COUNT(*) FROM transaksi_pemasukan") == 1, 'Pembayaran melebihi sisa tagihan ditolak');
$bayar('0');
$bayar('-5');
ok($q("SELECT COUNT(*) FROM transaksi_pemasukan") == 1, 'Nominal 0 / negatif ditolak');
$bayar('1775000', '2026-10-12');
$inv = $pdo->query("SELECT * FROM invoice WHERE id_invoice=$idInv")->fetch();
ok($inv['status_invoice'] === 'lunas' && $near($inv['total_terbayar'], 2775000), 'Pelunasan -> status lunas');
ok($near($q("SELECT saldo FROM kas_bank WHERE id_kas_bank=2"), 7775000), 'Saldo bank 7.775.000');
$totPpn = (float)$q("SELECT SUM(jd.kredit) FROM jurnal_detail jd JOIN akun a ON a.id_akun=jd.id_akun JOIN jurnal j ON j.id_jurnal=jd.id_jurnal WHERE a.kode_akun='2-101' AND j.sumber='pemasukan' AND j.status='aktif'");
ok($near($totPpn, 275000), 'Total PPN diakui tepat 275.000 setelah pelunasan', "ppn=$totPpn");
$bayar('1');
ok($q("SELECT COUNT(*) FROM transaksi_pemasukan") == 2, 'Invoice lunas tidak bisa dibayar lagi');
$r = $a->post("/invoice/status/$idInv");
ok($q("SELECT status_invoice FROM invoice WHERE id_invoice=?", [$idInv]) === 'lunas', 'Invoice berpembayaran tidak bisa dibatalkan');
$a->post("/invoice/update/$idInv", ['id_pelanggan' => $idP, 'tanggal' => '2026-10-05', 'jatuh_tempo' => '2026-11-30', 'keterangan' => 'Diubah'] + ['item_jenis' => ['freight'], 'item_deskripsi' => ['X'], 'item_qty' => ['1'], 'item_satuan' => [''], 'item_harga' => ['1']]);
$inv = $pdo->query("SELECT * FROM invoice WHERE id_invoice=$idInv")->fetch();
ok($near($inv['total'], 2775000) && $inv['jatuh_tempo'] === '2026-11-30' && $inv['keterangan'] === 'Diubah' && $q("SELECT COUNT(*) FROM invoice_item WHERE id_invoice=?", [$idInv]) == 2, 'Invoice berpembayaran: total & item terkunci, jatuh tempo bisa diubah');

// ubah & batal pemasukan
$idM1 = (int)$q("SELECT id_pemasukan FROM transaksi_pemasukan ORDER BY id_pemasukan LIMIT 1");
$a->post("/pemasukan/update/$idM1", ['id_kas_bank' => 1, 'id_akun' => $q("SELECT id_akun FROM akun WHERE kode_akun='4-101'"), 'tanggal' => '2026-10-10', 'nominal' => '900000', 'keterangan' => 'Koreksi']);
ok($near($q("SELECT saldo FROM kas_bank WHERE id_kas_bank=1"), 1900000) && $near($q("SELECT saldo FROM kas_bank WHERE id_kas_bank=2"), 6775000), 'Ubah pemasukan: saldo kedua kas/bank dikoreksi');
$inv = $pdo->query("SELECT * FROM invoice WHERE id_invoice=$idInv")->fetch();
ok($inv['status_invoice'] === 'sebagian' && $near($inv['total_terbayar'], 2675000), 'Ubah pemasukan: status invoice dihitung ulang');
$totPpn = (float)$q("SELECT SUM(jd.kredit) FROM jurnal_detail jd JOIN akun a ON a.id_akun=jd.id_akun JOIN jurnal j ON j.id_jurnal=jd.id_jurnal WHERE a.kode_akun='2-101' AND j.sumber='pemasukan' AND j.status='aktif'");
ok($totPpn > 0 && $totPpn < 275000, 'PPN dihitung ulang setelah ubah', "ppn=$totPpn");
$a->post("/pemasukan/batal/$idM1");
ok($q("SELECT status FROM transaksi_pemasukan WHERE id_pemasukan=?", [$idM1]) === 'batal' && $near($q("SELECT saldo FROM kas_bank WHERE id_kas_bank=1"), 1000000), 'Batal pemasukan: saldo dikoreksi');
$inv = $pdo->query("SELECT * FROM invoice WHERE id_invoice=$idInv")->fetch();
ok($inv['status_invoice'] === 'sebagian' && $near($inv['total_terbayar'], 1775000), 'Batal pemasukan: status invoice sebagian');
$bayar('1000000', '2026-10-13');
$inv = $pdo->query("SELECT * FROM invoice WHERE id_invoice=$idInv")->fetch();
ok($inv['status_invoice'] === 'lunas', 'Bayar ulang sisa -> lunas lagi');
$totPpn = (float)$q("SELECT SUM(jd.kredit) FROM jurnal_detail jd JOIN akun a ON a.id_akun=jd.id_akun JOIN jurnal j ON j.id_jurnal=jd.id_jurnal WHERE a.kode_akun='2-101' AND j.sumber='pemasukan' AND j.status='aktif'");
ok($near($totPpn, 275000), 'Total PPN kembali tepat 275.000', "ppn=$totPpn");

// pendapatan lain & setoran modal
$a->post('/pemasukan/store', ['id_kas_bank' => 1, 'id_akun' => $q("SELECT id_akun FROM akun WHERE kode_akun='4-103'"), 'tanggal' => '2026-10-14', 'nominal' => '250000', 'keterangan' => 'Pendapatan lain']);
ok($q("SELECT COUNT(*) FROM transaksi_pemasukan WHERE id_invoice IS NULL AND status='aktif'") == 1, 'Pendapatan lain tanpa invoice');
$a->post('/pemasukan/store', ['id_kas_bank' => 1, 'id_akun' => $q("SELECT id_akun FROM akun WHERE kode_akun='5-101'"), 'tanggal' => '2026-10-14', 'nominal' => '100', 'keterangan' => 'salah akun']);
ok($q("SELECT COUNT(*) FROM transaksi_pemasukan WHERE id_invoice IS NULL AND status='aktif'") == 1, 'Pemasukan ke akun beban ditolak');

// ------------------------------------------------------------------ Pengeluaran
section('Transaksi pengeluaran');
$bebanId = $q("SELECT id_akun FROM akun WHERE kode_akun='5-101'");
$kasSebelum = (float)$q("SELECT saldo FROM kas_bank WHERE id_kas_bank=1");
$a->post('/pengeluaran/store', ['id_kas_bank' => 1, 'id_akun' => $bebanId, 'tanggal' => '2026-10-15', 'nominal' => '300000', 'keterangan' => 'Bensin truk']);
ok($near($q("SELECT saldo FROM kas_bank WHERE id_kas_bank=1"), $kasSebelum - 300000), 'Pengeluaran 300.000 mengurangi saldo kas');
ok($q("SELECT no_bukti_pengeluaran FROM transaksi_pengeluaran LIMIT 1") === 'BKK-202610-0001', 'Nomor bukti BKK-202610-0001');
$a->post('/pengeluaran/store', ['id_kas_bank' => 1, 'id_akun' => $bebanId, 'tanggal' => '2026-10-15', 'nominal' => '99999999', 'keterangan' => 'terlalu besar']);
ok($q("SELECT COUNT(*) FROM transaksi_pengeluaran") == 1, 'Pengeluaran melebihi saldo ditolak');
ok((float)$q("SELECT saldo FROM kas_bank WHERE id_kas_bank=1") >= 0, 'Saldo kas tidak negatif');
$a->post('/pengeluaran/store', ['id_kas_bank' => 1, 'id_akun' => $q("SELECT id_akun FROM akun WHERE kode_akun='4-101'"), 'tanggal' => '2026-10-15', 'nominal' => '10', 'keterangan' => 'akun salah']);
ok($q("SELECT COUNT(*) FROM transaksi_pengeluaran") == 1, 'Pengeluaran ke akun pendapatan ditolak');
// unggah bukti
$png = tempnam(sys_get_temp_dir(), 'png') . '.png';
imagepng(imagecreatetruecolor(20, 20), $png);
$a->post('/pengeluaran/store', ['id_kas_bank' => 2, 'id_akun' => $q("SELECT id_akun FROM akun WHERE kode_akun='5-103'"), 'tanggal' => '2026-10-16', 'nominal' => '150000', 'keterangan' => 'Listrik', 'file_bukti' => new CURLFile($png, 'image/png', 'bukti.png')]);
$idK2 = (int)$q("SELECT MAX(id_pengeluaran) FROM transaksi_pengeluaran");
ok($q("SELECT file_bukti FROM transaksi_pengeluaran WHERE id_pengeluaran=?", [$idK2]) !== null, 'Bukti PNG terunggah');
$r = $a->get("/pengeluaran/$idK2/bukti");
ok($r['code'] === 200 && str_starts_with($r['body'], "\x89PNG"), 'Bukti dapat dibuka');
$txt = tempnam(sys_get_temp_dir(), 'txt') . '.php'; file_put_contents($txt, '<?php echo 1;');
$a->post('/pengeluaran/store', ['id_kas_bank' => 2, 'id_akun' => $bebanId, 'tanggal' => '2026-10-16', 'nominal' => '1000', 'keterangan' => 'file jahat', 'file_bukti' => new CURLFile($txt, 'image/png', 'x.php')]);
ok($q("SELECT COUNT(*) FROM transaksi_pengeluaran") == 2, 'Unggah file bukan JPG/PNG/PDF ditolak');
$a->post("/pengeluaran/update/$idK2", ['id_kas_bank' => 2, 'id_akun' => $q("SELECT id_akun FROM akun WHERE kode_akun='5-103'"), 'tanggal' => '2026-10-16', 'nominal' => '200000', 'keterangan' => 'Listrik koreksi']);
ok($near($q("SELECT nominal_pengeluaran FROM transaksi_pengeluaran WHERE id_pengeluaran=?", [$idK2]), 200000), 'Ubah pengeluaran');
$bankSebelum = (float)$q("SELECT saldo FROM kas_bank WHERE id_kas_bank=2");
$a->post("/pengeluaran/batal/$idK2");
ok($near($q("SELECT saldo FROM kas_bank WHERE id_kas_bank=2"), $bankSebelum + 200000) && $q("SELECT status FROM transaksi_pengeluaran WHERE id_pengeluaran=?", [$idK2]) === 'batal', 'Batal pengeluaran: saldo dikembalikan');
$a->post('/pengeluaran/store', ['id_kas_bank' => 1, 'id_akun' => $q("SELECT id_akun FROM akun WHERE kode_akun='3-102'"), 'tanggal' => '2026-10-17', 'nominal' => '50000', 'keterangan' => 'Prive pemilik']);
ok($q("SELECT COUNT(*) FROM transaksi_pengeluaran WHERE status='aktif'") == 2, 'Prive dapat dicatat sebagai pengeluaran');

// ------------------------------------------------------------------ Integritas
section('Integritas data');
ok($near($q("SELECT SUM(jd.debit)-SUM(jd.kredit) FROM jurnal_detail jd JOIN jurnal j ON j.id_jurnal=jd.id_jurnal WHERE j.status='aktif'"), 0), 'Total debit = total kredit (semua jurnal aktif)');
$unbalanced = $q("SELECT COUNT(*) FROM (SELECT j.id_jurnal FROM jurnal j JOIN jurnal_detail jd ON jd.id_jurnal=j.id_jurnal GROUP BY j.id_jurnal HAVING ABS(SUM(jd.debit)-SUM(jd.kredit))>0.001) t");
ok($unbalanced == 0, 'Setiap jurnal seimbang individual');
foreach ($pdo->query('SELECT * FROM kas_bank') as $kb) {
    $exp = (float)$kb['saldo_awal'] + (float)$q("SELECT COALESCE(SUM(nominal_pembayaran),0) FROM transaksi_pemasukan WHERE id_kas_bank=? AND status='aktif'", [$kb['id_kas_bank']])
         - (float)$q("SELECT COALESCE(SUM(nominal_pengeluaran),0) FROM transaksi_pengeluaran WHERE id_kas_bank=? AND status='aktif'", [$kb['id_kas_bank']]);
    ok($near($kb['saldo'], $exp), "Saldo {$kb['nama_kas_bank']} = saldo awal + mutasi", "db={$kb['saldo']} hitung=$exp");
}
$terbayar = (float)$q("SELECT COALESCE(SUM(nominal_pembayaran),0) FROM transaksi_pemasukan WHERE id_invoice=? AND status='aktif'", [$idInv]);
ok($near($q("SELECT total_terbayar FROM invoice WHERE id_invoice=?", [$idInv]), $terbayar), 'total_terbayar invoice sinkron dengan pemasukan aktif');

// nonaktifkan kas/bank
$a->post('/kas-bank/status/1');
$a->post('/pengeluaran/store', ['id_kas_bank' => 1, 'id_akun' => $bebanId, 'tanggal' => '2026-10-18', 'nominal' => '1000', 'keterangan' => 'ke kas nonaktif']);
ok($q("SELECT COUNT(*) FROM transaksi_pengeluaran") == 3, 'Transaksi ke kas/bank nonaktif ditolak');
$a->post('/kas-bank/status/1');

// ------------------------------------------------------------------ Laporan
section('Laporan keuangan');
$per = 'dari=2026-10-01&sampai=2026-10-31';
foreach (['laba_rugi' => 'LABA BERSIH', 'neraca' => 'SEIMBANG', 'arus_kas' => 'TOTAL KAS DAN BANK', 'perubahan_modal' => 'MODAL AKHIR', 'jurnal_umum' => 'TOTAL'] as $j => $needle) {
    $r = $a->get("/laporan/preview?jenis=$j&$per");
    ok($r['code'] === 200 && $noErr($r['body']) && str_contains($r['body'], $needle), "Preview laporan $j");
}
$r = $a->get("/laporan/preview?jenis=neraca&$per");
ok(!str_contains($r['body'], 'TIDAK SEIMBANG'), 'Neraca seimbang (Aset = Kewajiban + Modal)');
// hitung manual: pendapatan = 2.775.000 total terbayar inv - 275.000 PPN... (hanya pembayaran aktif) + 250.000 pendapatan lain
$pendTotal = (float)$q("SELECT SUM(jd.kredit-jd.debit) FROM jurnal_detail jd JOIN akun a ON a.id_akun=jd.id_akun JOIN jurnal j ON j.id_jurnal=jd.id_jurnal WHERE a.tipe_akun='pendapatan' AND j.status='aktif'");
ok($near($pendTotal, 2500000 + 250000), 'Total pendapatan = 2.500.000 (invoice tanpa PPN) + 250.000');
$r = $a->get("/laporan/preview?jenis=laba_rugi&$per");
ok(str_contains($r['body'], '2.750.000,00'), 'Laba rugi menampilkan total pendapatan 2.750.000,00');
ok(str_contains($a->get("/laporan/preview?jenis=laba_rugi&dari=2026-10-31&sampai=2026-10-01")['body'], 'tidak boleh setelah') , 'Periode terbalik ditolak');
$r = $a->get("/laporan/preview?jenis=ngawur&$per");
ok(str_contains($r['body'], 'tidak valid'), 'Jenis laporan tidak valid ditolak');
$h0 = (int)$q('SELECT COUNT(*) FROM riwayat_laporan');
$r = $a->get("/laporan/pdf?jenis=laba_rugi&$per&unduh=1");
ok(str_starts_with($r['body'], '%PDF') && str_contains($r['head'], 'attachment'), 'Unduh PDF laporan');
$r = $a->get("/laporan/pdf?jenis=arus_kas&$per");
ok(str_starts_with($r['body'], '%PDF'), 'Cetak PDF arus kas (landscape)');
$r = $a->get("/laporan/excel?jenis=jurnal_umum&$per");
ok(str_starts_with($r['body'], 'PK') && str_contains($r['head'], 'spreadsheetml'), 'Unduh Excel laporan');
ok((int)$q('SELECT COUNT(*) FROM riwayat_laporan') === $h0 + 3, 'Riwayat laporan bertambah 3');
$r = $a->get('/pemasukan/cetak?dari=2026-10-01&sampai=2026-10-31');
ok(str_starts_with($r['body'], '%PDF'), 'Cetak laporan pemasukan (PDF)');
$r = $a->get('/pengeluaran/cetak');
ok(str_starts_with($r['body'], '%PDF'), 'Cetak laporan pengeluaran (PDF)');
$r = $a->get('/riwayat');
ok($r['code'] === 200 && $noErr($r['body']) && str_contains($r['body'], 'Laporan Laba Rugi'), 'Halaman riwayat laporan');
$idR = (int)$q("SELECT id_laporan FROM riwayat_laporan WHERE format='pdf' ORDER BY id_laporan LIMIT 1");
ok(str_starts_with($a->get("/riwayat/$idR/buka")['body'], '%PDF'), 'Riwayat: buka/cetak ulang PDF');
ok(str_contains($a->get("/riwayat/$idR/unduh")['head'], 'attachment'), 'Riwayat: unduh ulang');
ok(str_starts_with($p->get("/laporan/pdf?jenis=laba_rugi&$per")['body'], '%PDF'), 'Pimpinan dapat mencetak laporan');
ok($p->get('/riwayat')['code'] === 200, 'Pimpinan melihat riwayat');
// perubahan modal: modal awal 6.000.000 - prive 50.000 + laba
$r = $a->get("/laporan/preview?jenis=perubahan_modal&$per");
ok(str_contains($r['body'], '6.000.000,00') && str_contains($r['body'], '(50.000,00)'), 'Perubahan modal memuat setoran modal 6.000.000 dan prive 50.000');

// ------------------------------------------------------------------ Kop
section('Pengaturan kop');
$r = $a->get('/pengaturan/kop');
ok($r['code'] === 200 && $noErr($r['body']), 'Halaman pengaturan kop');
$r = $a->get("/invoice/$idInv/cetak");
ok(str_starts_with($r['body'], '%PDF'), 'Invoice tercetak dengan kop placeholder');
$jpg = tempnam(sys_get_temp_dir(), 'jpg') . '.jpg';
$im = imagecreatetruecolor(1600, 220); imagefill($im, 0, 0, imagecolorallocate($im, 30, 77, 140)); imagejpeg($im, $jpg);
$a->post('/pengaturan/kop', ['mode_kop' => 'gambar', 'nama_perusahaan' => 'PT. Bali Surya Sentosa', 'alamat' => 'Tuban, Kuta, Badung', 'telepon' => '0361', 'email' => 'a@b.co', 'npwp' => '01.234',
    'bank_nama' => 'BCA', 'bank_no_rekening' => '123456', 'bank_atas_nama' => 'PT BSS', 'ttd_nama' => 'Windi', 'ttd_jabatan' => 'Keuangan', 'catatan_invoice' => 'Terima kasih', 'ppn_default' => '11',
    'header_image' => new CURLFile($jpg, 'image/jpeg', 'kop.jpg')]);
ok($q("SELECT mode_kop FROM pengaturan_perusahaan WHERE id=1") === 'gambar' && $q("SELECT header_image_path FROM pengaturan_perusahaan WHERE id=1") !== null, 'Kop gambar JPG tersimpan & mode gambar aktif');
$r = $a->get('/kop/header');
ok($r['code'] === 200 && str_starts_with($r['body'], "\xFF\xD8"), 'Gambar kop dapat diakses (login)');
ok($anon->get('/kop/header')['code'] !== 200 || str_contains($anon->last['body'], 'Username'), 'Gambar kop tidak terbuka tanpa login');
$r = $a->get("/invoice/$idInv/cetak");
ok(str_starts_with($r['body'], '%PDF') && strlen($r['body']) > 3000, 'Invoice PDF dengan kop gambar');
$r = $a->get("/laporan/preview?jenis=laba_rugi&$per");
ok(str_contains($r['body'], 'data:image/jpeg;base64'), 'Preview laporan memuat kop gambar');
$a->post('/pengaturan/kop', ['mode_kop' => 'gambar', 'nama_perusahaan' => 'PT. Bali Surya Sentosa', 'hapus_header' => '1', 'ppn_default' => '11']);
ok($q("SELECT mode_kop FROM pengaturan_perusahaan WHERE id=1") === 'gambar' && $q("SELECT header_image_path FROM pengaturan_perusahaan WHERE id=1") !== null, 'Mode gambar tanpa file kop ditolak');
$a->post('/pengaturan/kop', ['mode_kop' => 'sistem', 'nama_perusahaan' => 'PT. Bali Surya Sentosa', 'hapus_header' => '1', 'ppn_default' => '11']);
ok($q("SELECT header_image_path FROM pengaturan_perusahaan WHERE id=1") === null && $q("SELECT mode_kop FROM pengaturan_perusahaan WHERE id=1") === 'sistem', 'Hapus file kop & kembali ke mode sistem');
$a->post('/pengaturan/kop', ['mode_kop' => 'sistem', 'nama_perusahaan' => 'PT', 'ppn_default' => '11', 'logo' => new CURLFile($txt, 'image/png', 'x.php')]);
ok($q("SELECT logo_path FROM pengaturan_perusahaan WHERE id=1") === null, 'Logo bukan gambar ditolak');
$a->post('/pengaturan/kop', ['mode_kop' => 'sistem', 'nama_perusahaan' => 'PT. Bali Surya Sentosa', 'ppn_default' => '11', 'logo' => new CURLFile($png, 'image/png', 'logo.png')]);
ok($q("SELECT logo_path FROM pengaturan_perusahaan WHERE id=1") !== null, 'Logo PNG tersimpan');
ok(str_starts_with($a->get("/invoice/$idInv/cetak")['body'], '%PDF'), 'Invoice PDF dengan logo + kop sistem');

// ------------------------------------------------------------------ Dashboard & profil & logout
section('Dashboard, profil, logout');
$r = $a->get('/dashboard');
ok($noErr($r['body']) && str_contains($r['body'], 'chartBulanan'), 'Dashboard memuat grafik');
$r = $a->post('/profil/password', ['password_lama' => 'salah', 'password_baru' => 'baru12345', 'konfirmasi' => 'baru12345']);
ok(str_contains($r['body'], 'Password lama salah'), 'Ganti password: password lama salah ditolak');
$r = $a->post('/profil/password', ['password_lama' => 'admin123', 'password_baru' => 'pendek', 'konfirmasi' => 'pendek']);
ok(str_contains($r['body'], 'minimal 8'), 'Ganti password: terlalu pendek ditolak');
$r = $a->post('/logout', [], false);
ok($r['code'] === 302, 'Logout');
$r = $a->get('/dashboard');
ok(str_contains($r['body'], 'Username'), 'Setelah logout, dashboard mengarah ke login');
$r = $a->get('/tidak-ada');
ok($r['code'] === 404, 'Halaman tidak ada -> 404');

// brute force
section('Pembatasan percobaan login');
$b = new Client($base);
$r = $b->get('/login'); preg_match('/name="_token" value="([a-f0-9]+)"/', $r['body'], $m);
for ($i = 0; $i < 5; $i++) $r = $b->post('/login', ['_token' => $m[1], 'username' => 'pimpinan', 'password' => 'x' . $i]);
$r = $b->post('/login', ['_token' => $m[1], 'username' => 'pimpinan', 'password' => 'pimpinan123']);
ok(str_contains($r['body'], 'Terlalu banyak percobaan'), 'Setelah 5x gagal, login ditunda walau password benar');
@unlink($root . '/storage/logs/throttle.json');

echo "\n================ HASIL: $pass lulus, $fail gagal ================\n";
exit($fail ? 1 : 0);
