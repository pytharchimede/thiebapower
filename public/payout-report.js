'use strict';
document.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-copy-report]');
  if (!button) return;
  const target = document.getElementById(button.dataset.copyReport);
  const feedback = document.querySelector(`[data-copy-feedback="${button.dataset.copyReport}"]`);
  try {
    await navigator.clipboard.writeText(target.value);
    feedback.textContent = 'Rapport copié.';
  } catch (_) {
    // Keep a usable fallback when clipboard permissions or HTTPS are unavailable.
    target.closest('details').open = true;
    target.focus();
    target.select();
    let copied = false;
    try { copied = document.execCommand('copy'); } catch (_) {}
    feedback.textContent = copied ? 'Rapport copié.' : 'Texte sélectionné : utilisez Ctrl+C pour copier.';
  }
});
