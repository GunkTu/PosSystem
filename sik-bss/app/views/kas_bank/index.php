<div class="d-flex justify-content-between align-items-center mb-3">
  <form class="d-flex gap-2 flex-wrap" method="get">
    <input class="form-control" style="width:240px" name="q" value="<?= e($q) ?>" placeholder="Cari kode / nama / no. rekening">
    <select class="form-select" style="width:140px" name="status"><option value="">Semua status</option>
      <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option><option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option></select>
    <button class="btn btn-outline-primary"><i class="bi bi-search"></i> Cari</button>
  </form>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kbModal" data-add="#kbModal" data-action="<?= url('/kas-bank/store') ?>" data-title="Tambah Kas / Bank"><i class="bi bi-plus-lg"></i> Tambah</button>
</div>
<div class="card"><div class="card-body">
<?php if (!$pg['rows']): partial('empty'); else: ?>
<div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>No</th><th>Kode</th><th>Nama</th><th>Jenis</th><th>No. Rekening</th><th>Akun</th><th class="num">Saldo Awal</th><th class="num">Saldo</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
  <tbody>
  <?php foreach ($pg['rows'] as $i => $r): ?>
    <tr>
      <td><?= ($pg['page'] - 1) * $pg['per'] + $i + 1 ?></td><td><?= e($r['kode_kas_bank']) ?></td><td><?= e($r['nama_kas_bank']) ?></td>
      <td><?= ucfirst(e($r['jenis_kas_bank'])) ?></td><td><?= e($r['no_rekening']) ?></td><td><?= e($r['nama_akun']) ?></td>
      <td class="num"><?= angka($r['saldo_awal']) ?></td><td class="num fw-semibold"><?= angka($r['saldo']) ?></td><td><?= badge($r['status']) ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-outline-secondary btn-icon" title="Mutasi" href="<?= url('/kas-bank/mutasi/' . $r['id_kas_bank']) ?>"><i class="bi bi-list-ul"></i></a>
        <button class="btn btn-outline-primary btn-icon" title="Ubah" data-bs-toggle="modal" data-bs-target="#kbModal" data-edit="#kbModal"
          data-locked="<?= $r['mutasi'] > 0 ? 1 : 0 ?>" data-action="<?= url('/kas-bank/update/' . $r['id_kas_bank']) ?>" data-title="Ubah Kas / Bank"
          data-row="<?= e(json_encode(['id_akun' => $r['id_akun'], 'kode_kas_bank' => $r['kode_kas_bank'], 'nama_kas_bank' => $r['nama_kas_bank'], 'jenis_kas_bank' => $r['jenis_kas_bank'], 'no_rekening' => $r['no_rekening'], 'saldo_awal' => $r['saldo_awal']])) ?>"><i class="bi bi-pencil"></i></button>
        <button class="btn btn-outline-<?= $r['status'] === 'aktif' ? 'danger' : 'success' ?> btn-icon" title="Ubah status"
          data-confirm="<?= $r['status'] === 'aktif' ? 'Nonaktifkan kas/bank ini?' : 'Aktifkan kas/bank ini?' ?>" data-yes="Ya, ubah status" data-action="<?= url('/kas-bank/status/' . $r['id_kas_bank']) ?>"><i class="bi bi-power"></i></button>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php partial('pager', ['pg' => $pg]); endif; ?>
</div></div>

<div class="modal fade" id="kbModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form method="post"><?= csrf_field() ?>
  <div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3">
    <div class="col-4"><label class="form-label req">Kode</label><input name="kode_kas_bank" class="form-control" maxlength="20" required></div>
    <div class="col-8"><label class="form-label req">Nama</label><input name="nama_kas_bank" class="form-control" maxlength="100" required></div>
    <div class="col-6"><label class="form-label req">Jenis</label><select name="jenis_kas_bank" class="form-select"><option value="kas">Kas</option><option value="bank">Bank</option></select></div>
    <div class="col-6"><label class="form-label">No. rekening</label><input name="no_rekening" class="form-control" maxlength="30"></div>
    <div class="col-12"><label class="form-label req">Akun aset terkait</label><select name="id_akun" class="form-select" required data-lock>
      <?php foreach ($akun as $a): ?><option value="<?= $a['id_akun'] ?>"><?= e($a['kode_akun'] . ' - ' . $a['nama_akun']) ?></option><?php endforeach; ?></select></div>
    <div class="col-6"><label class="form-label">Saldo awal (Rp)</label><input type="number" step="0.01" min="0" name="saldo_awal" value="0" class="form-control" data-lock></div>
    <div class="col-6"><label class="form-label">Tanggal saldo awal</label><input type="date" name="tanggal_saldo" value="<?= date('Y-m-d') ?>" class="form-control" data-lock></div>
    <div class="col-12 form-text">Saldo hanya berubah lewat transaksi. Akun, saldo awal, dan tanggalnya dikunci setelah ada mutasi.</div>
  </div></div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
</form></div></div></div>
