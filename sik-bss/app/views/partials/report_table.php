<?php /** @var array $rep */ ?>
<style>
  table.rt { width:100%; border-collapse:collapse; font-size:12px }
  table.rt th { background:#EEF2F8; border-bottom:1px solid #b9c3d3; padding:5px 6px; text-align:left }
  table.rt td { padding:4px 6px; border-bottom:1px solid #eef0f4; vertical-align:top }
  table.rt .r { text-align:right; white-space:nowrap }
  table.rt tr.head td { background:#F4F6FA; font-weight:bold }
  table.rt tr.sub td { color:#6B7685; font-style:italic }
  table.rt tr.total td { font-weight:bold; border-top:1px solid #333; border-bottom:1px solid #333 }
  .rt-note { font-size:11px; color:#444; margin-top:8px }
</style>
<table class="rt">
  <thead><tr><?php foreach ($rep['columns'] as [$label, $al]): ?><th class="<?= $al === 'r' ? 'r' : '' ?>"><?= e($label) ?></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php foreach ($rep['rows'] as $row): ?>
    <tr class="<?= e($row['style']) ?>">
      <?php foreach ($row['cells'] as $i => $c): $al = $rep['columns'][$i][1] ?? 'l'; ?>
        <td class="<?= $al === 'r' ? 'r' : '' ?>"><?= (is_int($c) || is_float($c)) ? ($c < 0 ? '(' . angka(-$c) . ')' : angka($c)) : nl2br(e(str_replace('      ', "\u{00A0}\u{00A0}\u{00A0}\u{00A0}\u{00A0}\u{00A0}", (string)$c))) ?></td>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php foreach ($rep['notes'] as $n): ?><div class="rt-note"><?= e($n) ?></div><?php endforeach; ?>
