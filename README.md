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

## Tableau de bord et environnements

La migration `database/migrations/20260928_integration_modes.sql` ajoute les modes. `/admin` présente les indicateurs, les dernières locations, les tarifs, les batteries et deux groupes de boutons radio : Paiement Pro sandbox/production et HeyCharge simulation/normal. Le nom d'utilisateur HTTP Basic est `admin` par défaut (`ADMIN_USERNAME` dans `.env`) ; les formulaires d'administration utilisent un jeton CSRF.

Les modes sont persistés dans SQL, sans stocker de clés dans Git. `PAIEMENTPRO_MERCHANT_ID` et `PAIEMENTPRO_SECRET_KEY` sont les secrets de production ; les variables `PAIEMENTPRO_SANDBOX_*` sont distinctes. Aucune URL sandbox n'est fournie dans les PDF : demander au prestataire son WSDL d'encaissement, son URL de redirection et son WSDL de reversement de test. Ne jamais pointer une sandbox vers les URL de production. Le service de reversement prépare le token HMAC SHA-256 décrit dans le PDF SOAP, mais aucun endpoint ne déclenche un reversement tant que le suivi de statut et la réconciliation ne sont pas codés.

HeyCharge simulation produit seulement un aperçu et ne prétend pas libérer physiquement la batterie. Le mode normal attend la documentation détaillée de l'Open API, la clé et les événements signés. La route publique de location reste fermée dans les deux modes. La notification Paiement Pro attend une méthode officielle de vérification du `hashcode` ; ni l'affichage du mode production ni la présence d'identifiants n'activent le paiement.

## Reversement automatique de caution

La migration `database/migrations/20260928_automatic_deposit_refunds.sql` ajoute le canal du bénéficiaire et l'état `unknown` pour les issues de payout non rapprochées. `AutomaticDepositRefundService::recordVerifiedReturn()` fige une seule fois la retenue et le montant à rendre, avec verrouillage de la location. Pour une caution de 200 FCFA : retour à temps = 200 FCFA à restituer ; une heure entamée de retard avec 10 % = 180 FCFA ; la retenue ne dépasse jamais 200 FCFA. `dispatch()` réserve une référence unique et prépare un reversement SOAP. En cas de réponse incertaine, il marque `unknown` sans relancer automatiquement une opération pouvant avoir réussi.

Ce service n'est pas relié à une route publique ni à un cron tant que les événements de retour HeyCharge ne peuvent pas être authentifiés et que le résultat du reversement Paiement Pro ne peut pas être rapproché par référence. Après réception de ces contrats API, brancher l'événement signé à `recordVerifiedReturn()`, enregistrer le canal choisi et la transaction d'encaissement confirmée, puis déclencher le worker `dispatch()` et rapprocher l'issue fournisseur avant de passer à `refunded`.

La migration `database/migrations/20260928_payout_status.sql` ajoute l'identifiant de session du reversement. Le service SOAP implémente `getTransStatus` avec le token HMAC documenté et `reconcile()` ne clôture un reversement que si le statut est `SUCCESS` et que référence et montant correspondent. Les issues sans session restent `unknown` et exigent un rapprochement fournisseur avant toute répétition.

## Banc d'essai financier administrateur

Appliquer `database/migrations/20260928_payment_lab.sql`. Le banc d'essai est fermé par défaut (`PAYMENT_LAB_PAYIN_ENABLED=0` et `PAYMENT_LAB_PAYOUT_ENABLED=0` dans `.env`). Lorsqu'un essai est activé, les formulaires administrateur fixent un encaissement à 300 FCFA et un reversement séparé à 200 FCFA, chacun avec une confirmation explicite. Les opérations sont consignées et le reversement doté d'une session est vérifiable avec `getTransStatus`. Une notification d'encaissement est classée `notification_unverified`, jamais comme paiement réussi. Une issue de reversement inconnue interdit un nouvel essai jusqu'au rapprochement fournisseur. Les secrets restent exclusivement dans `.env`.
