<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <form class="d-flex gap-2 flex-wrap" method="get">
    <input type="date" class="form-control" style="width:150px" name="dari" value="<?= e($dari) ?>" title="Dari tanggal">
    <input type="date" class="form-control" style="width:150px" name="sampai" value="<?= e($sampai) ?>" title="Sampai tanggal">
    <input class="form-control" style="width:230px" name="q" value="<?= e($q) ?>" placeholder="No. bukti / invoice / akun">
    <select class="form-select" style="width:120px" name="status"><option value="">Semua</option>
      <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option><option value="batal" <?= $status === 'batal' ? 'selected' : '' ?>>Batal</option></select>
    <button class="btn btn-outline-primary"><i class="bi bi-search"></i> Cari</button>
  </form>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" target="_blank" href="<?= url('/pemasukan/cetak' . qs(['page' => null, 'per' => null])) ?>"><i class="bi bi-printer"></i> Cetak</a>
    <a class="btn btn-primary" href="<?= url('/pemasukan/create') ?>"><i class="bi bi-plus-lg"></i> Tambah</a>
  </div>
</div>
<div class="card"><div class="card-body">
<?php if (!$pg['rows']): partial('empty'); else: ?>
<div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>No. Bukti</th><th>Tanggal</th><th>Invoice / Akun</th><th>Kas/Bank</th><th>Keterangan</th><th class="num">Nominal</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
  <tbody>
  <?php foreach ($pg['rows'] as $r): ?>
    <tr class="<?= $r['status'] === 'batal' ? 'text-muted' : '' ?>">
      <td><?= e($r['no_bukti_pemasukan']) ?></td><td><?= tgl($r['tanggal_pembayaran']) ?></td>
      <td><?= $r['no_invoice'] ? '<a href="' . url('/invoice/' . $r['id_invoice']) . '">' . e($r['no_invoice']) . '</a>' : '' ?><div class="small text-muted"><?= e($r['nama_akun']) ?></div></td>
      <td><?= e($r['nama_kas_bank']) ?></td><td><?= e($r['keterangan_pembayaran']) ?></td>
      <td class="num"><?= angka($r['nominal_pembayaran']) ?></td><td><?= badge($r['status']) ?></td>
      <td class="text-end text-nowrap"><?php if ($r['status'] === 'aktif'): ?>
        <a class="btn btn-outline-primary btn-icon" title="Ubah" href="<?= url('/pemasukan/' . $r['id_pemasukan'] . '/edit') ?>"><i class="bi bi-pencil"></i></a>
        <button class="btn btn-outline-danger btn-icon" title="Batalkan" data-confirm="Batalkan pemasukan <?= e($r['no_bukti_pemasukan']) ?>? Saldo dan status invoice akan dikoreksi." data-yes="Ya, batalkan" data-action="<?= url('/pemasukan/batal/' . $r['id_pemasukan']) ?>"><i class="bi bi-x-circle"></i></button>
      <?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr class="fw-semibold"><td colspan="5" class="text-end">Total pemasukan aktif (sesuai filter)</td><td class="num"><?= angka($total) ?></td><td colspan="2"></td></tr></tfoot>
  </table></div>
<?php partial('pager', ['pg' => $pg]); endif; ?>
</div></div>
