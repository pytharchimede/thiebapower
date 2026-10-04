# SMS Orange CI — socle d’intégration

Administration : `/admin/sms`. Permissions séparées `sms.view`, `sms.manage`, `sms.test`, accordées au propriétaire ; les autres rôles sont configurables dans Comptes et droits. Pour utiliser les formulaires, accorder aussi `sms.view`.

## Installation

1. Appliquer `database/migrations/20261004_orange_sms.sql` à la base MariaDB. Migration rejouable, intégration désactivée et simulation par défaut.
2. Copier `public/orange-sms.css` dans la racine publique comme les autres assets. Le contrôleur, les services, la vue et les routes restent dans le dépôt applicatif.
3. PHP 8.2+, PDO MySQL, cURL et OpenSSL requis. Générer une clé avec `php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'` puis ajouter `ORANGE_SMS_ENCRYPTION_KEY=...` au fichier `.env` protégé. Ne pas publier cette clé ni la mettre dans Git ; la conserver avec les sauvegardes chiffrées. Une perte ou rotation de clé impose de ressaisir le secret.
4. Dans Orange Developer, créer l’application, souscrire à SMS Côte d’Ivoire, obtenir Client ID et Client Secret, acheter un lot SMS. Choisir l’expéditeur par défaut (sans senderName) ou un nom personnalisé. Pour le mode personnalisé, faire approuver et autoriser le nom, puis confirmer l’approbation dans l’administration. L’adresse technique du pays est `tel:+2250000`, distincte du nom commercial.
5. Saisir les identifiants dans l’administration (secret chiffré AES-256-GCM en base) ou dans `.env` via `ORANGE_SMS_CLIENT_ID` et `ORANGE_SMS_CLIENT_SECRET`. La sélection automatique privilégie une source .env complète ; sinon elle utilise le couple administratif. Un champ secret vide conserve le secret administratif. Aucun mélange des deux sources. Les identifiants `.env` restent côté serveur.
6. Enregistrer, tester l’authentification, consulter solde / consommation / achats. Ces contrôles sont réels, même en simulation. Activer en simulation pour vérifier les entrées sans crédit consommé. Passer ensuite en production et confirmer un SMS réel depuis le formulaire.

## Fonctionnement et limites

- Hôte HTTPS fixé à `api.orange.com`, vérification TLS, délais réseau bornés, aucune redirection.
- OAuth client credentials vers `/oauth/v3/token`. Jeton uniquement en mémoire pour la durée de l’opération ; renouvellement avant expiration. Aucun jeton exposé au navigateur ou enregistré dans l’historique.
- Envoi POST `/smsmessaging/v1/outbound/tel%3A%2B2250000/requests`, normalisation des numéros ivoiriens locaux / internationaux ; refus des numéros étrangers. Tests limités à 480 caractères Unicode ; plusieurs segments peuvent être facturés selon l’encodage.
- GET `/sms/admin/v1/contracts`, `/statistics`, `/purchaseorders` pour les informations Orange. Réponses affichées après contrôle explicite ; pas de polling.
- Protection CSRF et permissions sur chaque opération ; nonce à usage unique contre double soumission, intervalle global de 30 secondes entre contrôles réservé sous verrou SQL. Pas de nouvelle tentative automatique des envois : en cas d’erreur réseau le résultat est inconnu et doit être vérifié chez Orange.
- Deux modes expéditeur : `default` omet toujours `senderName`, même si un ancien nom est conservé ; `custom` ajoute le nom et exige sa confirmation d’approbation. Le mode par défaut dépend de l’autorisation du compte Orange et peut être refusé par le fournisseur. L’approbation est confirmée par l’administrateur sur la base de la validation Orange, pas vérifiée automatiquement par l’API.
- HTTP 201 = accepté, jamais « livré ». Les références Orange et statuts sont conservés ; destinataires masqués, messages et secrets absents de l’historique et de l’audit. Un processus interrompu peut laisser une ligne « En cours / à vérifier ».
- Les modèles de processus, la file et les rappels sont maintenant disponibles : voir `docs/sms-templates-otp.md`. L’OTP est préparé pour un futur parcours de vérification, sans endpoint public.
- Les accusés de livraison sont une évolution séparée : Orange exige une URL HTTPS enregistrée auprès de son équipe. Aucun endpoint non authentifié n’est ajouté ici et aucun statut de livraison n’est inventé.

Sources : collection Postman fournie ; https://developer.orange.com/apis/sms/getting-started ; https://developer.orange.com/apis/sms-ci/faq

## Vérification

`php tests/orange_sms.php` (transport simulé, aucun SMS réel) et `php tests/run.php`. En production, vérifier accès propriétaire, absence d’accès opérateur par défaut, simulation, refus sans confirmation et contrôle du solde avant un SMS réel.

## Arrêt serveur et accès techniques

`ORANGE_SMS_ENABLED=0` bloque tout appel au service d’envoi avant le réseau, y compris les simulations, sans empêcher l’authentification et la consultation du compte. `1` lève ce blocage ; l’activation administrative reste nécessaire. Variable absente : comportement existant conservé.

`TECHNICAL_ADMIN_USERNAME` désigne le login exact autorisé pour `sms.manage`, `sms.test`, `integrations.manage`, `payout.send`. À défaut, `ADMIN_USERNAME` puis `admin` est utilisé. Cette restriction est vérifiée avant le rôle propriétaire et ne peut pas être contournée par les permissions de rôle. Le compte désigné doit aussi disposer des permissions ordinaires (le propriétaire les possède). Les autres fonctions métier gardent leurs droits habituels.

Le résultat d’envoi affiche la référence Orange et, en cas de refus, un identifiant d’erreur fournisseur filtré. Aucun statut de livraison n’est déduit d’un HTTP 201.

## Authentification : une source unique

`ORANGE_SMS_CREDENTIALS_SOURCE=env` impose le fichier .env ; `admin` impose le couple chiffré de l’administration ; `auto` choisit un .env complet, sinon l’administration. La variable serveur prime sur le choix du formulaire. Dans la source env, `ORANGE_SMS_AUTHORIZATION_HEADER=Basic ...` prime sur le couple `ORANGE_SMS_CLIENT_ID` / `ORANGE_SMS_CLIENT_SECRET`. L’en-tête est validé, décodé et transmis selon le schéma Basic ; aucun jeton ou secret n’est affiché dans les résultats.

`printf '%s' "$orange_auth" | php bin/configure_orange_sms_auth.php` permet de configurer le header reçu via saisie masquée dans le shell. Le script écrit atomiquement le .env et impose la source env, sans modifier la clé de chiffrement, l’activation des SMS ou le compte technique. Sauvegarder le .env avant exécution.

`php bin/orange_sms_check.php --balance` teste le même client et les mêmes identifiants que le dashboard et consulte le solde, sans envoyer de SMS. Un en-tête Basic invalide provoque une erreur explicite ; aucun basculement automatique vers un autre compte n’est tenté en cas de 401.
