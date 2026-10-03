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

## Administration système et réduction des lectures (mise à jour)

`/admin/system` exige le droit `system.manage`, accordé implicitement au propriétaire. Ce droit peut être attribué aux autres rôles depuis Comptes et droits. Le réglage global des compteurs est enregistré dans un fichier privé ; un bouton du dashboard permet aussi de les masquer sur l'appareil courant. La désactivation arrête les calculs chaque seconde et les lectures SQL de suivi des compteurs. Les requêtes de la cloche et de sécurité continuent.

Le suivi regroupe locations actives et références affichées dans **une seule requête SQL**, limitée à 500 lignes. Son résultat est partagé pendant 30 secondes par ensemble de références (100 références maximum, 32 fichiers de cache maximum). Un verrou non bloquant empêche deux rafraîchissements SQL simultanés du cache. Lors d'une contention, un résultat de moins de 120 secondes peut être réutilisé ; sinon le suivi indique son indisponibilité temporaire. Cela ne cache jamais la vérification physique au paiement. La lecture du badge et des notifications utilise une seule requête avec fonction fenêtre (MariaDB). Les droits et l'utilisateur sont mémorisés uniquement pendant la requête PHP, sans conserver les permissions entre les requêtes.

Les incidents sont signalés dans la cloche des administrateurs disposant de `system.manage`. Ils couvrent exceptions du checkout, paiement déclaré échoué après vérification, paiement tardif à rapprocher, échec/incertitude de sortie, exceptions applicatives et erreurs PHP fatales lorsque la fermeture PHP peut encore s'exécuter. Un abandon normal avant paiement n'est pas un plantage et reste dans le suivi des locations.

Chaque rapport fournit diagnostic, étape, exception, message masqué, référence de location si disponible et durées du checkout. Aucun corps de requête, secret, numéro de bénéficiaire ou stack trace complète n'est enregistré dans le rapport système. Les rapports sont conservés dans `storage/system` (200 récents maximum) et doublés dans le journal PHP avec préfixe `TBP_INCIDENT`. Ils ne nécessitent aucune requête SQL ni appel externe, et toute erreur du collecteur est absorbée. L'accusé « Pris en compte » est global et ne modifie aucun état financier.

Le dossier privé est configurable par `SYSTEM_STORAGE_PATH` et doit être inscriptible par PHP, hors racine publique. Par défaut : dossier `storage/system` du dépôt. À créer avec mode 700 sous le compte d'hébergement ; fichiers créés avec mode 600. Le stockage n'est pas versionné. Si disque ou permissions échouent, consulter le journal PHP ; l'interface n'invente pas un rapport qui n'a pas pu être stocké. Un arrêt brutal du processus, un serveur hors ligne ou certaines saturations mémoire nécessitent une supervision externe ; le collecteur PHP ne les garantit pas.

Validation complémentaire : `php tests/system_monitoring.php` (désactivation sans requête de suivi, cache partagé, contention, journal sans base, masquage, accusé et stockage défaillant). Pas de migration SQL supplémentaire pour cette mise à jour ; la migration de notifications précédente reste un prérequis. Aucun envoi email/SMS n'est configuré à ce stade.
