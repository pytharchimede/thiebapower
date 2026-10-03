# Administration mobile first

Le dashboard garde les indicateurs et les aperçus. Tarification et Batteries ont des pages dédiées, avec les mêmes formulaires et permissions. Les fiches Batteries et Locations sont consultées par routes GET protégées. Les terminaux conservent leur page de détail existante. Les autres cards proposent une fiche de lecture dans la page courante, avec retour au listing.

Les tableaux restent présents sans JavaScript. Le script conserve les nœuds des formulaires POST et leurs champs CSRF. Il ajoute une recherche locale, un filtre d’état et des exports des résultats chargés et visibles. Les plafonds des contrôleurs existants (10/100/150 selon le listing) restent inchangés et sont rappelés sous les filtres. Les exports ne relancent pas les API.

Excel est un fichier OOXML .xlsx : les cellules sont stockées comme du texte pour préserver les identifiants et empêcher l’exécution des formules contenues dans les données. PDF est un document téléchargeable avec plusieurs pages. Aucune dépendance distante pour ces exports. FontAwesome Free 6.7.2 est fourni avec sa police et sa licence.

Aucune migration, changement de calcul, requête API ni modification des workflows. Seules les redirections après enregistrement des tarifs et des batteries renvoient vers leur page dédiée.

## Vérification

```bash
find app views routes bin tests -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/run.php
php tests/v1_smoke.php
php tests/payout_diagnostics.php
php tests/admin_presentation.php
```

Tests visuels avec données fictives à 360, 390, 768, 1024 et 1440 px ; pas de débordement horizontal. Recherche et téléchargement Excel vérifiés en navigateur. Exports Excel/PDF relus sur un jeu de 70 enregistrements. Tests des formulaires, permissions et échappement des vues dédiées.

## Fichiers publics à copier pour l’hébergement actuel

Après la mise à jour du dépôt, copier dans /home/ifmapci/thiebapower.com :
- public/admin-design.css
- public/admin-design.js
- public/admin-icons.css
- public/fa-solid-900.woff2
- public/admin-icons-LICENSE.txt

Les liens des nouveaux fichiers CSS/JS portent une version pour le cache. Aucune nouvelle donnée de configuration n’est requise.
