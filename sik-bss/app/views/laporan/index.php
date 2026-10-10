<div class="card" style="max-width:720px"><div class="card-body">
  <form method="get" action="<?= url('/laporan/preview') ?>" class="row g-3">
    <div class="col-12"><label class="form-label req">Jenis laporan</label>
      <select name="jenis" class="form-select" id="jenisSel">
        <?php foreach ($jenisList as $k => $v): ?><option value="<?= $k ?>" <?= $jenis === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label req">Dari tanggal</label><input type="date" name="dari" class="form-control" required value="<?= e($dari) ?>"></div>
    <div class="col-md-6"><label class="form-label req">Sampai tanggal</label><input type="date" name="sampai" class="form-control" required value="<?= e($sampai) ?>"></div>
    <div class="col-12 form-text">Neraca dihitung per tanggal akhir periode (kumulatif sejak awal pencatatan).</div>
    <div class="col-12 text-end"><button class="btn btn-primary"><i class="bi bi-eye"></i> Tampilkan</button></div>
  </form>
</div></div>
