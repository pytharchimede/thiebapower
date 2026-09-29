# Mise en service HeyCharge et Paiement Pro

Contrats : [Station Communication Server API Reference](https://alidocs.dingtalk.com/i/p/ZR2PmKjJ5wEXvpO7nb9XJ43jjwEwOGyA) ; [API PHP Paiement Pro](https://paiementpro.net/api-php) ; [sandbox Paiement Pro](https://sandbox.paiementpro.net/).

## 1. Code et base

Partir de `develop`. Sauvegarder la base et `.env`, tirer le code, puis appliquer `database/migrations/20260929_heycharge_terminals.sql` une seule fois après les migrations V1. La caution reste activée par défaut. Copier `public/style.css`, `public/payout.css`, `public/app.js` dans la racine publique conformément à `docs/v1-deployment.md`. Vérifier `php -l` et `php tests/run.php && php tests/v1_smoke.php`.

## 2. Secrets privés dans `.env`

```dotenv
APP_URL=https://thiebapower.com
HEYCHARGE_API_BASE=https://openapi.heycharge.global
HEYCHARGE_API_KEY=<clé réelle fournie par HeyCharge>
PAIEMENTPRO_MERCHANT_ID=<identifiant marchand>
PAYMENT_CALLBACK_SECRET=<64 caractères hexadécimaux aléatoires>
PUBLIC_RENTALS_ENABLED=0
AUTOMATIC_REFUNDS_ENABLED=0
```

Générer `PAYMENT_CALLBACK_SECRET` avec `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`. Le secret ne doit jamais être dans Git ni dans une ressource publique. Pour le sandbox, configurer `PAIEMENTPRO_SANDBOX_MERCHANT_ID` et sélectionner le mode sandbox dans `/admin`.

L'initialisation des locations utilise l'API JSON officielle de Paiement Pro. Chaque session communique à Paiement Pro une URL de notification portant un jeton HMAC propre à la référence de location ; le jeton n'est pas envoyé au navigateur. Le callback contrôle ce jeton, le marchand, la référence, le montant, la devise XOF (`952`) et `responsecode=0` avant d'émettre la commande matérielle. Le PDF Paiement Pro ne donne pas l'algorithme de son `hashcode` ; le jeton URL est ici un secret partagé par transaction, à valider avec le fournisseur lors du pilote. Protéger les journaux HTTP qui peuvent contenir la query string du callback.

## 3. Terminaux et événements

Communiquer à HeyCharge le préfixe exact `https://thiebapower.com/api/heycharge/callback`. Le fournisseur appelle `/register`, `/return` et `/status` sous ce préfixe. Une station inconnue signalée par `register` apparaît désactivée dans l'administration.

Dans `/admin` → Terminaux, enregistrer ou constater chaque IMEI, cliquer **Synchroniser** pour importer `battery_id`, `slot_id` et état, vérifier le parc, puis **Activer**. Dans Intégrations, choisir HeyCharge **Normal**. Une station désactivée ou une batterie non disponible ne peut pas être louée.

Créer la tâche cron cPanel suivante toutes les minutes, en adaptant uniquement le chemin du binaire PHP si nécessaire :

```cron
* * * * * /usr/local/bin/php /home/ifmapci/repositories/thiebapower/bin/heycharge_worker.php
```

Le worker rapproche les sorties et retours par `GET /v1/station/:imei`, et rafraîchit l'inventaire des stations actives. Les callbacks matériels sans signature documentée servent de signaux et de journal ; ils ne déclenchent pas seuls une restitution. Une commande de sortie à issue inconnue n'est jamais répétée automatiquement. Le retour sur une autre station exige son événement `return` puis une lecture API positive. L'heure du retour retenue est celle de la vérification par l'API, ce qui peut décaler le calcul de la caution de quelques minutes.

## 4. Caution et ouverture

Dans `/admin` → Tarification, décocher **Activer la caution** pour les nouvelles locations sans caution. Le récapitulatif et le paiement ne comprennent alors que le tarif de location ; le canal de restitution disparaît et aucun reversement n'est envoyé au retour. Les locations déjà créées conservent leurs montants.

Tester d'abord en sandbox puis avec un terminal et un paiement pilote réels : succès, refus, callback dupliqué, éjection, retour même station, retour sur une autre station et panne réseau. Une fois validé, régler Paiement Pro sur **Production**, vérifier l'IMEI actif et `PAYMENT_CALLBACK_SECRET`, puis mettre `PUBLIC_RENTALS_ENABLED=1`. Si les cautions sont utilisées, garder `AUTOMATIC_REFUNDS_ENABLED=0` tant que les reversements n'ont pas été validés séparément ; l'activer seulement après ce pilote. Le worker de reversements existant (`bin/refund_worker.php`) doit aussi être planifié pour les cautions positives.

La commande `forceUnlock` et le redémarrage sont disponibles dans le client API mais ne sont pas exposés aux clients, afin de réserver les opérations exceptionnelles à une procédure de maintenance.
