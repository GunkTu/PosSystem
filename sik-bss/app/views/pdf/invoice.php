<?php /** @var array $inv, $items, $kop */ ?>
<!doctype html><html><head><meta charset="utf-8">
<style>
  @page { margin: 28px 34px; }
  body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1F2A37; }
  table.items { width:100%; border-collapse:collapse; margin-top:12px }
  table.items th { background:#EEF2F8; border:1px solid #cfd6e2; padding:6px; font-size:11px; text-align:left }
  table.items td { border:1px solid #cfd6e2; padding:6px; vertical-align:top }
  .r { text-align:right } .tot td { padding:3px 6px }
  h2 { text-align:center; margin:6px 0 2px; letter-spacing:2px; color:#143863 }
</style></head><body>
<?php partial('kop', ['kop' => $kop]); ?>
<h2>INVOICE</h2>
<div style="text-align:center;margin-bottom:10px"><b><?= e($inv['no_invoice']) ?></b></div>
<table style="width:100%"><tr>
  <td style="width:58%;vertical-align:top">
    <b>Kepada:</b><br><?= e($inv['nama_pelanggan']) ?><br><?= nl2br(e($inv['alamat'])) ?>
    <?= $inv['telepon'] ? '<br>Telp: ' . e($inv['telepon']) : '' ?>
  </td>
  <td style="vertical-align:top">
    <table><tr><td>Tanggal</td><td>: <?= tgl($inv['tanggal']) ?></td></tr>
    <tr><td>Jatuh tempo</td><td>: <?= tgl($inv['jatuh_tempo']) ?></td></tr>
    <tr><td>Status</td><td>: <?= e(ucwords(str_replace('_', ' ', $inv['status_invoice']))) ?></td></tr></table>
  </td></tr></table>
<?php if ($inv['keterangan']): ?><p style="margin:8px 0 0"><b>Keterangan:</b> <?= e($inv['keterangan']) ?></p><?php endif; ?>

<table class="items">
  <thead><tr><th style="width:24px">No</th><th>Deskripsi</th><th class="r" style="width:50px">Qty</th><th style="width:55px">Satuan</th><th class="r" style="width:95px">Harga</th><th class="r" style="width:105px">Subtotal</th></tr></thead>
  <tbody>
  <?php foreach ($items as $i => $it): ?>
    <tr><td><?= $i + 1 ?></td><td><?= e($it['deskripsi']) ?><br><span style="color:#6B7685;font-size:10px"><?= e(ucfirst($it['jenis_layanan'])) ?></span></td>
      <td class="r"><?= rtrim(rtrim(number_format((float)$it['qty'], 2, ',', '.'), '0'), ',') ?></td><td><?= e($it['satuan']) ?></td>
      <td class="r"><?= angka($it['harga_satuan']) ?></td><td class="r"><?= angka($it['subtotal']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<table class="tot" style="width:45%;margin-left:55%;margin-top:6px">
  <tr><td>Subtotal</td><td class="r"><?= rupiah($inv['subtotal']) ?></td></tr>
  <tr><td>PPN <?= rtrim(rtrim(number_format((float)$inv['ppn_persen'], 2, ',', ''), '0'), ',') ?>%</td><td class="r"><?= rupiah($inv['ppn_nominal']) ?></td></tr>
  <tr><td style="border-top:1px solid #333"><b>TOTAL</b></td><td class="r" style="border-top:1px solid #333"><b><?= rupiah($inv['total']) ?></b></td></tr>
  <?php if ($inv['total_terbayar'] > 0): ?>
  <tr><td>Terbayar</td><td class="r"><?= rupiah($inv['total_terbayar']) ?></td></tr>
  <tr><td>Sisa tagihan</td><td class="r"><?= rupiah(max(0, $inv['total'] - $inv['total_terbayar'])) ?></td></tr>
  <?php endif; ?>
</table>

<table style="width:100%;margin-top:26px"><tr>
  <td style="width:58%;vertical-align:top;font-size:11px">
    <b>Pembayaran ke:</b><br>
    <?= e($kop['bank_nama'] ?: 'Nama Bank') ?><br>
    No. Rek: <?= e($kop['bank_no_rekening'] ?: '0000000000') ?><br>
    a.n. <?= e($kop['bank_atas_nama'] ?: ($kop['nama_perusahaan'] ?: 'Nama Perusahaan')) ?>
    <?php if ($kop['catatan_invoice']): ?><br><br><i><?= e($kop['catatan_invoice']) ?></i><?php endif; ?>
  </td>
  <td style="text-align:center;vertical-align:top">
    Hormat kami,<br><br><br><br><br>
    <b><u><?= e($kop['ttd_nama'] ?: '( Nama Penandatangan )') ?></u></b><br><?= e($kop['ttd_jabatan'] ?: 'Jabatan') ?>
  </td></tr></table>
</body></html>
