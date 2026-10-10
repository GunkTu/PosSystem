<div class="mb-3"><a href="<?= url('/kas-bank') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a></div>
<div class="card"><div class="card-body">
  <div class="d-flex justify-content-between mb-3">
    <div><div class="fw-semibold"><?= e($k['kode_kas_bank'] . ' - ' . $k['nama_kas_bank']) ?></div><div class="text-muted"><?= ucfirst(e($k['jenis_kas_bank'])) ?> <?= e($k['no_rekening']) ?></div></div>
    <div class="text-end"><div class="text-muted">Saldo saat ini</div><div class="fs-5 fw-semibold"><?= rupiah($k['saldo']) ?></div></div>
  </div>
  <?php if (!$rows): partial('empty'); else: $run = 0; ?>
  <div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>Tanggal</th><th>No. Bukti</th><th>Keterangan</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Saldo</th></tr></thead>
    <tbody><?php foreach ($rows as $r): $run += $r['masuk'] - $r['keluar']; ?>
      <tr><td><?= tgl($r['tanggal']) ?></td><td><?= e($r['no_bukti']) ?></td><td><?= e($r['keterangan']) ?></td>
        <td class="num text-income"><?= $r['masuk'] > 0 ? angka($r['masuk']) : '' ?></td><td class="num text-expense"><?= $r['keluar'] > 0 ? angka($r['keluar']) : '' ?></td>
        <td class="num fw-semibold"><?= angka($run) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div></div>
