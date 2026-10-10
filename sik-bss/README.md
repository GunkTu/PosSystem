# SIK PT. Bali Surya Sentosa

Sistem Informasi Keuangan berbasis web (PHP 8.1+ native, MySQL/MariaDB, Bootstrap 5).
Dokumen kebutuhan dan desain ada di folder induk: `PRD.md` dan `DESIGN.md`.

## Menjalankan secara lokal (XAMPP)

1. Jalankan **Apache** dan **MySQL** di XAMPP.
2. Impor database (**menghapus** database `sik_bss` bila sudah ada):
   ```
   C:\xampp\mysql\bin\mysql.exe -uroot < database\schema.sql
   C:\xampp\mysql\bin\mysql.exe -uroot < database\seed.sql
   ```
3. Atur `app/config/database.php` bila user/password MySQL berbeda (contoh: `database.example.php`).
4. `composer install` (Dompdf dan PhpSpreadsheet).
5. Buka salah satu:
   - **Server bawaan PHP:** `php -S 127.0.0.1:8080 -t public public/index.php`, lalu `http://127.0.0.1:8080`
   - **Apache:** salin atau tautkan folder ini ke `C:\xampp\htdocs\sik-bss`, lalu `http://localhost/sik-bss/`
     (`.htaccess` di root meneruskan ke `public/`; `mod_rewrite` harus aktif).

### Akun awal (WAJIB diganti)
| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Pimpinan | `pimpinan` | `pimpinan123` |

Ganti lewat menu pengguna di pojok kanan atas, lalu **Ganti Password**.

## Urutan penggunaan awal
1. **Pengaturan Kop**: unggah kop (JPG/PNG) atau isi logo dan data perusahaan, rekening, penandatangan, tarif PPN.
2. **Kas dan Bank**: isi saldo awal dan tanggalnya (membentuk jurnal saldo awal terhadap Modal Pemilik).
3. **Data Akun** dan **Data Pelanggan**: sesuaikan bagan akun dan isi pelanggan.
4. **Invoice** → **Catat Pembayaran** (pemasukan) → **Transaksi Pengeluaran**.
5. **Laporan Keuangan** (preview, cetak PDF, unduh Excel) dan **Riwayat Laporan**.

## Pengujian
```
php -S 127.0.0.1:8080 -t public public/index.php
php tests/e2e.php            # atau: php tests/e2e.php http://localhost/sik-bss
```
`tests/e2e.php` **mereset database `sik_bss`** lalu menjalankan 121 pemeriksaan (login/role, CRUD master, invoice multi-item + PPN,
pemasukan/pengeluaran, aturan bisnis BR-01 s/d BR-12, laporan, PDF/Excel, kop, keamanan dasar). Jalankan `schema.sql` + `seed.sql` lagi sesudahnya agar data bersih.

## Struktur
```
public/            front controller + aset (satu-satunya yang boleh diakses web)
app/core/          Router, Auth, Csrf, Database (PDO), Flash, helpers
app/controllers/   satu controller per modul
app/services/      logika bisnis: Invoice, Transaksi (saldo + jurnal atomik), Jurnal, Laporan, Kop, Pdf
app/views/         tampilan PHP (layouts, partials, pdf)
database/          schema.sql, seed.sql
storage/           laporan, bukti, kop, logs (di luar akses web)
tests/e2e.php      uji end-to-end
```

## Produksi (hosting)
- Arahkan DocumentRoot ke `public/`, aktifkan HTTPS, dan set di `app/config/app.php`: `debug => false`, `force_https => true`.
- Ganti password akun awal. `app/` dan `storage/` harus berada di luar web root atau diblokir (sudah ada `.htaccess` `Require all denied`).
- Jadwalkan backup `mysqldump` harian dan simpan di luar server.
- Git: `vendor/`, `storage/*`, dan `app/config/database.php` sudah di-`.gitignore`.
