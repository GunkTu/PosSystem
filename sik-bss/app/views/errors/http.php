<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kesalahan <?= (int)$status ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="bg-light"><div class="container py-5 text-center">
<h1 class="display-3 fw-bold text-primary"><?= (int)$status ?></h1>
<p class="lead"><?= e($message) ?></p>
<a class="btn btn-primary" href="<?= e(url('/dashboard')) ?>">Kembali ke Dashboard</a>
</div></body></html>
