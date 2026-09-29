# Déploiement Thiebapower V1 sur cPanel

Le dépôt est cloné dans `/home/ifmapci/repositories/thiebapower`. Le domaine sert `/home/ifmapci/thiebapower.com/index.php`, qui charge `public/index.php` du dépôt. Les fichiers CSS et JS sont copiés dans la racine du domaine.

1. Sauvegarder la base et les fichiers `.env`, `.htaccess`, `index.php` et les ressources publiques. Ne jamais versionner `.env`.
2. Mettre à jour le dépôt avec `git pull --ff-only origin main`.
3. Appliquer une seule fois `database/migrations/20260928_v1_accounts_audit.sql` à `ifmapci_thiebapower_db` avant d'ouvrir `/admin`.
4. Copier `public/style.css`, `public/payout.css` et `public/app.js` vers `/home/ifmapci/thiebapower.com/`, puis `chmod 644` sur ces trois fichiers.
5. Vérifier la syntaxe PHP de `app`, `public`, `views` et `bin`, ainsi que `/admin/login`, `/`, `/admin/payout` et `/admin/audit`.
6. Se connecter à `/admin/login` avec l'ancien identifiant `ADMIN_USERNAME` (ou `admin`) et l'ancien mot de passe administrateur. Si la table `users` est vide, ce premier compte propriétaire est créé à partir du hash déjà présent dans `.env`. Créer ensuite les comptes individuels et attribuer les rôles.

La V1 enregistre toutes les requêtes **qui traversent le front controller PHP** dans `request_events` et les principales actions dans `audit_events`. Les accès directs à `style.css`, `payout.css`, `app.js`, les fichiers statiques, ainsi que les erreurs rejetées par le serveur avant PHP, appartiennent aux journaux HTTP de LiteSpeed/cPanel. Aucun mot de passe, secret marchand ou token API n'est écrit dans l'audit. Aucun effacement automatique n'est configuré ; surveiller la croissance de la base sur l'hébergement mutualisé.

Pour le mode HeyCharge normal et la caution facultative, appliquer ensuite la migration et les réglages du [guide HeyCharge](heycharge-integration.md). Le payout Paiement Pro `INITIATED` sans session ni callback doit rester à rapprocher avec le prestataire avant toute répétition. Cette version ne résout pas rétroactivement les opérations inconnues.
