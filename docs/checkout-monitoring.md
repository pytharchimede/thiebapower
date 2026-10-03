# Scans, utilisation et notifications

Appliquer `database/migrations/20261003_checkout_notifications.sql` **une seule fois, avant de déployer le code**, après sauvegarde de la base. Elle ajoute deux colonnes aux locations et deux tables de notifications. Elle ne modifie aucun paiement existant. Déployer aussi `public/app.js`, `public/admin-design.css`, `public/admin-design.js` et `public/admin-monitoring.js` dans la racine publique. Recharger les anciennes pages de paiement (elles n'ont pas encore le jeton de soumission).

## Scans et paiements

Un verrou MySQL par station sérialise les synchronisations d'inventaire. Les scans réutilisent un inventaire de moins de 20 secondes, ou de moins de 120 secondes uniquement si une autre synchronisation est en cours. Un inventaire ancien ne devient pas automatiquement disponible. Le checkout vérifie toujours la batterie directement chez HeyCharge, puis conserve le verrou de réservation SQL.

Un jeton aléatoire par formulaire, un verrou MySQL et une colonne unique empêchent de créer deux sessions pour une même soumission. Une session déjà persistée et encore valide est réutilisée. Une demande enregistrée sans session exploitable n'est pas réémise automatiquement. Cette protection ne prétend pas fournir l'idempotence chez PaiementPro en cas de crash externe ; le rapprochement reste nécessaire pour une issue incertaine.

Les lignes `TBP_CHECKOUT` du journal PHP contiennent une référence de diagnostic, l'étape, sa durée en millisecondes et son résultat. Étapes : `checkout_request`, `checkout`, `heycharge_inventory`, `paiementpro_session`. Comparer ces mesures pour identifier la cause d'un ralentissement ; le précédent incident reste inexpliqué faute de logs correspondants.

## Suivi et alertes

Le compteur commence à `started_at` (sortie confirmée), affiche utilisation, durée restante et dépassement, et se fige à `returned_at`. Il ne mesure pas une charge électrique. Chaque seconde est calculée dans le navigateur, avec correction de l'horloge par le serveur. Les données sont vérifiées toutes les 45 secondes lorsque l'onglet est visible ; aucun polling ne se fait dans un onglet masqué. Les fiches et les 100 premières références de compteurs affichées sont rapprochées avec l'état serveur à chaque vérification.

Les notifications sont persistées, dédupliquées par événement et marquées lues par utilisateur. Elles couvrent dépassements, sorties en échec, paiements à rapprocher, remboursements échoués et heartbeat HeyCharge absent depuis plus de quatre minutes en mode normal. Les alertes résolues quittent la cloche et restent dans la base. Les droits existants filtrent l'accès aux alertes et aux données de location ; les mutations exigent le CSRF.

La production des alertes est déclenchée par consultation du dashboard, au plus une fois toutes les 30 secondes pour tous les utilisateurs grâce à un verrou global. Il n'y a pas encore de génération autonome en l'absence de consultation ni d'envoi SMS, email ou push. Pour la suite, appeler `SystemNotifications::refresh()` depuis un worker et ajouter une file de livraison avec reprises et déduplication, indépendante du stockage d'événements.

## Validation

`php tests/run.php`, `php tests/v1_smoke.php`, `php tests/admin_presentation.php`, `php tests/checkout_monitoring.php`, lint PHP et JavaScript. Les fixtures isolées ne contactent aucun fournisseur et ne réalisent aucun paiement. Les verrous sont simulés : un test de charge sur infrastructure de recette reste nécessaire pour quantifier le gain et les limites PHP/MySQL.
