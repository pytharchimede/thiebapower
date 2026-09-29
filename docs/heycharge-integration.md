# HeyCharge — intégration des stations

Contrat fournisseur : https://alidocs.dingtalk.com/i/p/ZR2PmKjJ5wEXvpO7nb9XJ43jjwEwOGyA (Station Communication Server API Reference). API HTTPS Basic Auth, clé comme utilisateur et mot de passe vide. `GET /v1/station/:imei`, `POST /v1/station/:imei` avec `battery_id` et `slot_id` encodés en formulaire ; `/forceUnlock` et `/reboot` sont documentés mais volontairement non exposés dans l'interface de location.

## Déploiement

1. Partir de `develop`, appliquer `database/migrations/20260929_heycharge_terminals.sql` après les migrations V1. La migration ne change pas les locations historiques ; la caution est activée par défaut.
2. Dans `.env` privé : `HEYCHARGE_API_BASE=https://openapi.heycharge.global`, `HEYCHARGE_API_KEY=<clé fournisseur>`. Ne jamais publier la clé. Le préfixe à transmettre au fournisseur est `https://thiebapower.com/api/heycharge/callback` : le fournisseur ajoute `/register`, `/return`, `/status`.
3. Publier le code PHP et les ressources `public/style.css`, `public/payout.css`, `public/app.js` conformément au déploiement existant.
4. Dans `/admin`, enregistrer chaque IMEI, synchroniser l'inventaire, vérifier batteries et emplacements, puis activer la station. Basculer HeyCharge en normal après validation sur un terminal pilote. L'ouverture du kiosque exige aussi `PUBLIC_RENTALS_ENABLED=1` et Paiement Pro configuré.
5. Dans Tarification, décocher « Activer la caution » pour les nouvelles locations sans caution, sans canal de reversement. Les locations existantes conservent leur montant et leur traitement.

## Contrôles d'exploitation

La notification Paiement Pro n'a pas de signature ou de méthode de vérification exploitable dans la documentation fournie : `PaymentVerification` reste fermé. Un administrateur doit vérifier référence, session, montant et crédit effectif auprès de Paiement Pro puis saisir sa référence de preuve dans le tableau de bord. Cette action déclenche une seule commande d'éjection. Une issue réseau inconnue reste à rapprocher ; ne jamais relancer à l'aveugle.

Le contrat HeyCharge ne fournit ni événement de sortie ni signature pour les callbacks. Le système journalise `register`, `return`, `status`, sans les utiliser comme preuve. Le bouton « Vérifier la station » consulte l'API authentifiée : absence de la batterie après commande = sortie constatée ; présence à la station de départ ou dans une station de retour signalée = retour constaté. L'horodatage retenu est celui du rapprochement, pas nécessairement l'heure physique ; effectuer le rapprochement rapidement, particulièrement si une caution dépend de la durée. Sans caution, aucun reversement n'est émis. Les reversements positifs restent soumis à `AUTOMATIC_REFUNDS_ENABLED` et aux contrôles Paiement Pro existants.

L'intégration matérielle et le parcours de paiement n'ont pas été testés sur un vrai terminal ni sur une session Paiement Pro dans cet environnement. Conserver le kiosque fermé jusqu'au pilote et à la vérification des scénarios sortie, retour sur autre station et échec réseau.
