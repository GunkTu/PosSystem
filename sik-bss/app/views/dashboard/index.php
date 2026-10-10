<?php
/** @var bool $admin */
$cards = [
    ['Total Pemasukan', rupiah($masuk), 'bi-box-arrow-in-down', 'text-income', 'Bulan berjalan'],
    ['Total Pengeluaran', rupiah($keluar), 'bi-box-arrow-up', 'text-expense', 'Bulan berjalan'],
    ['Saldo Kas + Bank', rupiah($saldo), 'bi-bank', 'text-primary', 'Saldo saat ini'],
    ['Invoice Belum Lunas', (int)$inv['n'] . ' invoice', 'bi-receipt', 'text-warning', 'Sisa tagihan ' . rupiah($inv['sisa'])],
];
if (!$admin) $cards[] = [$laba >= 0 ? 'Laba Bulan Ini' : 'Rugi Bulan Ini', rupiah($laba), 'bi-graph-up-arrow', $laba >= 0 ? 'text-income' : 'text-expense', 'Bulan berjalan'];
?>
<div class="text-muted mb-3">Ringkasan periode <?= e($bulanIni) ?></div>
<div class="row g-3 mb-3">
  <?php foreach ($cards as [$label, $val, $icon, $cls, $ket]): ?>
  <div class="col-sm-6 <?= count($cards) > 4 ? 'col-xl' : 'col-xl-3' ?>"><div class="card h-100"><div class="card-body stat-card">
    <div><div class="label"><?= e($label) ?></div><div class="value"><?= e($val) ?></div><div class="small text-muted"><?= e($ket) ?></div></div>
    <i class="bi <?= $icon ?> icon <?= $cls ?>"></i>
  </div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-8"><div class="card h-100"><div class="card-body">
    <div class="card-title">Pemasukan vs Pengeluaran (6 bulan)</div>
    <div style="height:280px"><canvas id="chartBulanan" aria-label="Grafik pemasukan dan pengeluaran 6 bulan terakhir" role="img"></canvas></div>
  </div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-body">
    <div class="card-title">Saldo per Kas / Bank</div>
    <?php if (!$kasBank): ?><p class="text-muted mb-0">Belum ada kas/bank aktif.</p><?php else: ?>
    <ul class="list-group list-group-flush"><?php foreach ($kasBank as $k): ?>
      <li class="list-group-item d-flex justify-content-between px-0"><span><i class="bi <?= $k['jenis_kas_bank'] === 'bank' ? 'bi-bank' : 'bi-cash-stack' ?> text-muted"></i> <?= e($k['nama_kas_bank']) ?></span><span class="fw-semibold"><?= angka($k['saldo']) ?></span></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7"><div class="card h-100"><div class="card-body">
    <div class="card-title">Transaksi Terbaru</div>
    <?php if (!$terbaru): partial('empty'); else: ?>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
      <thead><tr><th>Tanggal</th><th>No. Bukti</th><th>Keterangan</th><th class="num">Nominal</th></tr></thead>
      <tbody><?php foreach ($terbaru as $t): ?>
        <tr><td><?= tgl($t['tanggal']) ?></td><td><?= e($t['no_bukti']) ?></td><td><?= e($t['ket']) ?></td>
          <td class="num <?= $t['jenis'] === 'masuk' ? 'text-income' : 'text-expense' ?>"><?= $t['jenis'] === 'masuk' ? '+' : '-' ?> <?= angka($t['nominal']) ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div></div></div>
  <div class="col-lg-5"><div class="card h-100"><div class="card-body">
    <div class="card-title">Invoice Jatuh Tempo / Lewat Tempo</div>
    <?php if (!$jatuhTempo): ?><p class="text-muted mb-0">Tidak ada invoice yang jatuh tempo dalam 7 hari.</p><?php else: ?>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
      <thead><tr><th>Invoice</th><th>Jatuh tempo</th><th class="num">Sisa</th></tr></thead>
      <tbody><?php foreach ($jatuhTempo as $j): $telat = $j['jatuh_tempo'] < date('Y-m-d'); ?>
        <tr><td><?= $admin ? '<a href="' . url('/invoice/' . $j['id_invoice']) . '">' . e($j['no_invoice']) . '</a>' : e($j['no_invoice']) ?><div class="small text-muted"><?= e($j['nama_pelanggan']) ?></div></td>
          <td class="<?= $telat ? 'text-expense fw-semibold' : '' ?>"><?= tgl($j['jatuh_tempo']) ?><?= $telat ? ' (lewat)' : '' ?></td><td class="num"><?= angka($j['sisa']) ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div></div></div>
</div>

<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartBulanan'), {
  type: 'bar',
  data: { labels: <?= json_encode($labels) ?>, datasets: [
    { label: 'Pemasukan', data: <?= json_encode($dm) ?>, backgroundColor: '#1E8E5A' },
    { label: 'Pengeluaran', data: <?= json_encode($dk) ?>, backgroundColor: '#C8423B' } ] },
  options: { responsive: true, maintainAspectRatio: false,
    scales: { y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('id-ID') } } },
    plugins: { tooltip: { callbacks: { label: c => c.dataset.label + ': Rp ' + c.parsed.y.toLocaleString('id-ID', {minimumFractionDigits: 2}) } } } }
});
</script>
<?php $scripts = ob_get_clean(); ?>
