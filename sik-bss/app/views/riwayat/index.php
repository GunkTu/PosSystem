<form class="d-flex gap-2 mb-3" method="get">
  <input class="form-control" style="width:240px" name="jenis" value="<?= e($jenis) ?>" placeholder="Cari jenis laporan">
  <button class="btn btn-outline-primary"><i class="bi bi-search"></i> Cari</button>
</form>
<div class="card"><div class="card-body">
<?php if (!$pg['rows']): partial('empty'); else: ?>
<div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>Tanggal dibuat</th><th>Jenis</th><th>Periode</th><th>Format</th><th>Dibuat oleh</th><th class="text-end">Aksi</th></tr></thead>
  <tbody><?php foreach ($pg['rows'] as $r): ?>
    <tr><td><?= tgl($r['created_at']) . ' ' . date('H:i', strtotime($r['created_at'])) ?></td><td><?= e($r['jenis_laporan']) ?></td><td><?= e($r['periode']) ?></td>
      <td><span class="badge text-bg-<?= $r['format'] === 'pdf' ? 'danger' : 'success' ?>"><?= strtoupper($r['format'] === 'pdf' ? 'PDF' : 'Excel') ?></span></td><td><?= e($r['nama']) ?></td>
      <td class="text-end text-nowrap">
        <?php if ($r['format'] === 'pdf'): ?><a class="btn btn-outline-primary btn-icon" target="_blank" title="Buka / Cetak" href="<?= url('/riwayat/' . $r['id_laporan'] . '/buka') ?>"><i class="bi bi-printer"></i></a><?php endif; ?>
        <a class="btn btn-outline-secondary btn-icon" title="Unduh" href="<?= url('/riwayat/' . $r['id_laporan'] . '/unduh') ?>"><i class="bi bi-download"></i></a>
      </td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php partial('pager', ['pg' => $pg]); endif; ?>
</div></div>
