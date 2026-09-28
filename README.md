# Thiebapower

Socle PHP 8.2 / MariaDB pour la location de batteries sur hébergement cPanel. Front controller, routes, vues, contrôleurs, services, repository, modèle de calcul et configuration privée.

## Installation

1. `composer install --no-dev --optimize-autoloader` ; créer la base et importer `database/schema.sql`.
2. Copier `.env.example` vers `.env`, renseigner les accès SQL et l'identifiant marchand. Générer `ADMIN_PASSWORD_HASH` avec `php -r 'echo password_hash("mot de passe fort", PASSWORD_DEFAULT), PHP_EOL;'`.
3. Faire pointer le document root du domaine vers `public/` (ou déplacer le contenu de `public/` dans le document root et adapter le chemin de `vendor/autoload.php`). Exiger HTTPS et activer `soap`, `pdo_mysql`, `curl`.
4. Ajouter les numéros de série des batteries via `/admin`. Le callback Paiement Pro est `https://thiebapower.com/api/heycharge/callback` ; le chemin demandé existe aussi en HTTP, mais la notification doit utiliser HTTPS.

## État de l'intégration

Les tarifs, cautions individuelles (y compris zéro), réservations et initialisation SOAP Paiement Pro sont codés. Le retour navigateur n'autorise jamais une libération. Le callback journalise les notifications et renvoie 202 tant que l'authenticité n'est pas vérifiable. Aucune batterie n'est libérée actuellement.

**Informations nécessaires au fournisseur avant activation** : documentation officielle de génération/vérification `hashcode` pour `OnlineServicePayment_v2` ou API de consultation du paiement ; confirmation du traitement de la caution (encaissement puis remboursement partiel/total, ou préautorisation) et procédure de remboursement ; clé et documentation détaillée de l'Open API HeyCharge (commande d'éjection, états, signature des webhooks et retour physique) ; identifiants matériels des stations et batteries. Le PDF SOAP fourni concerne le *payout*, pas l'encaissement en ligne. Ne jamais placer les secrets dans Git.

Le calcul du dépassement est `min(caution, ceil(caution × pourcentage × heures entamées / 100))`. Il est figé par location dans `rentals`. Il commence après `due_at` déterminé sur l'événement confirmé de retrait effectif, et s'arrête à l'événement confirmé de retour. Aucun prélèvement ou remboursement automatique n'est mis en œuvre sans la procédure contractuelle du prestataire.

Les réservations sans paiement doivent être libérées par une tâche d'expiration après ajout d'une vérification de statut auprès de Paiement Pro. Prévoir également CSRF/session admin, limitation des requêtes, journal d'audit et tests d'intégration fournisseur avant l'ouverture publique.

## Option 3 et restitution de caution

L'intégration choisie est **HeyCharge Open API hébergée**. Le serveur mutualisé n'a pas besoin d'écouter les connexions TCP des stations. Le service `HeyChargeOpenApi` attend le contrat officiel ; les chemins de commande et le format des événements ne sont pas inventés.

Après confirmation authentifiée du retour, `DepositSettlementService` calcule `refund = caution - retenue`. Un retour dans le délai donne une retenue de **0 FCFA**, donc le remboursement intégral de la caution, y compris le cas d'une caution nulle. Le calcul ne doit être déclenché que sur un retour confirmé par HeyCharge, puis un remboursement Paiement Pro confirmé doit faire passer `deposit_settlements.status` de `pending` à `refunded`. La documentation d'encaissement fournie n'expose pas cette API de remboursement : ce flux reste volontairement inactif.

Le chemin `/api/heycharge/callback` reçoit actuellement la notification **Paiement Pro**. Il faudra réserver un chemin distinct (par exemple `/api/heycharge/events`) pour les événements des stations, dont le schéma et la signature doivent être obtenus auprès de HeyCharge.
