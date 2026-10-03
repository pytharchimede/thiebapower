# Cautions, alimentation payout et essais XPaye

Interface : `/admin/deposit-wallet` (lecture `finance.view`, mouvements réels et rapprochement `finance.withdraw`, paramétrage également `pricing.manage`). Le montant client à restituer est aussi visible dans Statistiques en direct (avec droit finances) et sur sa page de suivi de paiement avec son jeton privé.

## Installation

Appliquer une seule fois `database/migrations/20261003_deposit_wallet.sql`, après les migrations existantes. Appliquer ensuite `database/migrations/20261003_deposit_wallet_fee_defaults.sql` : frais fixes nuls et taux payout de 2 % sur les canaux encore sans barème. Cette seconde migration est rejouable ; elle conserve les barèmes déjà enregistrés et ne change pas l’activation. Copier `public/deposit-wallet.js`, `public/deposit-wallet.css` et la nouvelle version de `public/statistics.js` dans le répertoire public réellement servi.

Ajouter dans le `.env` privé du dépôt, sans publier les valeurs :

```
XPAYE_LOGIN=identifiant_du_compte_reel
XPAYE_PASSWORD=mot_de_passe_du_compte_reel
AUTOMATIC_REFUNDS_ENABLED=1
```

Conserver la configuration de payout Paiement Pro production existante (merchant, secret, vérification des callbacks). PHP requiert PDO MySQL, cURL et SOAP. Les exemples de login/mot de passe fournis ne sont pas des identifiants activables.

Planifier toutes les minutes (adapter le chemin PHP) :

```
* * * * * /usr/bin/php /home/ifmapci/repositories/thiebapower/bin/refund_worker.php >> /home/ifmapci/repositories/thiebapower/storage/refund-worker.log 2>&1
```

Créer au préalable le dossier de journal hors de la racine publique, avec droits privés. Le worker peut être exécuté manuellement pour les essais. Plusieurs workers concurrents ne renvoient pas le même transfert : le passage à `unknown` est verrouillé puis validé avant l'appel HTTP.

## Mise en service

1. Vérifier la connexion dans l'interface : aucune transaction financière.
2. Renseigner le barème **contractuel du remboursement**, par canal : frais fixes et pourcentage directement saisi (2 = 2 % ; 2,5 = 2,5 %). Le défaut est 0 FCFA de frais fixes et 2 % par canal. Cocher « confirmé » même si le barème est nul. Les commissions d'encaissement ne constituent pas ce barème.
3. Envoyer un essai réel de 100 FCFA ; vérifier le montant exact crédité dans le compte XPaye et rapprocher sa référence. Un essai ne finance aucune location.
4. Activer les transferts dans cet écran et activer la caution dans Tarification. Activer les remboursements dans le `.env` et programmer le worker.
5. Tester une location production payée puis un retour dans une autre station. Rapprocher son crédit payout dans l'écran ; le worker rembourse le montant restant. Vérifier la livraison et le débit réel des frais chez le fournisseur.

**Limite connue :** le contrat communiqué décrit les deux requêtes XPaye, mais pas leur réponse de confirmation, un identifiant d'idempotence ni un endpoint de statut. L'automatisation de la demande fonctionne ; le rapprochement de crédit reste manuel. Ne pas assimiler HTTP 200 à un mouvement exécuté. Il faut obtenir la documentation de confirmation/statut pour automatiser cette étape. Les identifiants sont lus dans `.env` via `XPAYE_LOGIN` et `XPAYE_PASSWORD` : un POST JSON sur `/auth/token` récupère le token, puis le POST JSON `{ "montant": ... }` sur `/wallet/request` envoie ce token dans `Authorization: Bearer ...`, conformément aux requêtes fournies. Le token n’est pas un paramètre JSON supplémentaire et n’est pas enregistré. Le format token accepté est `token` ou `access_token`, au premier niveau ou sous `data` ; si le compte utilise un autre format, l'authentification s'arrête sans transfert.

## Montants et états

Encaissement vérifié = tarif de base + caution. Réserve transférée = caution + frais fixes + plafond(caution × points de base / 10 000). Le transfert interne est gratuit. Par défaut, les frais du payout sont de 2 % : le transfert interne est donc **1,02 × la caution**, arrondi au FCFA supérieur (1,002 correspondrait à 0,2 %). Exemple : caution 5 000 → transfert 5 100 ; après une retenue de 200, le client reçoit 4 800 et les frais de payout sont 96. Les frais restent à la charge de Thieba Power. Prévoir également les commissions d'encaissement : le solde net collecté doit suffire au transfert ; une caution n'est pas un budget pour payer ses frais.

Le calcul de retenue reste celui figé sur la location : prorata du tarif de base après cinq minutes gratuites, arrondi au FCFA supérieur, plafonné à la caution. Les anciennes locations conservent leur ancien calcul. Le retour physique fige la retenue, indépendamment de l'heure du remboursement. Le payout envoie **exactement** le montant restant au client ; on ne lui retire pas les frais et on n'ajoute pas les frais au montant qu'il reçoit. Leur débit effectif dépend du contrat fournisseur.

- `needs_fees` : caution vérifiée, barème manquant ; aucun transfert.
- `pending` : demande jamais envoyée, transmissible si activation effective.
- `unknown` : tentative engagée, résultat ambigu ; aucun renvoi automatique, y compris après timeout ou réponse HTTP d'erreur.
- `submitted` : réponse HTTP 2xx, crédit encore non prouvé.
- `confirmed` : crédit exact vérifié par un utilisateur autorisé, preuve et auteur conservés.

Une modification du barème recalcule les demandes encore jamais envoyées (`pending` ou `needs_fees`, sans date d’envoi). Elle conserve les montants des demandes déjà tentées ou confirmées.

Le remboursement attend un crédit confirmé lié à **sa** location et une réserve de frais suffisante au barème courant. Un transfert d'essai ne suffit jamais. Si le barème augmente au-delà de la réserve déjà transférée, le remboursement reste en attente : rapprocher le financement avec le fournisseur et faire évoluer le circuit avec un complément auditable plutôt que réémettre la même demande.

Les simulations et le sandbox n'alimentent pas le wallet production et ne déclenchent pas de remboursement automatique réel. Les cautions historiques ne sont pas balayées à l'installation : seules les notifications de paiement vérifiées postérieures à cette migration sont reprises par le worker en cas d'interruption du callback. Les frais réservés inutilisés et les retenues restent dans le solde payout : aucune API inverse n'a été fournie, donc aucun retour automatique vers le solde d'encaissement n'est inventé.

## Reprise et incidents

En cas de crash ou timeout, consulter le wallet fournisseur et rapprocher l'opération exacte. Ne pas relancer un transfert incertain. La confirmation manuelle certifie le crédit, et ne certifie pas la réception du remboursement par le client : celle-ci suit la vérification Paiement Pro existante. Une initiation SOAP acceptée ou une autorisation encore requise ne clôture pas la restitution.

Les identifiants/token ne sont pas enregistrés dans le ledger. Seuls le statut HTTP et quelques champs neutres de la réponse sont conservés. Les changements de paramètres et rapprochements sont audités. Chaque location et chaque clé d'essai n'ont qu'un seul transfert.

## Même canal pour le paiement et le remboursement

Appliquer `database/migrations/20261003_refund_payment_channel.sql` une seule fois après les migrations wallet. Le formulaire de location avec caution demande un **moyen de paiement unique** ; ce canal est envoyé à Paiement Pro dans le paramètre `channel`. Il n’existe plus de choix indépendant du canal de restitution.

Après notification de succès authentifiée, le canal fourni par Paiement Pro est prioritaire (`OMCIV2` est normalisé en `OMCIV` pour le payout). Si la notification ne fournit pas de canal, seule une nouvelle session explicitement initialisée avec ce canal sert de référence ; la documentation de notification ne garantit pas de champ `channel`. Les anciennes préférences de restitution ne sont jamais considérées comme une preuve du paiement. Un canal absent ou incompatible (ex. carte bancaire) bloque le remboursement automatique et produit un incident à rapprocher. Les anciennes locations dont le canal n’est pas connu nécessitent une confirmation du fournisseur ; une nouvelle notification vérifiée avec canal peut les rapprocher tant que le payout n’a pas été tenté.

Les callbacks répétés ne changent pas un canal déjà enregistré et aucune destination n’est modifiée après une tentative de payout. Le numéro bénéficiaire reste le numéro renseigné lors de la location ; la notification documentée ne garantit pas un numéro de compte payeur distinct.
