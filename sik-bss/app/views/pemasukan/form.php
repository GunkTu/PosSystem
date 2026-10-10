<?php
/** @var ?array $row @var array $invoices, $kasBank, $akunPendapatan, $akunModal @var int $preInvoice @var string $action */
$v = fn($k, $d = '') => old($k, $row[$k] ?? $d);
$selInv = (int)old('id_invoice', $preInvoice);
$terkunci = $row !== null;
?>
<form method="post" action="<?= e($action) ?>" class="card" style="max-width:760px"><?= csrf_field() ?>
<div class="card-body"><div class="row g-3">
  <div class="col-12"><label class="form-label">Invoice <span class="text-muted">(kosongkan untuk pendapatan lain / setoran modal)</span></label>
    <select name="id_invoice" id="invSel" class="form-select" <?= $terkunci ? 'disabled' : '' ?>>
      <option value="" data-sisa="">- tanpa invoice -</option>
      <?php foreach ($invoices as $i): ?><option value="<?= $i['id_invoice'] ?>" data-sisa="<?= e($i['sisa']) ?>" <?= $selInv === (int)$i['id_invoice'] ? 'selected' : '' ?>>
        <?= e($i['no_invoice'] . ' - ' . $i['nama_pelanggan'] . ' (sisa ' . angka($i['sisa']) . ')') ?></option><?php endforeach; ?>
    </select>
    <?php if ($terkunci && $row['id_invoice']): ?><input type="hidden" name="id_invoice" value="<?= (int)$row['id_invoice'] ?>"><?php endif; ?>
    <div class="form-text" id="sisaInfo"></div></div>
  <div class="col-md-6"><label class="form-label req">Kas / Bank tujuan</label>
    <select name="id_kas_bank" class="form-select" required>
      <?php foreach ($kasBank as $k): ?><option value="<?= $k['id_kas_bank'] ?>" <?= (int)$v('id_kas_bank') === (int)$k['id_kas_bank'] ? 'selected' : '' ?>><?= e($k['kode_kas_bank'] . ' - ' . $k['nama_kas_bank']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="col-md-6"><label class="form-label req">Akun pendapatan</label>
    <select name="id_akun" id="akunSel" class="form-select" required>
      <optgroup label="Pendapatan">
        <?php foreach ($akunPendapatan as $a): ?><option value="<?= $a['id_akun'] ?>" <?= (int)$v('id_akun') === (int)$a['id_akun'] ? 'selected' : '' ?>><?= e($a['kode_akun'] . ' - ' . $a['nama_akun']) ?></option><?php endforeach; ?>
      </optgroup>
      <optgroup label="Modal (setoran modal, tanpa invoice)" id="grpModal">
        <?php foreach ($akunModal as $a): ?><option value="<?= $a['id_akun'] ?>" <?= (int)$v('id_akun') === (int)$a['id_akun'] ? 'selected' : '' ?>><?= e($a['kode_akun'] . ' - ' . $a['nama_akun']) ?></option><?php endforeach; ?>
      </optgroup>
    </select>
    <div class="form-text">Untuk invoice ber-PPN, bagian PPN otomatis dicatat ke akun PPN Keluaran.</div></div>
  <div class="col-md-4"><label class="form-label req">Tanggal pembayaran</label><input type="date" name="tanggal" class="form-control" required value="<?= e($v('tanggal_pembayaran', date('Y-m-d'))) ?>"></div>
  <div class="col-md-4"><label class="form-label req">Nominal (Rp)</label><input type="number" step="0.01" min="0.01" name="nominal" id="nominal" class="form-control" required value="<?= e($v('nominal_pembayaran')) ?>"></div>
  <div class="col-md-4"><label class="form-label">No. bukti</label><input class="form-control" disabled value="<?= e($row['no_bukti_pemasukan'] ?? 'Otomatis (BKM-YYYYMM-0001)') ?>"></div>
  <div class="col-12"><label class="form-label">Keterangan</label><input name="keterangan" class="form-control" maxlength="100" value="<?= e($v('keterangan_pembayaran')) ?>"></div>
</div></div>
<div class="card-footer bg-transparent text-end"><a href="<?= url($selInv ? '/invoice/' . $selInv : '/pemasukan') ?>" class="btn btn-outline-secondary">Batal</a> <button class="btn btn-primary">Simpan</button></div>
</form>
<?php ob_start(); ?>
<script>
(function(){
  const inv=document.getElementById('invSel'), nom=document.getElementById('nominal'), info=document.getElementById('sisaInfo'),
        grp=document.getElementById('grpModal'), akun=document.getElementById('akunSel');
  function sync(first){
    const o=inv.options[inv.selectedIndex]; const sisa=o?parseFloat(o.dataset.sisa):NaN;
    const adaInvoice = !!inv.value || inv.disabled && <?= $terkunci && $row['id_invoice'] ? 'true' : 'false' ?>;
    grp.disabled = adaInvoice;
    if (adaInvoice && akun.selectedOptions[0] && akun.selectedOptions[0].parentNode===grp) akun.selectedIndex=0;
    if(!isNaN(sisa)){ nom.max=sisa; info.textContent='Sisa tagihan: Rp '+sisa.toLocaleString('id-ID',{minimumFractionDigits:2}); if(!first && !nom.value) nom.value=sisa; if(first && !nom.value) nom.value=sisa; }
    else { nom.removeAttribute('max'); info.textContent=''; }
  }
  inv.addEventListener('change',()=>{nom.value='';sync(false);}); sync(true);
})();
</script>
<?php $scripts = ob_get_clean(); ?>
