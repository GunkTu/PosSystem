<?php /** @var array $pg */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
  <div class="text-muted small">Total <?= (int)$pg['total'] ?> data</div>
  <div class="d-flex align-items-center gap-2">
    <form method="get" class="d-flex align-items-center gap-1">
      <?php foreach ($_GET as $k => $v): if (in_array($k, ['per', 'page'], true) || is_array($v)) continue; ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
      <select name="per" class="form-select form-select-sm" onchange="this.form.submit()">
        <?php foreach ([10, 25, 50] as $n): ?><option <?= $pg['per'] == $n ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?>
      </select><span class="small text-muted">/hal</span>
    </form>
    <?php if ($pg['pages'] > 1): ?>
    <ul class="pagination pagination-sm mb-0">
      <li class="page-item <?= $pg['page'] <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(qs(['page' => $pg['page'] - 1])) ?>">&laquo;</a></li>
      <?php for ($i = max(1, $pg['page'] - 2); $i <= min($pg['pages'], $pg['page'] + 2); $i++): ?>
        <li class="page-item <?= $i == $pg['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= e(qs(['page' => $i])) ?>"><?= $i ?></a></li>
      <?php endfor; ?>
      <li class="page-item <?= $pg['page'] >= $pg['pages'] ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(qs(['page' => $pg['page'] + 1])) ?>">&raquo;</a></li>
    </ul>
    <?php endif; ?>
  </div>
</div>
