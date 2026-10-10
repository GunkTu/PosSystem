<?php /** @var array $rep, $kop */ ?>
<!doctype html><html><head><meta charset="utf-8">
<style>
  @page { margin: 28px 34px; }
  body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1F2A37; }
  h2 { text-align:center; margin:4px 0 0; color:#143863 }
  .sub { text-align:center; margin:2px 0 12px; color:#444 }
  .foot { margin-top:18px; font-size:10px; color:#6B7685 }
</style></head><body>
<?php partial('kop', ['kop' => $kop]); ?>
<h2><?= e($rep['title']) ?></h2>
<div class="sub"><?= e($rep['subtitle']) ?></div>
<?php partial('report_table', ['rep' => $rep]); ?>
<div class="foot">Dicetak pada <?= date('d-m-Y H:i') ?> melalui Sistem Informasi Keuangan.</div>
</body></html>
