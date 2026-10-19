# Retrait avec solde et frais

Le formulaire affiche le solde payout daté, le montant reçu par le bénéficiaire, les frais estimés, le total débité estimé et le solde restant. « Tout retirer » remplit le plus grand montant entier compatible avec le solde et le barème.

Le calcul reprend les frais payout par canal configurés dans `deposit_wallet_settings.fee_rules` (également utilisés pour les restitutions de cautions). Aucun taux n’est créé ou modifié. Les frais sont fixes + plafond(montant × points de base / 10 000). Le maximum est calculé en arithmétique entière. Les frais du fournisseur restent à rapprocher du barème contractuel ; il ne s’agit pas d’une API de devis de frais.

À la soumission, après le contrôle d’idempotence et des retraits incertains, le serveur consulte `getBalance` sans cache et recalcule le maximum sous le verrou des soumissions de retrait. Si le solde ou le barème est indisponible, ou si le montant dépasse le maximum frais inclus, il n’insère ni n’envoie de nouveau retrait. Les frais et le solde avant retrait sont enregistrés dans l’audit de la demande. Le contrôle du fournisseur reste final, car d’autres opérations peuvent modifier le wallet.

Les références, statuts, confirmations et déblocages existants restent inchangés. Le cache d’affichage du wallet est invalidé à chaque soumission. Le serveur n’utilise jamais le maximum communiqué par le navigateur comme autorisation.

Déployer les nouveaux fichiers publics `finance-withdrawal.js` et `finance-withdrawal.css`. Aucune migration SQL. Tests : `withdrawal_quote.php`, `finance_withdrawals.php`, `finance_withdrawal_ui.js` (Node), `admin_navigation.php`, `finance_balances.php` et `run.php`.
