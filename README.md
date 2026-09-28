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

Les modes sont persistés dans SQL, sans stocker de clés dans Git. `PAIEMENTPRO_MERCHANT_ID` et `PAIEMENTPRO_SECRET_KEY` sont les secrets de production ; les variables `PAIEMENTPRO_SANDBOX_*` sont distinctes. Aucune URL sandbox n'est fournie dans les PDF : demander au prestataire son WSDL d'encaissement, son URL de redirection et son WSDL de reversement de test. Ne jamais pointer une sandbox vers les URL de production. Le service de reversement calcule le token HMAC SHA-256 du PDF SOAP. Le banc d’essai administrateur peut initier un reversement de 200 FCFA lorsque son indicateur est activé ; le parcours client demeure fermé.

HeyCharge simulation produit seulement un aperçu et ne prétend pas libérer physiquement la batterie. Le mode normal attend la documentation détaillée de l'Open API, la clé et les événements signés. La route publique de location reste fermée en mode normal. Un essai contrôlé est disponible en simulation avec les deux indicateurs explicitement activés, mais l’encaissement doit alors être confirmé manuellement dans le portail Paiement Pro. La notification Paiement Pro attend une méthode officielle de vérification du `hashcode` ; ni l'affichage du mode production ni la présence d'identifiants n'activent le paiement.

## Reversement automatique de caution

La migration `database/migrations/20260928_automatic_deposit_refunds.sql` ajoute le canal du bénéficiaire et l'état `unknown` pour les issues de payout non rapprochées. `AutomaticDepositRefundService::recordVerifiedReturn()` fige une seule fois la retenue et le montant à rendre, avec verrouillage de la location. Pour une caution de 200 FCFA : retour à temps = 200 FCFA à restituer ; une heure entamée de retard avec 10 % = 180 FCFA ; la retenue ne dépasse jamais 200 FCFA. `dispatch()` réserve une référence unique et prépare un reversement SOAP. En cas de réponse incertaine, il marque `unknown` sans relancer automatiquement une opération pouvant avoir réussi.

Le service de restitution est relié au parcours interne après un retour physique confirmé ; aucun événement public HeyCharge n’est encore accepté. Le worker CLI est prêt, mais le cron et `AUTOMATIC_REFUNDS_ENABLED` doivent rester désactivés jusqu’à l’authentification des événements et des essais complets de rapprochement.

La migration `database/migrations/20260928_payout_status.sql` ajoute l'identifiant de session du reversement. Le service SOAP implémente `getTransStatus` avec le token HMAC documenté et `reconcile()` ne clôture un reversement que si le statut est `SUCCESS` et que référence et montant correspondent. Les issues sans session restent `unknown` et exigent un rapprochement fournisseur avant toute répétition.

## Banc d'essai financier administrateur

Appliquer `database/migrations/20260928_payment_lab.sql`. Le banc d'essai est fermé par défaut (`PAYMENT_LAB_PAYIN_ENABLED=0` et `PAYMENT_LAB_PAYOUT_ENABLED=0` dans `.env`). Lorsqu'un essai est activé, les formulaires administrateur fixent un encaissement à 300 FCFA et un reversement séparé à 200 FCFA, chacun avec une confirmation explicite. Les opérations sont consignées et le reversement doté d'une session est vérifiable avec `getTransStatus`. Une notification d'encaissement est classée `notification_unverified`, jamais comme paiement réussi. Une issue de reversement inconnue interdit un nouvel essai jusqu'au rapprochement fournisseur. Les secrets restent exclusivement dans `.env`.

## Parcours de location intégré, fermé par défaut

La migration `database/migrations/20260928_rental_checkout_lifecycle.sql` ajoute le code station, la session de paiement et l'expiration de réservation. `RentalCheckoutService` réserve une batterie sous verrou, fige le tarif et la caution, initie Paiement Pro et libère la réservation si l'initialisation échoue. `RentalLifecycleService` attend une confirmation de paiement authentifiée avant de commander une libération ; la durée commence uniquement à l'événement de sortie physique HeyCharge vérifié. Le retour physique vérifié fige la retenue et prépare le remboursement. `bin/refund_worker.php` traite les reversements en attente et interroge leur statut par session ; il ne doit être planifié en cron qu'après activation de `AUTOMATIC_REFUNDS_ENABLED=1`.

`PUBLIC_RENTALS_ENABLED=0` et `AUTOMATIC_REFUNDS_ENABLED=0` restent les valeurs par défaut. Même si quelqu'un active la première variable trop tôt, `PaymentVerification::ready()` retourne false jusqu'à l'implémentation de la vérification officielle du callback Paiement Pro. La libération physique HeyCharge exige les commandes et événements signés de l'option 3. Le code ne doit pas être rendu actif sur le kiosque avant ces intégrations et leurs essais complets. Le banc d'essai administrateur reste indépendant de ce parcours.

## Essai complet avec station simulée et transactions réelles

Appliquer `database/migrations/20260928_simulated_rental_events.sql`. Dans `/admin`, choisir Paiement Pro **production** et HeyCharge **simulation**, définir 100 FCFA de location et 200 FCFA de caution, et enregistrer une batterie. Dans `.env`, régler `PUBLIC_RENTALS_ENABLED=1` et `SIMULATED_RENTALS_ENABLED=1` pour afficher le formulaire du kiosque. Ces indicateurs ouvrent des encaissements réels ; l’API HeyCharge ne reçoit aucune commande.

Sur le kiosque, saisir un code station de test, choisir la batterie et payer 300 FCFA. Le retour navigateur et la notification ne prouvent pas l’encaissement. Dans le portail Paiement Pro, contrôler la **référence TBP**, la **session**, le **montant** et la réussite effective de la transaction. Dans `/admin#simulation`, recopier la référence et inscrire une preuve fournisseur distincte avant de simuler la sortie. La durée commence à cet instant. Simuler ensuite le retour ; le calcul fige la restitution, et l’opération est enregistrée une seule fois.

Après vérification de l’habilitation au reversement Paiement Pro et du numéro bénéficiaire, `AUTOMATIC_REFUNDS_ENABLED=1` autorise un vrai `initTransact` au retour ; la restitution est entière dans le délai. La réponse d’initiation ne prouve pas la réception des fonds : utiliser « Vérifier le reversement » ou exécuter `php bin/refund_worker.php` par cron pour interroger `getTransStatus`. Un échec explicite est marqué `failed`, une réponse incertaine `unknown` ; ne jamais répéter automatiquement le paiement dans ce cas. Si l’option est désactivée, le règlement reste `pending` et le worker ne traite rien. Les actions de simulation sont réservées à l’administration avec authentification et jeton CSRF ; chaque sortie et retour est journalisé.

## Console des reversements et retours API

Appliquer `database/migrations/20260928_payout_api_events.sql` puis ouvrir `/admin/payout`. Cette page affiche les cautions et les essais de reversement, leurs références, sessions et statuts, ainsi que les réponses filtrées de `initTransact`, `getTransStatus`, des erreurs et des callbacks. Les tokens et clés ne sont jamais enregistrés dans ce journal. Un callback est **non vérifié** : il est affiché pour diagnostic et ne confirme pas le versement. La page de test payout est indépendante des cautions et ne réémet jamais la référence `TBP-REFUND-1`. Un essai payout non résolu bloque un nouvel essai payout.

Appliquer également `database/migrations/20260928_payout_request_snapshots.sql` pour conserver les paramètres filtrés envoyés à l’API. La page `/admin/payout` utilise un thème clair autonome, affiche le WSDL et la méthode SOAP, le formulaire de test de 200 FCFA, la requête envoyée (token masqué) et la réponse reçue. Le formulaire est disponible lorsque `PAYMENT_LAB_PAYOUT_ENABLED=1` ou `AUTOMATIC_REFUNDS_ENABLED=1`.

## Clôture d'un essai payout sans session

Appliquer `database/migrations/20260928_payout_archive.sql`. Une réponse `INITIATED` avec code `0` mais sans `sessionid` signifie seulement que l'API a reçu la demande : elle n'atteste pas le versement, et `getTransStatus` n'est pas disponible. Dans `/admin/payout`, un administrateur peut, après vérification du résultat auprès de Paiement Pro, recopier la référence, saisir le motif et clore localement cet essai. La clôture est datée, conserve la réponse et ne fait **aucun appel d'annulation** au fournisseur. Elle autorise un nouvel essai avec une nouvelle référence ; jamais la réémission de la référence précédente.

## Diagnostic du callback payout

`GET /api/paiementpro/payout-callback` sans paramètres répond `payout callback ready` pour vérifier le routage. Les notifications GET ou POST consignent méthode, type de contenu, noms des champs, empreinte SHA-256 du corps et champs de statut autorisés dans `payout_api_events`. Une référence inconnue est classée `UNMATCHED` et limitée à une entrée par minute. Aucun callback non authentifié ne marque un versement comme réussi. Les journaux d'accès du serveur restent nécessaires pour savoir si le prestataire a appelé l'URL lors d'un ancien essai.

Le service payout normalise les numéros ivoiriens : `0748367710` et `2250748367710` deviennent `+2250748367710` avant l'appel SOAP. Les références déjà initiées conservent leurs paramètres historiques : ne jamais les réémettre sans confirmation de leur issue auprès de Paiement Pro.
