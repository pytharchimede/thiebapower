(() => {
  'use strict';
  const panel = document.querySelector('#sms-live-result');
  if (!panel) return;
  const status = panel.querySelector('[data-sms-status]');
  const output = panel.querySelector('[data-sms-response]');
  const copy = panel.querySelector('[data-sms-copy]');
  const forms = [...document.querySelectorAll('form[data-sms-test]')];
  let busy = false;
  const messages = {
    authenticated: 'Authentification réussie.',
    checked: 'Consultation Orange réussie.',
    simulated: 'Simulation réussie : aucun SMS envoyé.',
    accepted: 'SMS accepté par Orange. La livraison au téléphone reste à confirmer.',
    rejected: 'SMS refusé par Orange : consultez le code de refus ci-dessous.',
    unknown: 'Résultat inconnu : vérifiez chez Orange avant de recommencer.'
  };
  copy.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(output.textContent); copy.textContent = 'Réponse copiée'; }
    catch { copy.textContent = 'Sélectionnez le texte pour le copier'; }
  });
  for (const form of forms) form.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy) return;
    busy = true;
    const payload = new FormData(form);
    const buttons = forms.flatMap(f => [...f.querySelectorAll('button')]);
    const disabled = buttons.map(b => b.disabled);
    buttons.forEach(b => { b.disabled = true; });
    panel.hidden = false;
    status.textContent = 'Contrôle en cours…';
    output.textContent = 'Connexion à Orange. Aucun nouvel essai ne sera lancé automatiquement.';
    copy.hidden = true;
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    panel.focus({ preventScroll: true });
    try {
      const response = await fetch(form.action, {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json' }, body: payload
      });
      if (!response.headers.get('content-type')?.includes('application/json')) {
        throw new Error(response.status === 403 ? 'Accès refusé ou formulaire expiré. Vérifiez TECHNICAL_ADMIN_USERNAME et rechargez la page.' :
          response.status === 401 || response.redirected ? 'Connexion requise. Rechargez la page et reconnectez-vous.' :
          'Réponse du serveur non exploitable (HTTP ' + response.status + '). Vérifiez le déploiement du contrôleur SMS.');
      }
      const result = await response.json();
      if (result.next_nonce) {
        forms.forEach(f => { const n = f.querySelector('[name="nonce"]'); if (n) n.value = result.next_nonce; });
      }
      const { next_nonce, ...safe } = result;
      status.textContent = result.error || messages[result.state] || 'Contrôle terminé.';
      output.textContent = JSON.stringify(safe, null, 2);
      copy.textContent = 'Copier la réponse'; copy.hidden = false;
    } catch (error) {
      status.textContent = error.message || 'Connexion interrompue.';
      output.textContent = 'Si vous avez demandé un envoi réel, consultez l’historique avant de recommencer. Aucun nouvel envoi automatique.';
    } finally {
      buttons.forEach((b, i) => { b.disabled = disabled[i]; });
      busy = false;
    }
  });
})();
