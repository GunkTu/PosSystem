<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
  <a href="<?= url('/laporan?jenis=' . urlencode($jenis)) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Ubah laporan / periode</a>
  <div class="d-flex gap-2">
    <a class="btn btn-primary" target="_blank" href="<?= url('/laporan/pdf?' . $q) ?>"><i class="bi bi-printer"></i> Cetak</a>
    <a class="btn btn-outline-primary" href="<?= url('/laporan/pdf?' . $q . '&unduh=1') ?>"><i class="bi bi-file-earmark-pdf"></i> Unduh PDF</a>
    <a class="btn btn-outline-success" href="<?= url('/laporan/excel?' . $q) ?>"><i class="bi bi-file-earmark-excel"></i> Unduh Excel</a>
  </div>
</div>
<div class="laporan-preview">
  <?php partial('kop', ['kop' => $kop]); ?>
  <h2 class="h5 text-center fw-bold mb-0"><?= e($rep['title']) ?></h2>
  <div class="text-center text-muted mb-3"><?= e($rep['subtitle']) ?></div>
  <div class="table-responsive"><?php partial('report_table', ['rep' => $rep]); ?></div>
</div>
