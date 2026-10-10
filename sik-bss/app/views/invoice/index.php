<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <form class="d-flex gap-2 flex-wrap" method="get">
    <input class="form-control" style="width:210px" name="q" value="<?= e($q) ?>" placeholder="No. invoice / pelanggan">
    <select class="form-select" style="width:140px" name="status"><option value="">Semua status</option>
      <?php foreach (['belum_lunas' => 'Belum lunas', 'sebagian' => 'Sebagian', 'lunas' => 'Lunas', 'batal' => 'Batal'] as $k => $v): ?>
        <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
    <input type="date" class="form-control" style="width:150px" name="dari" value="<?= e($dari) ?>" title="Dari tanggal">
    <input type="date" class="form-control" style="width:150px" name="sampai" value="<?= e($sampai) ?>" title="Sampai tanggal">
    <button class="btn btn-outline-primary"><i class="bi bi-search"></i> Cari</button>
  </form>
  <a class="btn btn-primary" href="<?= url('/invoice/create') ?>"><i class="bi bi-plus-lg"></i> Buat Invoice</a>
</div>
<div class="card"><div class="card-body">
<?php if (!$pg['rows']): partial('empty'); else: ?>
<div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>No. Invoice</th><th>Pelanggan</th><th>Tanggal</th><th>Jatuh Tempo</th><th class="num">Total</th><th class="num">Terbayar</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
  <tbody>
  <?php foreach ($pg['rows'] as $r): $telat = $r['status_invoice'] !== 'lunas' && $r['status_invoice'] !== 'batal' && $r['jatuh_tempo'] < date('Y-m-d'); ?>
    <tr>
      <td><a href="<?= url('/invoice/' . $r['id_invoice']) ?>"><?= e($r['no_invoice']) ?></a></td><td><?= e($r['nama_pelanggan']) ?></td>
      <td><?= tgl($r['tanggal']) ?></td><td class="<?= $telat ? 'text-expense fw-semibold' : '' ?>"><?= tgl($r['jatuh_tempo']) ?><?= $telat ? ' <i class="bi bi-exclamation-circle" title="Lewat jatuh tempo"></i>' : '' ?></td>
      <td class="num"><?= angka($r['total']) ?></td><td class="num"><?= angka($r['total_terbayar']) ?></td><td><?= badge($r['status_invoice']) ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-outline-secondary btn-icon" title="Detail" href="<?= url('/invoice/' . $r['id_invoice']) ?>"><i class="bi bi-eye"></i></a>
        <?php if ($r['status_invoice'] !== 'batal'): ?>
        <a class="btn btn-outline-primary btn-icon" title="Ubah" href="<?= url('/invoice/' . $r['id_invoice'] . '/edit') ?>"><i class="bi bi-pencil"></i></a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary btn-icon" title="Cetak" target="_blank" href="<?= url('/invoice/' . $r['id_invoice'] . '/cetak') ?>"><i class="bi bi-printer"></i></a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php partial('pager', ['pg' => $pg]); endif; ?>
</div></div>
