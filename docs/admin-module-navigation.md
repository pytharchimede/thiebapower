# Navigation et modules — 19 octobre 2026

La navigation commune utilise des groupes repliables accessibles au clavier. Le groupe de la page active est ouvert au chargement. Le menu mobile présente les mêmes liens, filtrés selon les permissions existantes.

L’accueil général présente les modules et les actions fréquentes. Les accueils de modules sont accessibles sous `/admin/modules/locations`, `/admin/modules/equipements`, `/admin/modules/offres`, `/admin/modules/communication` et `/admin/modules/administration`. Chaque accueil contient ses indicateurs et ses liens autorisés. `/admin/modules/finances` sert également d’entrée aux comptes disposant uniquement des outils payout.

## Pages Finance

- `/admin/finance` : accueil financier, soldes et synthèse.
- `/admin/finance/withdrawals` : formulaire et historique des retraits.
- `/admin/finance/summary` : point financier sur la période.
- `/admin/finance/daily` : points quotidiens.
- `/admin/finance/receipts` : encaissements confirmés.
- `/admin/finance/refunds` : cautions remboursées et confirmées.
- `/admin/finance/cash` : espèces et écritures de caisse.

Les six écrans utilisent les mêmes rapports et les mêmes formulaires qu’avant la séparation. Les URL POST, les contrôles de permissions et de CSRF, les calculs, les références et les appels de paiement ne changent pas. Après une action, la redirection retourne à la page dédiée concernée. Les filtres de période et les exports existants sont conservés. Les nouveaux accueils effectuent uniquement des lectures.

## Déploiement

Aucune migration SQL. Tester dans un worktree avant de modifier le dépôt servant le site. Copier `public/admin-navigation.css` dans la racine publique, puis appliquer le commit. Le shell commun utilise une nouvelle version des URL d’assets pour invalider le cache du navigateur.

Contrôles : syntaxe PHP, `tests/admin_navigation.php`, `tests/admin_presentation.php`, `tests/finance_withdrawals.php`, `tests/finance_balances.php`, `tests/billing_reports.php` et `tests/run.php`.

Contrôle visuel après déploiement : compte propriétaire et compte en lecture seule, menu ordinateur et mobile, groupe actif ouvert, filtres de période, popup soldes et exports. Ne pas envoyer de retrait réel pour tester la navigation.
