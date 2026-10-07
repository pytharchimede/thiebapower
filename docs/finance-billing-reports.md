# Retraits, facturation et documents

## Installation

Cette branche part de `develop`. Appliquer une seule fois `database/migrations/20261003_finance_billing.sql` avant de mettre en service le nouveau code. La migration conserve la règle des locations existantes et leurs règlements ; les nouvelles locations portent `prorata_grace5`. Sauvegarder la base avant migration. Le changement ne déploie ni n’effectue de transfert réel par lui-même.

```bash
mysql -h localhost -u UTILISATEUR -p BASE < database/migrations/20261003_finance_billing.sql
```

Déployer le code PHP et les fichiers publics modifiés : `admin-design.js` et `app.js`. Recharger le navigateur pour actualiser les scripts. La page `/admin/users` permet au propriétaire d’attribuer les nouveaux droits. `finance.withdraw` et `reports.export` sont désactivés par défaut pour les autres rôles. Les permissions de lecture `pricing.view`, `stations.view`, `batteries.view`, `labels.view`, `rentals.view` sont reprises des anciens droits de gestion. Les contrôles de mutation et CSRF restent côté serveur.

## Facturation

Le début correspond à la sortie physique confirmée. Cinq minutes de dépassement sont gratuites. Le calcul s’effectue à la seconde après cette franchise, puis est arrondi une seule fois au FCFA supérieur :

`montant calculé = tarif payé × max(0, secondes de dépassement − 300) / (durée incluse × 60)`.

La retenue effective est le minimum de ce montant et de la caution. Sans caution, la retenue est nulle ; aucun paiement supplémentaire n’est créé. Exemple : 100 FCFA pour 15 minutes ; retour après 30 minutes ; 15 minutes de dépassement moins 5 gratuites = 10 minutes facturables ; montant 66,67, arrondi à 67 FCFA. Avec une caution de 200 FCFA, 133 FCFA sont restituables. Les anciennes locations gardent le pourcentage par heure entamée enregistré à leur création.

## Retraits

`/admin/finance` exige bénéficiaire, numéro ivoirien, canal, montant et motif. La confirmation rappelle les coordonnées. Un jeton unique évite les doubles envois, y compris après un double clic. Un verrou sérialise la création des retraits ; un retrait dont le résultat est incertain interdit une nouvelle opération. Le code appelle Paiement Pro uniquement en production et journalise la demande avant l’appel. Une notification non authentifiée ne confirme aucun versement. Si Paiement Pro exige une autorisation, le journal affiche le lien fournisseur validé à partir de la réponse SOAP. Le bouton de vérification utilise le statut fournisseur et contrôle session, référence, devise, montant, canal et bénéficiaire.

Un statut sans session reste bloqué : transmettre la référence à Paiement Pro pour rapprochement. Aucun bouton ne prétend annuler un virement chez le fournisseur. Les retraits réussis diminuent le flux net Paiement Pro à leur date de confirmation et ne créent pas de sortie de caisse physique. Ce flux est un indicateur de période, pas un solde fournisseur disponible : le service ne déduit ni frais ni réserves non connus par l’API.

## Documents

PDF A4 paysage : wordmark Thiebapower, QR vers l’administration, synthèse de la sélection, colonnes avec retour à la ligne, titres de colonnes répétés et pagination. Les exports de listes portent sur les lignes chargées et filtrées dans l’interface (100 locations maximum). Les colonnes purement opérationnelles ne doivent pas être considérées comme des pièces comptables. Les montants des synthèses reflètent les lignes affichées, même si leur paiement n’est pas confirmé.

Excel : en-tête, synthèse, styles de la marque, cellules multilignes, filtres, titres répétés à l’impression et feuille QR. Les textes commençant par une formule restent des chaînes.

Le reçu apparaît pour chaque location retournée dans le listing et la fiche. Il reprend le contrat, la chronologie, le calcul, les montants et l’état réel du remboursement ; un remboursement en attente reste indiqué comme tel. Le client retrouve le reçu depuis sa page de retour de paiement, dans le navigateur ayant initié sa dernière location. Le jeton privé est conservé localement. Un autre navigateur ou une location lancée ultérieurement ne retrouve pas automatiquement ce jeton ; le personnel peut fournir le reçu. Le QR du reçu renvoie à la copie protégée par connexion, sans encoder le jeton client.

## Validation

Suites PHP : `tests/run.php`, `v1_smoke.php`, `billing_reports.php`, `finance_withdrawals.php`, `admin_presentation.php`, `checkout_monitoring.php`, `payout_diagnostics.php`, `payout_environment.php`, `system_monitoring.php`. Vérification syntaxique PHP et JavaScript. PDF d’exemple rendus et inspectés ; classeur vérifié avec openpyxl. Les tests utilisent un SOAP simulé : aucun vrai paiement n’est envoyé. La migration a aussi été exécutée sur MariaDB 10.11 en mode bootstrap, avec assertions de conservation des contrats et des droits. Le flux bancaire doit être vérifié sur l’environnement de déploiement ; aucune connexion à la base de production n’a été faite.
