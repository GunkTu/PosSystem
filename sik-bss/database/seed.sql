USE sik_bss;

-- Password awal: admin / admin123, pimpinan / pimpinan123  (WAJIB diganti saat go-live)
INSERT INTO pengguna (nama, username, password, role) VALUES
('Admin Keuangan', 'admin',    '$2y$10$yxYgceV0ap2nBXyWP.JOj.c7RZDGY5vW48LEBUnTSm8nrIWWNWqa6', 'admin'),
('Pimpinan',       'pimpinan', '$2y$10$17C8eEiTMaLJihVsSiDej.dA2j0UfCU60IMRDqdPVxnWf0SEQIBmy', 'pimpinan');

INSERT INTO akun (kode_akun, nama_akun, tipe_akun, kategori_akun, saldo_normal) VALUES
('1-101','Kas','aset','Kas','debit'),
('1-102','Bank','aset','Bank','debit'),
('1-201','Piutang Usaha','aset','Piutang','debit'),
('2-101','PPN Keluaran','kewajiban','Pajak','kredit'),
('3-101','Modal Pemilik','modal','Modal','kredit'),
('3-102','Prive','modal','Prive','debit'),
('4-101','Pendapatan Freight','pendapatan','Jasa','kredit'),
('4-102','Pendapatan Jasa Gudang','pendapatan','Jasa','kredit'),
('4-103','Pendapatan Jasa Pengiriman','pendapatan','Jasa','kredit'),
('5-101','Beban Transportasi','beban','Operasional','debit'),
('5-102','Beban Gudang','beban','Operasional','debit'),
('5-103','Beban Operasional','beban','Operasional','debit'),
('5-104','Beban Gaji','beban','Operasional','debit');

INSERT INTO kas_bank (id_akun, kode_kas_bank, nama_kas_bank, jenis_kas_bank, no_rekening)
VALUES
((SELECT id_akun FROM akun WHERE kode_akun='1-101'), 'KAS-01', 'Kas Kantor', 'kas', NULL),
((SELECT id_akun FROM akun WHERE kode_akun='1-102'), 'BANK-01', 'Bank Utama', 'bank', '');

INSERT INTO pengaturan_perusahaan (id) VALUES (1);
