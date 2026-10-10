<?php
/** @var array $kop dari KopService::siap() */
?>
<?php if ($kop['pakai_gambar']): ?>
  <div style="margin-bottom:10px"><img src="<?= $kop['gambar_uri'] ?>" style="width:100%;display:block" alt="Kop"></div>
<?php else: ?>
  <table style="width:100%;border-bottom:2px solid #1E4D8C;margin-bottom:12px;padding-bottom:8px" cellspacing="0" cellpadding="0">
    <tr>
      <td style="width:90px;vertical-align:middle">
        <?php if ($kop['logo_uri']): ?>
          <img src="<?= $kop['logo_uri'] ?>" style="max-width:80px;max-height:80px" alt="Logo">
        <?php else: ?>
          <div style="width:76px;height:76px;border:2px dashed #aab;text-align:center;line-height:76px;color:#889;font-size:11px">Logo</div>
        <?php endif; ?>
      </td>
      <td style="vertical-align:middle;padding-left:10px;padding-bottom:6px">
        <div style="font-size:17px;font-weight:bold;color:#143863"><?= e($kop['nama_perusahaan'] ?: 'Nama Perusahaan') ?></div>
        <div style="font-size:11px;color:#333"><?= e($kop['alamat'] ?: 'Alamat perusahaan') ?></div>
        <div style="font-size:11px;color:#333">
          <?= $kop['telepon'] ? 'Telp: ' . e($kop['telepon']) . ' ' : '' ?>
          <?= $kop['email'] ? '| Email: ' . e($kop['email']) . ' ' : '' ?>
          <?= $kop['npwp'] ? '| NPWP: ' . e($kop['npwp']) : '' ?>
        </div>
      </td>
    </tr>
  </table>
<?php endif; ?>
