# Thiebapower 1.0.0

Première version, arrêtée le 4 octobre 2026. Développée par Success’Lab.

## Périmètre livré

- Stations HeyCharge, inventaire, sélection de batterie et parcours de location.
- Paiement PaiementPro, réservation temporaire, suivi du retrait et du retour.
- Tarification, caution facultative, retenue et restitution, rapprochement financier.
- Administration des stations et des gérants, carte publique, recherche et étiquettes QR.
- Mes locations, reçus, signalements et assistance configurable.
- Codes promo, fidélité et parrainage, publication et partage des campagnes.
- Caisse, suivi payout, statistiques, rentabilité, droits et journal.
- Formation client de 20 modules et portefeuille de propositions exportable en PDF.

## Mentions de version

`VERSION` et `App\Services\ReleaseInfo` indiquent la version de référence. Une mention discrète apparaît sur les pages publiques du parcours client et sur le dashboard. La mention publique peut être masquée dans Interface publique et support. Les exports existants gardent leur présentation.

## Limites connues

Le chat public ouvre un service externe configuré : il ne s’agit pas encore d’une messagerie interne. Mes locations repose sur les accès conservés dans le navigateur. Les propositions d’évolution ne déclenchent aucun développement, paiement ou déploiement. Les remboursements et sorties matérielles doivent être confirmés par leurs circuits respectifs.

## Déploiement de la mention de version

Mettre à jour le code depuis `develop` puis copier `public/release.css` vers la racine publique. Aucune migration supplémentaire. La publication GitHub n’exécute pas le déploiement du serveur. Le passage éventuel vers `main` relève du circuit de livraison retenu.
