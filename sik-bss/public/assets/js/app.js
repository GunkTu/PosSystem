document.addEventListener('DOMContentLoaded', () => {
  // flash sukses hilang otomatis 5 detik
  document.querySelectorAll('.alert.auto-dismiss').forEach(a => setTimeout(() => bootstrap.Alert.getOrCreateInstance(a).close(), 5000));

  // dialog konfirmasi: <button data-confirm="teks" data-action="url" data-yes="Ya, ...">
  const modalEl = document.getElementById('confirmModal');
  if (modalEl) {
    const modal = new bootstrap.Modal(modalEl);
    document.querySelectorAll('[data-confirm]').forEach(b => b.addEventListener('click', e => {
      e.preventDefault();
      document.getElementById('confirmText').textContent = b.dataset.confirm;
      document.getElementById('confirmForm').action = b.dataset.action;
      document.getElementById('confirmYes').textContent = b.dataset.yes || 'Ya';
      modal.show();
    }));
  }

  // modal form master: [data-edit="#modal"] mengisi field dari data-row (JSON)
  document.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => {
    const m = document.querySelector(b.dataset.edit);
    const row = JSON.parse(b.dataset.row);
    const f = m.querySelector('form');
    f.reset();
    f.action = b.dataset.action;
    m.querySelector('.modal-title').textContent = b.dataset.title;
    Object.entries(row).forEach(([k, v]) => { const el = f.elements[k]; if (el) el.value = v ?? ''; });
    f.querySelectorAll('[data-lock]').forEach(el => el.disabled = b.dataset.locked === '1');
  }));
  document.querySelectorAll('[data-add]').forEach(b => b.addEventListener('click', () => {
    const m = document.querySelector(b.dataset.add);
    const f = m.querySelector('form');
    f.reset();
    f.action = b.dataset.action;
    m.querySelector('.modal-title').textContent = b.dataset.title;
    f.querySelectorAll('[data-lock]').forEach(el => el.disabled = false);
  }));
});
