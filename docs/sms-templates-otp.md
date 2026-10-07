# Modèles SMS, événements de location et OTP

## Installation

Appliquer `database/migrations/20261004_sms_templates_otp.sql` puis copier `public/orange-sms.js` dans la racine publique. Aucune table métier existante n’est modifiée. Les modèles sont désactivés par défaut et apparaissent dans `/admin/sms/templates`, accessible uniquement au compte technique doté de `sms.manage`.

Planifier `php /home/ifmapci/repositories/thiebapower/bin/sms_worker.php` chaque minute. Le worker ne fonctionne que si le réglage administratif SMS est activé, en production, et `ORANGE_SMS_ENABLED` autorise l’envoi. Un modèle désactivé empêche à la fois les tests d’envoi avec ce modèle et ses messages automatiques. L’aperçu demeure disponible sans aucun envoi. Le message libre de test suit les mêmes blocages globaux.

## Modèles livrés

| Clé | Déclenchement |
| --- | --- |
| phone_otp | Prêt pour une future vérification du téléphone ; aucun endpoint public créé |
| payment_pending | Nouvelle demande de location, seulement tant qu’elle attend le paiement |
| payment_confirmed | Paiement vérifié, après validation de l’état en base |
| payment_failed | Échec vérifié, seulement si cet état reste actuel |
| release_failed | Incident lors de la commande de sortie, si la sortie reste à vérifier |
| rental_started | Retrait physique confirmé ; durée et échéance de la location |
| reminder_5min | Location active, entre 300 et 241 secondes avant `due_at` |
| rental_overdue | Location encore active après `due_at`, une fois pour cette échéance |
| rental_returned | Retour physique confirmé, avec retenue réellement calculée |
| refund_pending | Montant à restituer positif, tant que la restitution reste en attente |
| refund_confirmed | Restitution financière effectivement confirmée |

Les appels métier mettent seulement en file : aucun appel réseau Orange ne retarde un paiement ou une commande HeyCharge. Une erreur de la file SMS ne fait pas échouer le processus métier. Les événements survenus avant activation ne sont pas rejoués. Les locations en environnement de paiement sandbox sont exclues des envois automatiques réels. Les rappels et dépassements concernent les locations encore actives au moment du traitement.

Le rappel n’est pas une nouvelle règle tarifaire : il utilise l’échéance `due_at` déjà calculée par le système. Le cron doit être exécuté chaque minute pour viser les cinq minutes avant échéance. Une panne ou un retard du cron peut faire manquer cette fenêtre ; aucun faux rappel « 5 min » n’est envoyé après cette fenêtre ou après un retour.

## Rendu et suivi

Un futur service métier peut appeler `SmsTemplates::send($key, $phone, $variables)` ; cet appel respecte les blocages du modèle et des SMS.

Syntaxe `{{customer_name}}`, `{{reference}}`, etc. Chaque modèle possède sa liste de variables autorisées et ses exemples. La page SMS permet de choisir le modèle, renseigner les variables en JSON, afficher un aperçu sans envoi, puis tester le message rendu. Le rendu serveur fait foi : le texte libre du formulaire ne remplace pas le contenu du modèle sélectionné.

Limite de 480 caractères après substitution. Les segments estimés distinguent GSM-7, extensions et Unicode UTF-16 ; Orange reste la référence de facturation. Les messages de base évitent certains accents pour limiter les segments ; les noms clients conservent leurs caractères.

File persistante `sms_outbox`, clé d’événement unique, un worker sous verrou MariaDB, au plus 20 envois par passage, débit limité, pas de réessai automatique après un résultat ambigu. Une interruption après réservation laisse un résultat à vérifier, jamais une nouvelle tentative aveugle. Les messages dépassant une heure d’attente sont ignorés. Les rappels sont contrôlés à nouveau juste avant envoi. Les SMS et numéros complets ne sont pas copiés dans les journaux ; ceux-ci gardent statut, référence et numéro masqué.

`ORANGE_SMS_ENABLED=0` interrompt toute émission. Les modèles activés conservent leur configuration mais ne peuvent pas envoyer. Une réactivation ne garantit pas le rattrapage des événements manqués. Les tâches récentes déjà en file sont contrôlées selon leur pertinence.

## Classe OTP interne

`PhoneOtpService::issue($phone, 'verify_phone', 5)` crée un code de six chiffres avec `random_int`, un challenge aléatoire, une durée de cinq minutes, et invalide les challenges précédents du même numéro et du même objet. L’API interne retourne le code uniquement pour qu’un service serveur de confiance puisse l’envoyer. Ne jamais exposer ce code à un navigateur ou un journal.

`verify($challengeId, $phone, $purpose, $code)` vérifie atomiquement l’échéance, le téléphone, l’objet, le hash et l’usage unique. Cinq tentatives maximum, une nouvelle émission par minute pour ce numéro et cet objet. Aucun code n’est stocké en clair : HMAC-SHA-256 lié au challenge, téléphone et objet.

Configurer `PHONE_OTP_KEY` avec 32 octets aléatoires en base64. À défaut, la clé `ORANGE_SMS_ENCRYPTION_KEY` existante est utilisée. La rotation de cette clé invalide les anciens codes. Aucun téléphone client n’est automatiquement déclaré vérifié dans cette livraison : le branchement d’un futur parcours public sera séparé.

## Validation

`php tests/sms_templates.php`, `php tests/orange_sms.php`, `php tests/orange_sms_view.php`, `php tests/run.php`, `node tests/orange_sms_ui.js` si Node est disponible. La migration, l’unicité des événements et le verrouillage de l’OTP à cinq essais ont aussi été vérifiés sur une base MariaDB isolée, sans envoi réel.
