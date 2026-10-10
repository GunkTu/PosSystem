DROP DATABASE IF EXISTS sik_bss;
CREATE DATABASE sik_bss CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sik_bss;

CREATE TABLE pengguna (
  id_user INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  username VARCHAR(20) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','pimpinan') NOT NULL,
  status_pengguna ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE pelanggan (
  id_pelanggan INT AUTO_INCREMENT PRIMARY KEY,
  kode_pelanggan VARCHAR(20) NOT NULL UNIQUE,
  nama_pelanggan VARCHAR(100) NOT NULL,
  alamat VARCHAR(255),
  telepon VARCHAR(20),
  email VARCHAR(50),
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif'
) ENGINE=InnoDB;

CREATE TABLE akun (
  id_akun INT AUTO_INCREMENT PRIMARY KEY,
  kode_akun VARCHAR(20) NOT NULL UNIQUE,
  nama_akun VARCHAR(100) NOT NULL,
  tipe_akun ENUM('aset','kewajiban','modal','pendapatan','beban') NOT NULL,
  kategori_akun VARCHAR(30),
  saldo_normal ENUM('debit','kredit') NOT NULL,
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif'
) ENGINE=InnoDB;

CREATE TABLE kas_bank (
  id_kas_bank INT AUTO_INCREMENT PRIMARY KEY,
  id_akun INT NOT NULL,
  kode_kas_bank VARCHAR(20) NOT NULL UNIQUE,
  nama_kas_bank VARCHAR(100) NOT NULL,
  jenis_kas_bank ENUM('kas','bank') NOT NULL,
  no_rekening VARCHAR(30),
  saldo_awal DECIMAL(15,2) NOT NULL DEFAULT 0,
  saldo DECIMAL(15,2) NOT NULL DEFAULT 0,
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  FOREIGN KEY (id_akun) REFERENCES akun(id_akun)
) ENGINE=InnoDB;

CREATE TABLE invoice (
  id_invoice INT AUTO_INCREMENT PRIMARY KEY,
  id_pelanggan INT NOT NULL,
  no_invoice VARCHAR(20) NOT NULL UNIQUE,
  tanggal DATE NOT NULL,
  jatuh_tempo DATE NOT NULL,
  keterangan VARCHAR(255),
  subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
  ppn_persen DECIMAL(5,2) NOT NULL DEFAULT 0,
  ppn_nominal DECIMAL(15,2) NOT NULL DEFAULT 0,
  total DECIMAL(15,2) NOT NULL,
  total_terbayar DECIMAL(15,2) NOT NULL DEFAULT 0,
  status_invoice ENUM('belum_lunas','sebagian','lunas','batal') NOT NULL DEFAULT 'belum_lunas',
  created_by INT,
  FOREIGN KEY (id_pelanggan) REFERENCES pelanggan(id_pelanggan),
  FOREIGN KEY (created_by) REFERENCES pengguna(id_user),
  INDEX (tanggal), INDEX (status_invoice)
) ENGINE=InnoDB;

CREATE TABLE invoice_item (
  id_item INT AUTO_INCREMENT PRIMARY KEY,
  id_invoice INT NOT NULL,
  jenis_layanan ENUM('freight','gudang','pengiriman','lainnya') NOT NULL,
  deskripsi VARCHAR(150) NOT NULL,
  qty DECIMAL(10,2) NOT NULL DEFAULT 1,
  satuan VARCHAR(20),
  harga_satuan DECIMAL(15,2) NOT NULL,
  subtotal DECIMAL(15,2) NOT NULL,
  FOREIGN KEY (id_invoice) REFERENCES invoice(id_invoice) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pengaturan_perusahaan (
  id TINYINT PRIMARY KEY DEFAULT 1,
  nama_perusahaan VARCHAR(100) NOT NULL DEFAULT 'Nama Perusahaan',
  alamat VARCHAR(255) DEFAULT 'Alamat perusahaan',
  telepon VARCHAR(30),
  email VARCHAR(50),
  npwp VARCHAR(30),
  logo_path VARCHAR(255) NULL,
  mode_kop ENUM('gambar','sistem') NOT NULL DEFAULT 'sistem',
  header_image_path VARCHAR(255) NULL,
  bank_nama VARCHAR(50),
  bank_no_rekening VARCHAR(30),
  bank_atas_nama VARCHAR(100),
  ttd_nama VARCHAR(100),
  ttd_jabatan VARCHAR(50),
  catatan_invoice VARCHAR(255),
  ppn_default DECIMAL(5,2) NOT NULL DEFAULT 11.00,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE transaksi_pemasukan (
  id_pemasukan INT AUTO_INCREMENT PRIMARY KEY,
  id_kas_bank INT NOT NULL,
  id_invoice INT NULL,
  id_akun INT NOT NULL,
  no_bukti_pemasukan VARCHAR(30) NOT NULL UNIQUE,
  tanggal_pembayaran DATE NOT NULL,
  keterangan_pembayaran VARCHAR(100),
  nominal_pembayaran DECIMAL(15,2) NOT NULL,
  status ENUM('aktif','batal') NOT NULL DEFAULT 'aktif',
  created_by INT,
  FOREIGN KEY (id_kas_bank) REFERENCES kas_bank(id_kas_bank),
  FOREIGN KEY (id_invoice) REFERENCES invoice(id_invoice),
  FOREIGN KEY (id_akun) REFERENCES akun(id_akun),
  INDEX (tanggal_pembayaran)
) ENGINE=InnoDB;

CREATE TABLE transaksi_pengeluaran (
  id_pengeluaran INT AUTO_INCREMENT PRIMARY KEY,
  id_kas_bank INT NOT NULL,
  id_akun INT NOT NULL,
  no_bukti_pengeluaran VARCHAR(30) NOT NULL UNIQUE,
  tanggal_pengeluaran DATE NOT NULL,
  keterangan_pengeluaran VARCHAR(100),
  nominal_pengeluaran DECIMAL(15,2) NOT NULL,
  file_bukti VARCHAR(255),
  status ENUM('aktif','batal') NOT NULL DEFAULT 'aktif',
  created_by INT,
  FOREIGN KEY (id_kas_bank) REFERENCES kas_bank(id_kas_bank),
  FOREIGN KEY (id_akun) REFERENCES akun(id_akun),
  INDEX (tanggal_pengeluaran)
) ENGINE=InnoDB;

CREATE TABLE jurnal (
  id_jurnal INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATE NOT NULL,
  no_bukti VARCHAR(30) NOT NULL,
  keterangan VARCHAR(150),
  sumber ENUM('pemasukan','pengeluaran','saldo_awal','manual') NOT NULL,
  id_sumber INT NULL,
  status ENUM('aktif','batal') NOT NULL DEFAULT 'aktif',
  INDEX (tanggal), INDEX (sumber, id_sumber)
) ENGINE=InnoDB;

CREATE TABLE jurnal_detail (
  id_detail INT AUTO_INCREMENT PRIMARY KEY,
  id_jurnal INT NOT NULL,
  id_akun INT NOT NULL,
  debit DECIMAL(15,2) NOT NULL DEFAULT 0,
  kredit DECIMAL(15,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (id_jurnal) REFERENCES jurnal(id_jurnal),
  FOREIGN KEY (id_akun) REFERENCES akun(id_akun)
) ENGINE=InnoDB;

CREATE TABLE riwayat_laporan (
  id_laporan INT AUTO_INCREMENT PRIMARY KEY,
  id_user INT NOT NULL,
  jenis_laporan VARCHAR(50) NOT NULL,
  periode VARCHAR(50) NOT NULL,
  format ENUM('pdf','excel') NOT NULL,
  file_laporan VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_user) REFERENCES pengguna(id_user)
) ENGINE=InnoDB;
