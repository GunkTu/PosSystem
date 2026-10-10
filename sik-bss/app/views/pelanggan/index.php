<div class="d-flex justify-content-between align-items-center mb-3">
  <form class="d-flex gap-2 flex-wrap" method="get">
    <input class="form-control" style="width:240px" name="q" value="<?= e($q) ?>" placeholder="Cari kode / nama / telepon">
    <select class="form-select" style="width:140px" name="status"><option value="">Semua status</option>
      <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option><option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option></select>
    <button class="btn btn-outline-primary"><i class="bi bi-search"></i> Cari</button>
  </form>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pModal" data-add="#pModal" data-action="<?= url('/pelanggan/store') ?>" data-title="Tambah Pelanggan"><i class="bi bi-plus-lg"></i> Tambah</button>
</div>
<div class="card"><div class="card-body">
<?php if (!$pg['rows']): partial('empty'); else: ?>
<div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>No</th><th>Kode</th><th>Nama</th><th>Alamat</th><th>Telepon</th><th>Email</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
  <tbody>
  <?php foreach ($pg['rows'] as $i => $r): ?>
    <tr>
      <td><?= ($pg['page'] - 1) * $pg['per'] + $i + 1 ?></td><td><?= e($r['kode_pelanggan']) ?></td><td><?= e($r['nama_pelanggan']) ?></td>
      <td><?= e($r['alamat']) ?></td><td><?= e($r['telepon']) ?></td><td><?= e($r['email']) ?></td><td><?= badge($r['status']) ?></td>
      <td class="text-end text-nowrap">
        <button class="btn btn-outline-primary btn-icon" title="Ubah" data-bs-toggle="modal" data-bs-target="#pModal" data-edit="#pModal"
          data-action="<?= url('/pelanggan/update/' . $r['id_pelanggan']) ?>" data-title="Ubah Pelanggan"
          data-row="<?= e(json_encode(array_intersect_key($r, array_flip(['kode_pelanggan', 'nama_pelanggan', 'alamat', 'telepon', 'email'])))) ?>"><i class="bi bi-pencil"></i></button>
        <button class="btn btn-outline-<?= $r['status'] === 'aktif' ? 'danger' : 'success' ?> btn-icon" title="Ubah status"
          data-confirm="<?= $r['status'] === 'aktif' ? 'Nonaktifkan pelanggan ini?' : 'Aktifkan pelanggan ini?' ?>" data-yes="Ya, ubah status" data-action="<?= url('/pelanggan/status/' . $r['id_pelanggan']) ?>"><i class="bi bi-power"></i></button>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php partial('pager', ['pg' => $pg]); endif; ?>
</div></div>

<div class="modal fade" id="pModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form method="post"><?= csrf_field() ?>
  <div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-3">
      <div class="col-4"><label class="form-label req">Kode</label><input name="kode_pelanggan" class="form-control" maxlength="20" required></div>
      <div class="col-8"><label class="form-label req">Nama pelanggan</label><input name="nama_pelanggan" class="form-control" maxlength="100" required></div>
      <div class="col-12"><label class="form-label">Alamat</label><textarea name="alamat" class="form-control" rows="2" maxlength="255"></textarea></div>
      <div class="col-6"><label class="form-label">Telepon</label><input name="telepon" class="form-control" maxlength="20"></div>
      <div class="col-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" maxlength="50"></div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
</form></div></div></div>
