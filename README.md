# Thiebapower · V1

Application PHP 8.2+ et MariaDB pour la location de batteries externes sur cPanel. Le dépôt sépare les routes, contrôleurs, services, modèle, vues et accès aux données. Le domaine `thiebapower.com` charge `public/index.php` depuis `/home/ifmapci/repositories/thiebapower`, tandis que les ressources publiques sont copiées dans `/home/ifmapci/thiebapower.com/`.

## Mise en service

Lire [le guide de déploiement](docs/v1-deployment.md). Les migrations antérieures sont déjà appliquées sur l'installation existante ; tirer le code puis appliquer `database/migrations/20260928_v1_accounts_audit.sql` **avant** de rouvrir `/admin`. Ne jamais publier `.env`. Vérifier les fichiers avec `find app public views bin tests -name '*.php' -print0 | xargs -0 -n1 php -l` et `php tests/v1_smoke.php`.

Le premier compte propriétaire est créé à la première connexion avec `ADMIN_USERNAME` (`admin` par défaut) et le hash `ADMIN_PASSWORD_HASH` de `.env`. Les comptes individuels et les permissions se gèrent ensuite dans `/admin/users`. Les visites des routes PHP, tentatives de connexion et actions sensibles sont visibles dans `/admin/audit` ; les fichiers statiques apparaissent dans les journaux d'accès cPanel. Les secrets et mots de passe ne sont pas enregistrés dans ces journaux applicatifs.

## Parcours financier et matériel

L'encaissement Paiement Pro et le reversement sont des opérations **réelles** lorsqu'ils sont activés dans `.env`. Une réponse payout `INITIATED` ne prouve pas un crédit : conserver la référence et attendre un rapprochement fournisseur. Les callbacks payout ne sont pas authentifiés par une signature documentée et ne peuvent donc pas attester seuls un versement. Les références dont l'issue est inconnue ne sont jamais réémises automatiquement. `/admin/payout` conserve les requêtes filtrées, réponses et états des essais ; la clôture locale n'annule pas une transaction chez le fournisseur.

En mode HeyCharge simulation, l'administrateur vérifie séparément le paiement, simule la sortie et le retour, puis la caution est calculée d'après l'heure de retour. Une caution de 200 FCFA rendue dans le délai donne 200 FCFA à restituer. Le worker `bin/refund_worker.php` traite les reversements seulement si `AUTOMATIC_REFUNDS_ENABLED=1`. Le mode normal utilise l’API des terminaux HeyCharge et rapproche automatiquement les états physiques. La caution est désactivable dans Tarification pour les nouvelles locations. Lire [la mise en service HeyCharge](docs/heycharge-integration.md) avant ouverture du kiosque.

Ne pas activer un essai financier réel sans avoir vérifié le bénéficiaire et le mode choisi. `PUBLIC_RENTALS_ENABLED` et `SIMULATED_RENTALS_ENABLED` contrôlent l'ouverture du kiosque pour le parcours de simulation ; les essais financiers de l'administration ont leurs propres indicateurs `PAYMENT_LAB_PAYIN_ENABLED` et `PAYMENT_LAB_PAYOUT_ENABLED`.
