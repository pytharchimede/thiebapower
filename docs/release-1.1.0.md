# THIEBAPOWER 1.1.0

## Paiements CI & Stabilisation

Cette version consolide le socle de THIEBAPOWER et les fonctionnalités nécessaires à l'exploitation du service de location de batteries externes.

### Paiements et encaissements

- Intégration et consolidation de PaiementPro.
- Gestion centralisée des moyens de paiement Côte d'Ivoire.
- Prise en charge de Wave, Orange Money, MTN MoMo et Moov Money.
- Activation indépendante des canaux d'encaissement et de payout.
- Contrôle côté serveur des moyens de paiement autorisés.
- Interface de sélection des moyens de paiement adaptée automatiquement au nombre de canaux actifs.
- Infrastructure de remboursement automatique de caution.
- Outils de diagnostic des payouts.

### Stations et locations

- Intégration HeyCharge consolidée.
- Synchronisation des stations et batteries.
- Gestion de l'inventaire des batteries.
- Gestion des locations et réservations.
- Outils de diagnostic des stations.
- Commandes de retrait manuel de batterie.
- Améliorations du parcours de location.

### Finance et administration

- Gestion financière et suivi des opérations.
- Paramétrage des moyens de paiement depuis l'administration.
- Gestion de la caution.
- Outils de diagnostic et de contrôle des transactions.
- Amélioration des interfaces administratives.

### SMS et communication

- Infrastructure Orange SMS.
- Gestion des modèles SMS et OTP.
- Workers et outils de diagnostic associés.

### Architecture et qualité

- Organisation framework-like avec routes web, API et administration séparées.
- Séparation des contrôleurs, modèles, repositories et services.
- Documentation technique structurée.
- Renforcement des tests automatisés.
- Nettoyage du dépôt Git.
- Protection des fichiers d'environnement, logs, certificats et autres données privées.
- Workflow Git basé sur develop, branches de travail, pull requests et main.

## Validation

La version 1.1.0 doit être publiée uniquement après validation complète de la suite automatisée et vérification d'un dépôt Git propre.
