# Stations, clients et pilotage — 4 octobre 2026

## Écrans

- `/admin/stations/profile?imei=...` : nom, type de lieu, adresse, horaires, position sur carte et contacts du gérant.
- `/stations/map` : stations activées, recherche locale, distance à vol d’oiseau, disponibilité et itinéraire.
- `/admin/rentals/watch` : sorties incertaines, réservations expirées, dépassements, remboursements en échec et assistance.
- `/admin/support` : demandes rattachées à une location, clôture et trace de résolution.
- `/my-rentals` : suivi et reçus des 30 derniers formulaires commencés depuis le même navigateur. Chaque location est autorisée par son jeton secret ; aucun historique n’est exposé à partir d’un simple numéro de téléphone. Effacement proposé sur les appareils partagés. Ce premier espace n’est pas un compte synchronisé entre appareils.
- `/admin/batteries/detail?id=...` : durée cumulée connue depuis les sorties enregistrées, utilisation en cours incluse.
- `/admin/stations/profitability` : tarifs des locations démarrées après paiement de production vérifié, retenues clôturées, investissement et coûts saisis. Les notifications répétées ne doublent pas les revenus. Les cautions et paiements sans sortie confirmée ne constituent pas des recettes acquises dans ce tableau.
- `/admin/promotions` : campagnes et offres pour clients réguliers ; parrainage depuis une location terminée dans Mes locations.

Les liens figurent dans le menu de gestion, les terminaux et le kiosque. Le nom local est conservé lors des synchronisations HeyCharge ; l’IMEI reste la clé technique inchangée.

## Carte

Leaflet 1.9.4 est fourni localement avec sa licence. Les fonds de carte utilisent OpenStreetMap. La recherche utilise Photon, avec préférence Abidjan et filtrage Côte d’Ivoire, temporisation de 800 ms, annulation des requêtes obsolètes et cache privé de 24 heures. Le marqueur doré est un aperçu ; choisir une suggestion, cliquer sur la carte ou déplacer le marqueur confirme la position à enregistrer. Une recherche ne déplace jamais silencieusement une station déjà enregistrée. En cas d’échec du géocodage, le clic sur la carte reste utilisable. Un lieu absent du référentiel peut être placé manuellement sur la carte.

`PHOTON_API_URL` permet de remplacer le serveur public par une instance HTTPS dédiée. Le serveur public ne fournit pas de garantie de disponibilité ; pour une exploitation à volume élevé, configurer un service dédié. L’autocomplétion n’utilise pas le serveur public Nominatim. Références : [Photon](https://github.com/komoot/photon/blob/master/docs/api-v1.md), [usage des tuiles OSM](https://operations.osmfoundation.org/policies/tiles/).

Les noms, adresses, horaires, positions, noms et téléphones des gérants sont publics. Les stations suspendues apparaissent en gris, sans bouton de location. L’email et les notes du gérant, les investissements et coûts restent administratifs. Une lecture de stock datant de plus de dix minutes, ou une station hors ligne, affiche « disponibilité à vérifier ».

## Droits et sécurité

Les permissions existantes sont réutilisées : `stations.view` pour consulter les fiches, `fleet.manage` pour modifier et rechercher un lieu, `rentals.view` pour les situations et l’assistance, `rentals.manage` pour rapprocher ou clôturer, `finance.view` + `stations.view` pour la rentabilité, `finance.manage` pour les coûts, `pricing.manage` pour les offres. Tous les formulaires administratifs exigent le jeton CSRF existant. Un coût possède un jeton unique pour éviter une double insertion lors d’un renvoi du formulaire.

Le support client accepte un POST JSON avec le jeton de la location, une seule demande ouverte par location et un délai après clôture. Les jetons du nouvel espace client sont transmis dans le corps JSON, jamais dans les URLs de ses API. Les liens PDF conservent le mécanisme existant de reçu protégé.

## Offres : activation distincte

Les offres sont désactivées par défaut : `PROMOTIONS_ENABLED=1` active les champs client et le parrainage. Sans ce réglage, le tarif de location existant s’applique et aucun code soumis ne modifie le prix. `REFERRAL_DISCOUNT_XOF=100` définit la remise d’un proche invité ; elle doit rester inférieure au tarif courant. Un code de parrainage dure 30 jours, avec dix utilisations au maximum, une par numéro. Le parrain ne reçoit pas de paiement automatique.

Une remise réduit seulement le tarif initial ; la caution est conservée. Le prorata du dépassement suit le tarif effectivement payé, selon le contrat existant. Les paiements gratuits sont refusés. Les offres sont figées dans la transaction de réservation, avec verrou de la campagne et unicité code/numéro. Une réservation abandonnée consomme aussi son code : elle n’est pas automatiquement recréditée, pour éviter les réutilisations après un paiement tardif. Le contrôle d’unicité repose sur le numéro déclaré ; ce mécanisme ne constitue pas une vérification d’identité par OTP.

Les offres fidélité exigent le jeton d’une précédente location de production terminée et le nombre de locations payées et terminées défini par l’administrateur. Les paiements anciens, sans jeton de formulaire, ne peuvent pas donner accès à l’espace client sur un nouvel appareil.

## Mise en production existante

Appliquer **avant le chargement du nouveau code** `database/migrations/20261004_station_experience.sql`. Elle ajoute cinq tables, sans modifier les statuts, montants ou contrats des locations existantes. Les migrations du 3 octobre doivent déjà être présentes. La migration est réexécutable (`CREATE TABLE IF NOT EXISTS`).

Copier les ressources suivantes vers la racine publique : `app.js`, `station-experience.css`, `station-profile.js`, `station-directory.js`, `my-rentals.js`, `promotions.js`, et le dossier `vendor/leaflet/`. Conserver les ressources existantes.

Après mise à jour : ouvrir les fiches des trois terminaux, leur donner un nom, choisir le lieu, enregistrer les coordonnées du gérant et les horaires. Renseigner investissements et coûts avant d’interpréter la rentabilité. Les coûts d’analyse ne créent pas un décaissement de caisse ; enregistrer séparément le mouvement physique si nécessaire.

La réservation de deux minutes, le rapprochement matériel, le worker et les remboursements restent ceux déjà en service. Aucun remboursement ni aucune commande d’éjection n’est lancé par ces nouveaux écrans en lecture.

## Validation

Contrôle de syntaxe de 124 fichiers PHP ; 19 scripts de tests PHP passés, dont 27 assertions nouvelles ; tests DOM existants de caution et portefeuille, plus recherche, marqueur et espace client. Essais sur MariaDB 10.11 isolée : migrations vierges et répétées, déduplication des recettes, refus de réutilisation du code, notifications sans modification des locations. Essais HTTP isolés : confidentialité publique, autorisation des jetons, assistance, validation de position, CSRF et droits de consultation/modification. Aucun paiement ni aucune commande HeyCharge réelle pendant ces tests.

Les tests DOM n’ont pas été complétés par un rendu Chromium : le téléchargement du navigateur est indisponible dans cet environnement. Vérifier la carte et la mise en page sur le navigateur du serveur après déploiement. Les dépendances externes de carte et de géocodage doivent être accessibles depuis le poste et le serveur de production.

`php tests/station_experience.php` et `node tests/station_experience_dom.js` (avec jsdom) sont autonomes. Pour la base, créer une **base vide** dont le nom commence par `thiebapower_test_`, puis lancer `TEST_EXPERIENCE_DATABASE=1 PROMOTIONS_ENABLED=1 php tests/integration/station_experience_database.php` avec `DB_DSN`, `DB_USER` et `DB_PASSWORD` dirigés vers cette base. Le test refuse une base existante contenant des tables et ne supprime aucune base.

Le test HTTP `python3 tests/integration/station_experience_http.py` vise exclusivement un serveur local (`TEST_BASE_URL`, port 8090 par défaut), préparé avec cette fixture. Il vérifie les comptes de test, les refus d’accès et les nouvelles routes.

## Refonte de la carte publique

Recherche sur toute la largeur, présentation adaptée aux mobiles, fiches détaillées dans la liste et les bulles de carte, liens d’appel au gérant et actualisation manuelle. Marqueur vert : locations activées et lecture récente ; orange : locations activées mais connexion à vérifier ; gris : locations suspendues. Le filtrage ignore la casse et les accents. Cette refonte ne nécessite pas de nouvelle migration. Copier `station-experience.css` et `station-directory.js` dans la racine publique.

## Refonte de Mes locations

Fiches responsive avec statut, tarif, étapes et reçu. Le formulaire d’incident propose quatre catégories, un compteur et un retour après envoi. Les actualisations conservent le brouillon, le focus et le résultat du signalement ; un échec permet de réessayer. Les routes et règles de location restent identiques. Aucune migration supplémentaire. Copier `my-rentals.js` et `my-rentals.css` dans la racine publique après mise à jour du code. Test DOM : `node tests/my_rentals_dom.js` avec jsdom.
