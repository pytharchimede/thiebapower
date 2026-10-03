# Statistiques en direct

Page /admin/statistics, disponible avec le droit dashboard.view, depuis le menu et le tableau de bord d’accueil. Aucune migration supplémentaire.

Après le pull, copier public/statistics.js et public/statistics.css dans la racine publique, puis actualiser le navigateur.

Le mode manuel effectue une lecture à l’ouverture. Le mode temps réel, activable par utilisateur sur son navigateur, actualise toutes les 15 secondes par défaut (10, 15, 30 ou 60 au choix). Les requêtes ne se chevauchent pas. L’onglet masqué suspend les mises à jour ; une erreur conserve la dernière lecture et ralentit les tentatives. Les données communes sont mises en cache pendant cinq secondes et la session PHP est libérée avant l’agrégation.

Les périodes couvrent aujourd’hui, 7, 30 et 90 jours calendaires, horaires UTC/Abidjan. Les champs TIMESTAMP sont lus avec une session SQL en UTC. Les événements créés, démarrés et retournés sont comptés à leur propre date. Les compteurs actifs et les files d’attente portent sur toutes les périodes ; la distribution des demandes correspond à celles créées dans la période, selon leur état actuel. Les locations incluent tous les modes. Les montants des restitutions ne concernent que la production et les versements positifs confirmés. Un retour physique ne vaut pas confirmation de remboursement.

Le droit rentals.view contrôle les locations ; finance.view contrôle les restitutions et montants ; stations.view et batteries.view contrôlent séparément leurs rubriques ; system.manage contrôle les incidents. Chaque requête vérifie les permissions ; la clé de cache inclut ces permissions.

Les listes sont bornées : 100 locations actives, 100 stations et 12 événements récents par rubrique. Les totaux ne sont pas limités à ces listes. Les incidents proviennent des rapports conservés ; une purge peut donc modifier les statistiques historiques. Le dashboard lit les événements connus en base et n’effectue ni appel HeyCharge ni transfert financier. Les délais de remontée des événements peuvent précéder le délai d’actualisation de l’écran.

Validation : syntaxe PHP/JavaScript, tests PHP de permissions/cache/périodes, régression des vues, tests du rendu DOM des graphiques, activation/pause, onglet masqué, absence de concurrence et conservation des données après erreur. Le rendu visuel dans un navigateur complet et les agrégats sur la base de production restent à vérifier après déploiement.
