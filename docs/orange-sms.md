# SMS Orange CI — socle d’intégration

Administration : `/admin/sms`. Permissions séparées `sms.view`, `sms.manage`, `sms.test`, accordées au propriétaire ; les autres rôles sont configurables dans Comptes et droits. Pour utiliser les formulaires, accorder aussi `sms.view`.

## Installation

1. Appliquer `database/migrations/20261004_orange_sms.sql` à la base MariaDB. Migration rejouable, intégration désactivée et simulation par défaut.
2. Copier `public/orange-sms.css` dans la racine publique comme les autres assets. Le contrôleur, les services, la vue et les routes restent dans le dépôt applicatif.
3. PHP 8.2+, PDO MySQL, cURL et OpenSSL requis. Générer une clé avec `php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'` puis ajouter `ORANGE_SMS_ENCRYPTION_KEY=...` au fichier `.env` protégé. Ne pas publier cette clé ni la mettre dans Git ; la conserver avec les sauvegardes chiffrées. Une perte ou rotation de clé impose de ressaisir le secret.
4. Dans Orange Developer, créer l’application, souscrire à SMS Côte d’Ivoire, obtenir Client ID et Client Secret, acheter un lot SMS. Faire approuver le nom expéditeur si nécessaire, puis confirmer l’approbation dans l’administration. L’adresse technique du pays est `tel:+2250000`, distincte du nom commercial.
5. Saisir les identifiants dans l’administration (secret chiffré AES-256-GCM en base) ou dans `.env` via `ORANGE_SMS_CLIENT_ID` et `ORANGE_SMS_CLIENT_SECRET`. Les valeurs administratives priment ; un champ secret vide conserve le secret. Les identifiants `.env` restent côté serveur.
6. Enregistrer, tester l’authentification, consulter solde / consommation / achats. Ces contrôles sont réels, même en simulation. Activer en simulation pour vérifier les entrées sans crédit consommé. Passer ensuite en production et confirmer un SMS réel depuis le formulaire.

## Fonctionnement et limites

- Hôte HTTPS fixé à `api.orange.com`, vérification TLS, délais réseau bornés, aucune redirection.
- OAuth client credentials vers `/oauth/v3/token`. Jeton uniquement en mémoire pour la durée de l’opération ; renouvellement avant expiration. Aucun jeton exposé au navigateur ou enregistré dans l’historique.
- Envoi POST `/smsmessaging/v1/outbound/tel%3A%2B2250000/requests`, normalisation des numéros ivoiriens locaux / internationaux ; refus des numéros étrangers. Tests limités à 160 caractères Unicode ; plusieurs segments peuvent être facturés selon l’encodage.
- GET `/sms/admin/v1/contracts`, `/statistics`, `/purchaseorders` pour les informations Orange. Réponses affichées après contrôle explicite ; pas de polling.
- Protection CSRF et permissions sur chaque opération ; nonce à usage unique contre double soumission, intervalle global de 30 secondes entre contrôles réservé sous verrou SQL. Pas de nouvelle tentative automatique des envois : en cas d’erreur réseau le résultat est inconnu et doit être vérifié chez Orange.
- HTTP 201 = accepté, jamais « livré ». Les références Orange et statuts sont conservés ; destinataires masqués, messages et secrets absents de l’historique et de l’audit. Un processus interrompu peut laisser une ligne « En cours / à vérifier ».
- Le service est prêt pour un futur appel métier via `OrangeSmsClient::send`, mais aucun circuit automatique (location, remboursement, OTP, rappel) n’est connecté dans cette livraison. Le débit du fournisseur et les doublons métier devront être gérés dans une file avant raccordement aux événements.
- Les accusés de livraison sont une évolution séparée : Orange exige une URL HTTPS enregistrée auprès de son équipe. Aucun endpoint non authentifié n’est ajouté ici et aucun statut de livraison n’est inventé.

Sources : collection Postman fournie ; https://developer.orange.com/apis/sms/getting-started ; https://developer.orange.com/apis/sms-ci/faq

## Vérification

`php tests/orange_sms.php` (transport simulé, aucun SMS réel) et `php tests/run.php`. En production, vérifier accès propriétaire, absence d’accès opérateur par défaut, simulation, refus sans confirmation et contrôle du solde avant un SMS réel.
