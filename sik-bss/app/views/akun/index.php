<div class="d-flex justify-content-between align-items-center mb-3">
  <form class="d-flex gap-2 flex-wrap" method="get">
    <input class="form-control" style="width:220px" name="q" value="<?= e($q) ?>" placeholder="Cari kode / nama akun">
    <select class="form-select" style="width:150px" name="tipe"><option value="">Semua tipe</option>
      <?php foreach ($tipeList as $t): ?><option value="<?= $t ?>" <?= $tipe === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option><?php endforeach; ?></select>
    <select class="form-select" style="width:140px" name="status"><option value="">Semua status</option>
      <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option><option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option></select>
    <button class="btn btn-outline-primary"><i class="bi bi-search"></i> Cari</button>
  </form>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#akunModal" data-add="#akunModal" data-action="<?= url('/akun/store') ?>" data-title="Tambah Akun"><i class="bi bi-plus-lg"></i> Tambah</button>
</div>
<div class="card"><div class="card-body">
<?php if (!$pg['rows']): partial('empty'); else: ?>
<div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>No</th><th>Kode</th><th>Nama</th><th>Tipe</th><th>Kategori</th><th>Saldo Normal</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
  <tbody>
  <?php foreach ($pg['rows'] as $i => $r): ?>
    <tr>
      <td><?= ($pg['page'] - 1) * $pg['per'] + $i + 1 ?></td><td><?= e($r['kode_akun']) ?></td><td><?= e($r['nama_akun']) ?></td>
      <td><?= ucfirst(e($r['tipe_akun'])) ?></td><td><?= e($r['kategori_akun']) ?></td><td><?= ucfirst(e($r['saldo_normal'])) ?></td>
      <td><?= badge($r['status']) ?></td>
      <td class="text-end text-nowrap">
        <button class="btn btn-outline-primary btn-icon" title="Ubah" data-bs-toggle="modal" data-bs-target="#akunModal" data-edit="#akunModal"
          data-locked="<?= $r['dipakai'] > 0 ? 1 : 0 ?>" data-action="<?= url('/akun/update/' . $r['id_akun']) ?>" data-title="Ubah Akun"
          data-row="<?= e(json_encode(['kode_akun' => $r['kode_akun'], 'nama_akun' => $r['nama_akun'], 'tipe_akun' => $r['tipe_akun'], 'kategori_akun' => $r['kategori_akun'], 'saldo_normal' => $r['saldo_normal']])) ?>"><i class="bi bi-pencil"></i></button>
        <button class="btn btn-outline-<?= $r['status'] === 'aktif' ? 'danger' : 'success' ?> btn-icon" title="Ubah status"
          data-confirm="<?= $r['status'] === 'aktif' ? 'Nonaktifkan akun ini?' : 'Aktifkan akun ini?' ?>" data-yes="Ya, ubah status" data-action="<?= url('/akun/status/' . $r['id_akun']) ?>"><i class="bi bi-power"></i></button>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php partial('pager', ['pg' => $pg]); endif; ?>
</div></div>

<div class="modal fade" id="akunModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form method="post"><?= csrf_field() ?>
  <div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="form-label req">Kode akun</label><input name="kode_akun" class="form-control" maxlength="20" required data-lock></div>
    <div class="mb-3"><label class="form-label req">Nama akun</label><input name="nama_akun" class="form-control" maxlength="100" required></div>
    <div class="row g-3">
      <div class="col-6"><label class="form-label req">Tipe akun</label><select name="tipe_akun" class="form-select" required data-lock>
        <?php foreach ($tipeList as $t): ?><option value="<?= $t ?>"><?= ucfirst($t) ?></option><?php endforeach; ?></select></div>
      <div class="col-6"><label class="form-label">Saldo normal</label><select name="saldo_normal" class="form-select" data-lock>
        <option value="">Otomatis</option><option value="debit">Debit</option><option value="kredit">Kredit</option></select></div>
    </div>
    <div class="mt-3"><label class="form-label">Kategori</label><input name="kategori_akun" class="form-control" maxlength="30"></div>
    <div class="form-text mt-2">Akun yang sudah dipakai transaksi: kode, tipe, dan saldo normal dikunci.</div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
</form></div></div></div>
