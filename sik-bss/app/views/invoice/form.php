<?php
/** @var ?array $inv @var array $items, $pelanggan, $layanan @var bool $locked @var float $ppnDefault @var string $action */
$oldItems = [];
$od = $_SESSION['_old']['item_deskripsi'] ?? null;
if (is_array($od)) {
    foreach ($od as $i => $d) $oldItems[] = ['jenis_layanan' => $_SESSION['_old']['item_jenis'][$i] ?? 'lainnya', 'deskripsi' => $d,
        'qty' => $_SESSION['_old']['item_qty'][$i] ?? 1, 'satuan' => $_SESSION['_old']['item_satuan'][$i] ?? '', 'harga_satuan' => $_SESSION['_old']['item_harga'][$i] ?? 0];
}
$rows = $oldItems ?: $items;
if (!$rows) $rows = [['jenis_layanan' => 'freight', 'deskripsi' => '', 'qty' => 1, 'satuan' => '', 'harga_satuan' => '']];
$val = fn($k, $d = '') => old($k, $inv[$k] ?? $d);
?>
<?php if ($locked): ?>
  <div class="alert alert-info"><i class="bi bi-lock"></i> Invoice ini sudah memiliki pembayaran. Item dan total dikunci; hanya jatuh tempo dan keterangan yang dapat diubah.</div>
<?php endif; ?>
<form method="post" action="<?= e($action) ?>" id="invForm"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body"><div class="row g-3">
  <div class="col-md-5"><label class="form-label req">Pelanggan</label>
    <select name="id_pelanggan" class="form-select" required <?= $locked ? 'disabled' : '' ?>>
      <option value="">- pilih -</option>
      <?php foreach ($pelanggan as $p): ?><option value="<?= $p['id_pelanggan'] ?>" <?= (int)$val('id_pelanggan') === (int)$p['id_pelanggan'] ? 'selected' : '' ?>><?= e($p['kode_pelanggan'] . ' - ' . $p['nama_pelanggan']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="col-md-2"><label class="form-label req">Tanggal</label><input type="date" name="tanggal" class="form-control" required value="<?= e($val('tanggal', date('Y-m-d'))) ?>" <?= $locked ? 'disabled' : '' ?>></div>
  <div class="col-md-2"><label class="form-label req">Jatuh tempo</label><input type="date" name="jatuh_tempo" class="form-control" required value="<?= e($val('jatuh_tempo', date('Y-m-d', strtotime('+30 days')))) ?>"></div>
  <div class="col-md-3"><label class="form-label req">PPN (%)</label><input type="number" step="0.01" min="0" max="100" name="ppn_persen" id="ppn" class="form-control" value="<?= e($val('ppn_persen', $ppnDefault)) ?>" <?= $locked ? 'disabled' : '' ?>></div>
  <div class="col-12"><label class="form-label">Keterangan</label><input name="keterangan" class="form-control" maxlength="255" value="<?= e($val('keterangan')) ?>"></div>
</div></div></div>

<div class="card mb-3"><div class="card-body">
  <div class="d-flex justify-content-between mb-2"><div class="card-title mb-0">Item Layanan</div>
    <?php if (!$locked): ?><button type="button" class="btn btn-outline-primary btn-sm" id="addRow"><i class="bi bi-plus-lg"></i> Tambah item</button><?php endif; ?></div>
  <div class="table-responsive"><table class="table align-middle" id="itemTable">
    <thead><tr><th style="width:140px">Layanan</th><th>Deskripsi</th><th style="width:90px">Qty</th><th style="width:100px">Satuan</th><th style="width:150px">Harga satuan</th><th class="num" style="width:150px">Subtotal</th><th style="width:40px"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><select name="item_jenis[]" class="form-select form-select-sm" <?= $locked ? 'disabled' : '' ?>><?php foreach ($layanan as $k => $v): ?><option value="<?= $k ?>" <?= $r['jenis_layanan'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></td>
        <td><input name="item_deskripsi[]" class="form-control form-control-sm" maxlength="150" value="<?= e($r['deskripsi']) ?>" <?= $locked ? 'disabled' : '' ?>></td>
        <td><input type="number" step="0.01" min="0" name="item_qty[]" class="form-control form-control-sm qty" value="<?= e($r['qty']) ?>" <?= $locked ? 'disabled' : '' ?>></td>
        <td><input name="item_satuan[]" class="form-control form-control-sm" maxlength="20" value="<?= e($r['satuan']) ?>" <?= $locked ? 'disabled' : '' ?>></td>
        <td><input type="number" step="0.01" min="0" name="item_harga[]" class="form-control form-control-sm harga" value="<?= e($r['harga_satuan']) ?>" <?= $locked ? 'disabled' : '' ?>></td>
        <td class="num sub">0,00</td>
        <td><?php if (!$locked): ?><button type="button" class="btn btn-outline-danger btn-icon rm" title="Hapus baris"><i class="bi bi-x-lg"></i></button><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="row justify-content-end"><div class="col-md-5"><table class="table table-sm mb-0">
    <tr><td>Subtotal</td><td class="num" id="tSub">0,00</td></tr>
    <tr><td>PPN <span id="tPpnP">0</span>%</td><td class="num" id="tPpn">0,00</td></tr>
    <tr class="fw-bold fs-6"><td>Total</td><td class="num" id="tTotal">0,00</td></tr>
  </table><div class="form-text text-end">Perhitungan final dilakukan ulang di server.</div></div></div>
</div></div>
<div class="text-end">
  <a href="<?= url($inv ? '/invoice/' . $inv['id_invoice'] : '/invoice') ?>" class="btn btn-outline-secondary">Batal</a>
  <button class="btn btn-primary">Simpan</button>
</div>
</form>
<template id="rowTpl"><tr>
  <td><select name="item_jenis[]" class="form-select form-select-sm"><?php foreach ($layanan as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></td>
  <td><input name="item_deskripsi[]" class="form-control form-control-sm" maxlength="150"></td>
  <td><input type="number" step="0.01" min="0" name="item_qty[]" class="form-control form-control-sm qty" value="1"></td>
  <td><input name="item_satuan[]" class="form-control form-control-sm" maxlength="20"></td>
  <td><input type="number" step="0.01" min="0" name="item_harga[]" class="form-control form-control-sm harga"></td>
  <td class="num sub">0,00</td>
  <td><button type="button" class="btn btn-outline-danger btn-icon rm" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
</tr></template>
<?php ob_start(); ?>
<script>
(function(){
  const fmt = n => n.toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2});
  const tb = document.querySelector('#itemTable tbody');
  function calc(){
    let sub=0;
    tb.querySelectorAll('tr').forEach(tr=>{
      const q=parseFloat(tr.querySelector('.qty').value)||0, h=parseFloat(tr.querySelector('.harga').value)||0;
      const s=Math.round(q*h*100)/100; sub+=s; tr.querySelector('.sub').textContent=fmt(s);
    });
    const p=parseFloat(document.getElementById('ppn').value)||0;
    const ppn=Math.round(sub*p)/100;
    document.getElementById('tSub').textContent=fmt(sub);
    document.getElementById('tPpnP').textContent=p;
    document.getElementById('tPpn').textContent=fmt(ppn);
    document.getElementById('tTotal').textContent=fmt(sub+ppn);
  }
  document.getElementById('invForm').addEventListener('input',calc);
  const add=document.getElementById('addRow');
  if(add) add.addEventListener('click',()=>{tb.appendChild(document.getElementById('rowTpl').content.cloneNode(true));calc();});
  tb.addEventListener('click',e=>{const b=e.target.closest('.rm'); if(!b) return; if(tb.rows.length>1){b.closest('tr').remove();calc();}});
  calc();
})();
</script>
<?php $scripts = ob_get_clean(); ?>
