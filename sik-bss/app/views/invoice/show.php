<?php $sisa = max(0, $inv['total'] - $inv['total_terbayar']); ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <a href="<?= url('/invoice') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (in_array($inv['status_invoice'], ['belum_lunas', 'sebagian'], true)): ?>
      <a class="btn btn-success" href="<?= url('/pemasukan/create?invoice=' . $inv['id_invoice']) ?>"><i class="bi bi-cash-coin"></i> Catat Pembayaran</a>
    <?php endif; ?>
    <?php if ($inv['status_invoice'] !== 'batal'): ?>
      <a class="btn btn-outline-primary" href="<?= url('/invoice/' . $inv['id_invoice'] . '/edit') ?>"><i class="bi bi-pencil"></i> Ubah</a>
    <?php endif; ?>
    <a class="btn btn-primary" target="_blank" href="<?= url('/invoice/' . $inv['id_invoice'] . '/cetak') ?>"><i class="bi bi-printer"></i> Cetak</a>
    <a class="btn btn-outline-secondary" href="<?= url('/invoice/' . $inv['id_invoice'] . '/cetak?unduh=1') ?>"><i class="bi bi-download"></i> PDF</a>
    <button class="btn btn-outline-<?= $inv['status_invoice'] === 'batal' ? 'success' : 'danger' ?>" data-confirm="<?= $inv['status_invoice'] === 'batal' ? 'Aktifkan kembali invoice ini?' : 'Batalkan invoice ini?' ?>" data-yes="Ya, lanjutkan" data-action="<?= url('/invoice/status/' . $inv['id_invoice']) ?>">
      <i class="bi bi-power"></i> <?= $inv['status_invoice'] === 'batal' ? 'Aktifkan' : 'Batalkan' ?></button>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-8"><div class="card h-100"><div class="card-body">
    <div class="d-flex justify-content-between"><h2 class="h5 mb-3"><?= e($inv['no_invoice']) ?></h2><?= badge($inv['status_invoice']) ?></div>
    <div class="row">
      <div class="col-sm-6"><div class="text-muted">Pelanggan</div><div class="fw-semibold"><?= e($inv['nama_pelanggan']) ?></div><div><?= e($inv['alamat']) ?></div><div><?= e($inv['telepon']) ?></div></div>
      <div class="col-sm-6"><div class="text-muted">Tanggal</div><div><?= tgl($inv['tanggal']) ?></div><div class="text-muted mt-2">Jatuh tempo</div><div><?= tgl($inv['jatuh_tempo']) ?></div></div>
    </div>
    <?php if ($inv['keterangan']): ?><div class="mt-3"><span class="text-muted">Keterangan:</span> <?= e($inv['keterangan']) ?></div><?php endif; ?>
  </div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-body">
    <table class="table table-sm mb-0">
      <tr><td>Subtotal</td><td class="num"><?= angka($inv['subtotal']) ?></td></tr>
      <tr><td>PPN <?= rtrim(rtrim(number_format((float)$inv['ppn_persen'], 2, ',', ''), '0'), ',') ?>%</td><td class="num"><?= angka($inv['ppn_nominal']) ?></td></tr>
      <tr class="fw-bold"><td>Total</td><td class="num"><?= angka($inv['total']) ?></td></tr>
      <tr><td>Terbayar</td><td class="num text-income"><?= angka($inv['total_terbayar']) ?></td></tr>
      <tr class="fw-semibold"><td>Sisa tagihan</td><td class="num text-expense"><?= angka($sisa) ?></td></tr>
    </table>
  </div></div></div>
</div>

<div class="card mb-3"><div class="card-body">
  <div class="card-title mb-2">Item Layanan</div>
  <div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>No</th><th>Layanan</th><th>Deskripsi</th><th class="num">Qty</th><th>Satuan</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
    <tbody><?php foreach ($items as $i => $it): ?>
      <tr><td><?= $i + 1 ?></td><td><?= e(ucfirst($it['jenis_layanan'])) ?></td><td><?= e($it['deskripsi']) ?></td>
        <td class="num"><?= rtrim(rtrim(number_format((float)$it['qty'], 2, ',', '.'), '0'), ',') ?></td><td><?= e($it['satuan']) ?></td>
        <td class="num"><?= angka($it['harga_satuan']) ?></td><td class="num"><?= angka($it['subtotal']) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
</div></div>

<div class="card"><div class="card-body">
  <div class="card-title mb-2">Riwayat Pembayaran</div>
  <?php if (!$bayar): ?><p class="text-muted mb-0">Belum ada pembayaran.</p><?php else: ?>
  <div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>No. Bukti</th><th>Tanggal</th><th>Kas/Bank</th><th>Keterangan</th><th class="num">Nominal</th><th>Status</th></tr></thead>
    <tbody><?php foreach ($bayar as $b): ?>
      <tr><td><a href="<?= url('/pemasukan?q=' . urlencode($b['no_bukti_pemasukan'])) ?>"><?= e($b['no_bukti_pemasukan']) ?></a></td><td><?= tgl($b['tanggal_pembayaran']) ?></td>
        <td><?= e($b['nama_kas_bank']) ?></td><td><?= e($b['keterangan_pembayaran']) ?></td><td class="num"><?= angka($b['nominal_pembayaran']) ?></td><td><?= badge($b['status'] === 'aktif' ? 'aktif' : 'batal') ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div></div>
