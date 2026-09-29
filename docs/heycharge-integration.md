# Mise en service HeyCharge et Paiement Pro

Contrats : [Station Communication Server API Reference](https://alidocs.dingtalk.com/i/p/ZR2PmKjJ5wEXvpO7nb9XJ43jjwEwOGyA) ; [API PHP Paiement Pro](https://paiementpro.net/api-php) ; [sandbox Paiement Pro](https://sandbox.paiementpro.net/).

## 1. Code et base

Partir de `develop`. Sauvegarder la base et `.env`, tirer le code, puis appliquer `database/migrations/20260929_heycharge_terminals.sql`, puis `database/migrations/20260929_rental_operations.sql`, puis `database/migrations/20260929_manual_battery_release.sql`, une seule fois après les migrations V1. La caution reste activée par défaut. Copier `public/style.css`, `public/payout.css`, `public/app.js` dans la racine publique conformément à `docs/v1-deployment.md`. Vérifier `php -l`, `php tests/run.php`, `php tests/v1_smoke.php`, puis `php bin/doctor.php` pour l’état des dépendances, migrations, clés et cron.

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

Communiquer à HeyCharge le préfixe exact `https://thiebapower.com/api/heycharge/callback`. Le fournisseur appelle `/register`, `/return` et `/status` sous ce préfixe. Une station inconnue signalée par `register` apparaît désactivée dans l’administration. Les écrans `/admin/stations`, `/admin/stations/detail?imei=...` et `/admin/rentals` présentent le parc, les événements et les locations à traiter.

La notification des encaissements Paiement Pro utilise une URL distincte, `/api/paiementpro/rental-callback?token=...`, générée lors de chaque paiement. L'URL racine HeyCharge répond à un contrôle GET et ne reçoit pas de paiement.

Dans `/admin` → Terminaux, enregistrer ou constater chaque IMEI, cliquer **Synchroniser** pour importer `battery_id`, `slot_id` et état, vérifier le parc, puis **Activer**. Dans Intégrations, choisir HeyCharge **Normal**. Les batteries présentes dans un emplacement valide et chargées à 70 % au moins sont proposées à la location, même si un indicateur câble ou batterie est signalé. Sous 70 %, elles apparaissent `charging`. Les batteries réservées, louées ou sous commande d'éjection administrateur ne sont pas proposées. Avant le paiement et avant la sortie, le serveur revérifie la présence et la charge auprès de HeyCharge. Appliquer `database/migrations/20260929_charging_batteries.sql` avant ce code.

Dans le détail du terminal, l'administrateur peut éjecter une batterie disponible ou en maintenance en recopiant son numéro de série. La commande la retire immédiatement des batteries louables ; la lecture API suivante confirme sa disparition du terminal. En cas de réponse réseau inconnue, ne pas répéter la commande : vérifier physiquement puis synchroniser. Les batteries réservées ou louées ne peuvent jamais être éjectées par cette commande.

Si une batterie est éjectée puis réinsérée avant que le worker ne constate son absence, l'administrateur peut utiliser **Vérifier la réinsertion** sur la ligne de la batterie. L'application consulte le terminal, contrôle le numéro et l'emplacement, clôt la commande en `reinserted` et restaure `available` à partir de 70 % de charge, sinon `charging`. Appliquer `database/migrations/20260929_manual_battery_reinsertion.sql` après la migration d'éjection.

Créer la tâche cron cPanel suivante toutes les minutes, en adaptant uniquement le chemin du binaire PHP si nécessaire :

```cron
* * * * * /usr/local/bin/php /home/ifmapci/repositories/thiebapower/bin/heycharge_worker.php
```

Le passage du worker est visible dans `/admin` (dernier passage UTC). Le worker traite les commandes en cours et les retours par petites séries, en priorisant les événements de retour récents. Il rapproche les sorties et retours par `GET /v1/station/:imei`, et rafraîchit l'inventaire des stations actives. Les callbacks matériels sans signature documentée servent de signaux et de journal ; ils ne déclenchent pas seuls une restitution. Une commande de sortie à issue inconnue n’est jamais répétée automatiquement. Les notifications Paiement Pro explicitement refusées marquent le paiement échoué, sans éjection. La batterie reste réservée jusqu’à la fin des deux minutes, puis le worker la libère au passage suivant. Les réservations sans confirmation expirent également après deux minutes et libèrent la batterie. Appliquer `database/migrations/20260929_payment_reservation_timeout.sql` avant de lancer le worker mis à jour. Si un succès arrive après expiration, la location passe en « payment_review » et ne provoque pas d’éjection : rapprocher le paiement et rembourser ou traiter manuellement. Le retour sur une autre station exige son événement `return` puis une lecture API positive. L'heure du retour retenue est celle de la vérification par l'API, ce qui peut décaler le calcul de la caution de quelques minutes.

## 4. Caution et ouverture

Dans `/admin` → Tarification, décocher **Activer la caution** pour les nouvelles locations sans caution. Le récapitulatif et le paiement ne comprennent alors que le tarif de location ; le canal de restitution disparaît et aucun reversement n'est envoyé au retour. Les locations déjà créées conservent leurs montants.

Tester d'abord en sandbox puis avec un terminal et un paiement pilote réels : succès, refus, callback dupliqué, éjection, retour même station, retour sur une autre station et panne réseau. Une fois validé, régler Paiement Pro sur **Production**, vérifier l'IMEI actif et `PAYMENT_CALLBACK_SECRET`, puis mettre `PUBLIC_RENTALS_ENABLED=1`. Si les cautions sont utilisées, garder `AUTOMATIC_REFUNDS_ENABLED=0` tant que les reversements n'ont pas été validés séparément ; l'activer seulement après ce pilote. Le worker de reversements existant (`bin/refund_worker.php`) doit aussi être planifié pour les cautions positives.

La commande `forceUnlock` et le redémarrage sont disponibles dans le client API mais ne sont pas exposés aux clients, afin de réserver les opérations exceptionnelles à une procédure de maintenance.

## 5. Découverte du parc et étiquettes QR

Le worker interroge `GET /v1/station` avec la clé du compte toutes les dix minutes ; l'administrateur peut relancer l'import dans `/admin/stations`. La réponse doit être une liste de stations ou un objet `stations`, `data`, `items` ou `list` contenant cette liste. Les nouveaux IMEI sont enregistrés **désactivés** ; l'import ne supprime ni n'active jamais une station. Si la forme de réponse ou l'endpoint du fournisseur diffère, le journal PHP signale l'échec sans modifier le parc : comparer la réponse réelle avec le contrat HeyCharge avant d'ajuster le parseur.

Dans `/admin/stations/labels`, imprimer les étiquettes 100 × 150 mm ou l'étiquette d'une station depuis sa ligne. Chaque QR est généré localement et pointe vers `APP_URL/rent?station=<IMEI>` ; aucune clé API n'apparaît dans le QR. Le kiosque `/` affiche un seul QR, associé à la station sélectionnée. Sur téléphone, le scan ouvre directement la liste des batteries disponibles de cette station avec numéro de slot, numéro de batterie et charge. La page rafraîchit l'inventaire auprès de HeyCharge si la dernière lecture dépasse 20 secondes ; si l'API est indisponible, aucune batterie n'est proposée. Le paiement revérifie ensuite la présence et la charge auprès du terminal. Copier les nouveaux `public/style.css` et `public/app.js` dans la racine publique lors du déploiement.
