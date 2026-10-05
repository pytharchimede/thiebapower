<?php
return [
 'version'=>'1.0 - 04 octobre 2026',
 'intro'=>'Guide destiné au propriétaire, aux responsables de stations et aux opérateurs Thiebapower. Les menus dépendent des permissions du compte. Les prix, les cautions et les canaux de paiement sont ceux configurés dans la plateforme : les exemples de ce guide ne changent aucun réglage. Réalisez les exercices dans un environnement de test ; une opération en production peut débiter un client ou déclencher une sortie physique.',
 'modules'=>[
 ['Découvrir le service','Comprendre le parcours et les responsabilités.',[
 'Thiebapower loue des batteries externes dans des stations. Le client choisit sa batterie, paie, récupère la batterie libérée puis la rapporte dans une station.',
 'Le propriétaire supervise le parc, les tarifs, les encaissements et le support. Le gérant aide au retrait et au retour. Le technicien contrôle les connexions et le matériel.',
 'Une location payée, une batterie sortie et une batterie rendue sont trois événements distincts. Un paiement seul ne prouve pas la sortie ; une déclaration du client ne prouve pas le retour.'
 ],'Reformuler les quatre étapes et identifier qui contacter pour un problème matériel et un problème de paiement.','/admin'],
 ['Se connecter et gérer les accès','Utiliser le dashboard avec les bons droits.',[
 'Ouvrez /admin et connectez-vous avec votre compte personnel. N’utilisez pas un compte partagé pour les opérations sensibles.',
 'Dans Comptes et droits, attribuez uniquement les permissions nécessaires : consultation des locations, gestion des stations, accès financier ou paramétrage. Un menu absent peut correspondre à une permission manquante.',
 'Réservez les modes API, les paramètres système et les droits utilisateurs aux responsables autorisés. Déconnectez-vous sur un appareil partagé et ne transmettez jamais de clés API au client final.'
 ],'Sur un compte de consultation, vérifier que les actions de modification sensibles sont refusées.','/admin/users'],
 ['Lire le tableau de bord','Repérer les actions prioritaires.',[
 'Le dashboard résume les stations, batteries et locations. Ouvrez les statistiques pour analyser une période. Vérifiez les dates de lecture et de dernière exécution des services avant de conclure.',
 'Commencez par À surveiller : paiements à vérifier, sorties non confirmées et locations en retard. Ouvrez la fiche concernée pour comprendre le contexte.',
 'Une lecture ancienne indique une information à confirmer. Ne promettez pas une disponibilité sur la seule base d’une donnée périmée.'
 ],'Retrouver une location à surveiller et expliquer la différence entre indisponibilité et information ancienne.','/admin/rentals/watch'],
 ['Nommer et positionner une station','Donner au client un lieu facile à retrouver.',[
 'Depuis Terminaux, ouvrez la fiche puis le profil. Donnez un nom commercial clair plutôt que le seul identifiant matériel.',
 'Tapez le nom du lieu ou une adresse, choisissez une proposition puis vérifiez le marqueur sur la carte. Ajustez sa position si nécessaire. Enregistrez sans saisir manuellement des coordonnées GPS.',
 'Renseignez adresse, horaires, type de lieu et coordonnées du gérant. Le nom et le téléphone du gérant peuvent être affichés publiquement ; les notes et l’email interne ne font pas partie de la carte publique.',
 'Conservez l’identifiant matériel d’origine. Si aucune adresse n’est trouvée, utilisez la carte et vérifiez le lieu avec le gérant.'
 ],'Créer un profil de test, repositionner le marqueur et vérifier le nom ainsi que le lieu sur la carte publique.','/admin/stations'],
 ['Comprendre l’inventaire','Distinguer stock, charge et état matériel.',[
 'La fiche station présente les batteries, leur emplacement, leur numéro, leur charge et leur état. Synchronisez pour obtenir une lecture récente.',
 'Une batterie disponible doit répondre aux critères de location en vigueur. Une batterie absente, réservée ou en maintenance n’est pas équivalente à une batterie prête à sortir.',
 'Les actions de retrait manuel ou de réinsertion sont réservées au personnel autorisé. Contrôlez physiquement le slot et le numéro avant une commande ; évitez tout nouvel essai pendant une commande en attente.'
 ],'Comparer le nombre affiché au nombre physiquement présent dans une station de test.','/admin/batteries'],
 ['Activer une station et contrôler les services','Préparer une station avant son ouverture.',[
 'Vérifiez alimentation, connexion, lecture récente et disponibilité des batteries. Activez les locations uniquement quand le service et les moyens de paiement sont prêts.',
 'Les modes HeyCharge simulation/normal et PaiementPro sandbox/production ne sont pas interchangeables. Un changement de mode doit être décidé par le responsable technique.',
 'Le worker et les notifications assurent le suivi asynchrone. Si une lecture ou une confirmation tarde, contrôlez le diagnostic et le journal plutôt que de relancer une commande physique.'
 ],'Expliquer comment confirmer que la station et les services sont prêts avant l’ouverture.','/admin/system'],
 ['Préparer les QR et étiquettes','Guider le client vers la bonne station.',[
 'Depuis Étiquettes QR, choisissez les stations et les dimensions. Ajustez les marges, les espacements et la taille des logos avant de générer le PDF.',
 'Imprimez à taille réelle, sans adaptation automatique. Scannez chaque QR avec un téléphone et vérifiez le nom et l’identifiant de la station ouverte.',
 'Positionnez l’étiquette à un endroit accessible. Une étiquette d’une autre station dirige le client vers le mauvais inventaire : remplacez-la avant de proposer la location.'
 ],'Imprimer une étiquette de test et contrôler sa destination avec deux téléphones.','/admin/stations/labels'],
 ['Accompagner la location du client','Expliquer le paiement et le retrait sans confusion.',[
 'Le client scanne le QR ou ouvre Trouver une station puis Louer ici. Il choisit une batterie disponible et passe au récapitulatif.',
 'Il renseigne son nom, son téléphone et, si proposé, son code promo. Il vérifie le tarif, la durée incluse et la caution éventuelle avant de payer.',
 'Le retour du prestataire de paiement peut afficher une vérification en cours. Le retrait intervient après confirmation du paiement et de la sortie : invitez le client à consulter le statut avant de récupérer la batterie.',
 'Sans suite après le délai de réservation, la batterie redevient louable selon le mécanisme prévu. Ne contournez pas ce contrôle par une nouvelle sortie manuelle.'
 ],'Faire un parcours sandbox : sélection, récapitulatif, paiement de test et lecture du statut.','/'],
 ['Expliquer le tarif et le dépassement','Présenter les règles en langage simple.',[
 'Le tarif initial couvre la durée configurée. La règle en vigueur prévoit ensuite cinq minutes gratuites. Au-delà, le dépassement est calculé au prorata du tarif effectivement payé et arrondi au FCFA supérieur.',
 'Exemple pédagogique : tarif 600 FCFA pour 60 minutes, batterie rendue 75 minutes après le début. Après les 5 minutes gratuites, 10 minutes sont facturables : 600 / 60 × 10 = 100 FCFA.',
 'Avec une caution de 200 FCFA dans cet exemple, la retenue est de 100 FCFA et le reste à restituer est de 100 FCFA. La retenue ne dépasse jamais la caution.',
 'Quand la caution est désactivée, aucune retenue automatique n’est appliquée. Une remise sur la caution ne change pas le tarif utilisé pour le prorata. Vérifiez toujours la règle et les montants de la fiche réelle.'
 ],'Calculer un retour dans le délai, pendant les cinq minutes gratuites, puis dix minutes après la période gratuite.','/admin/pricing'],
 ['Confirmer le retour et fournir le reçu','Terminer la location sur la base du retour reconnu.',[
 'Le client rapporte la batterie dans une station adaptée. Il vérifie que le retour est reconnu dans Mes locations.',
 'L’administration consulte le retour matériel, la fin de la location, le calcul final et la restitution éventuelle. Une batterie posée sur le comptoir n’est pas encore un retour reconnu par le système.',
 'Après retour confirmé, le client peut télécharger son reçu. Mes locations dépend des accès conservés dans le navigateur utilisé : changer de téléphone ou effacer les données peut empêcher de retrouver cet historique.',
 'En cas de retour non reconnu, demandez la station, l’heure, la référence et le numéro de batterie. Vérifiez l’inventaire et le journal avant toute correction.'
 ],'Retrouver le reçu d’une location terminée dans le navigateur de test.','/my-rentals'],
 ['Suivre les cautions et restitutions','Ne pas confondre paiement et remboursement.',[
 'La caution est distincte du revenu de location. Elle peut être désactivée globalement ; quand elle est active, le reste après retenue suit le circuit de restitution prévu.',
 'Consultez Cautions et solde payout pour distinguer encaissement client, transfert vers le solde de payout, remboursement en attente et remboursement confirmé.',
 'Une réponse technique de création de demande ne prouve pas à elle seule le crédit effectif du solde ou la réception par le client. Rapprochez avec le fournisseur et le journal.',
 'Les frais de remboursement sont pris en charge par Thiebapower. Un payout réel débite des fonds : vérifiez montant, numéro et canal ; ne relancez pas un essai en attente.'
 ],'Identifier un remboursement confirmé et expliquer les preuves nécessaires avant de le clôturer.','/admin/deposit-wallet'],
 ['Gérer les incidents et le support','Aider un client bloqué avec des informations précises.',[
 'Dans Mes locations, le client peut signaler batterie non sortie, retour non reconnu, problème de paiement ou autre incident. Le signalement est lié à la location ; un brouillon est conservé pendant les actualisations.',
 'Dans Assistance, l’opérateur lit la demande, retrouve la location et contacte le client. Renseignez une résolution claire avant de clôturer ; ne déclarez pas résolu sans vérification.',
 'Dans Interface publique et support, renseignez WhatsApp au format international, téléphone et email ; activez les canaux voulus et le bouton global. Vérifiez ensuite Besoin d’aide ? sur un téléphone.',
 'Le canal Chat ouvre un service externe déjà disponible via HTTPS. Il n’existe pas de messagerie interne dans cette version. Ne promettez pas une réponse instantanée si aucune équipe n’est disponible.'
 ],'Créer un incident de test, le traiter puis vérifier la résolution côté client.','/admin/support'],
 ['Réagir aux cas de blocage','Suivre un protocole sans doubler les opérations.',[
 'Paiement débité sans sortie : demander la référence, vérifier notification du fournisseur, état de location et journal matériel. Une nouvelle demande de paiement ou une nouvelle sortie n’est pas la première réponse.',
 'Batterie non visible : contrôler la lecture récente, la charge, le slot, une réservation ou une maintenance. Station hors ligne : vérifier alimentation et connexion avant d’activer la location.',
 'Retour non reconnu : noter heure et station, vérifier présence physique et synchronisation. Restitution non reçue : vérifier numéro, canal et confirmation fournisseur avant tout nouvel envoi.',
 'Documentez chaque action dans le circuit prévu et escaladez au responsable technique si les preuves ne concordent pas. Ne modifiez pas directement les tables de production.'
 ],'Simuler un appel client et constituer le dossier de vérification : référence, station, heure, symptôme et preuves.','/admin/rentals/watch'],
 ['Créer et partager un code promo','Maîtriser remise, visibilité et limites.',[
 'Dans Offres et fidélité, créez un code unique, choisissez campagne ou fidélité, remise fixe, dates et limite d’utilisations. La remise s’applique uniquement à la caution et ne change pas le tarif de location. Elle est plafonnée à la caution.',
 'Les réservations consomment l’utilisation même en cas de paiement abandonné. Chaque code ne peut être réutilisé par le même numéro. Les conditions de fidélité reposent aussi sur une location précédente retrouvée dans le navigateur.',
 'Les codes restent privés par défaut. Rendre public concerne les campagnes : seules les offres actives, non expirées, non épuisées et compatibles avec le tarif apparaissent sur /offers, si l’activation globale est autorisée.',
 'Copiez le code ou préparez un partage WhatsApp, Telegram, X ou autre. Le partage n’envoie pas automatiquement le message. Le client saisit puis vérifie le code avant le paiement. Fidélité et parrainage restent ciblés.'
 ],'Créer un code en test, vérifier sa visibilité privée/publique, le partager et contrôler le montant du récapitulatif.','/admin/promotions'],
 ['Alléger l’interface publique','Afficher uniquement les éléments utiles.',[
 'Ouvrez Interface publique et support. Les interrupteurs permettent de masquer les textes de présentation, étapes, logos de paiement, raccourcis, compteurs et certaines informations de station.',
 'Le support est discret et ne révèle ses canaux qu’au clic. Masquer nom ou téléphone du gérant retire aussi ces données du snapshot public.',
 'Les informations indispensables au choix, au paiement, à la facturation et à la confirmation restent visibles. Un réglage de présentation ne remplace pas une permission d’accès.',
 'Enregistrez puis rechargez accueil, location, carte, offres et Mes locations sur mobile. Vérifiez que le client peut toujours louer et demander de l’aide.'
 ],'Masquer une information secondaire puis la réafficher, sans gêner la location.','/admin/public-settings'],
 ['Gérer la caisse et les exports','Rapprocher l’argent et les événements de location.',[
 'Dans Caisse et finances, consignez les mouvements selon les justificatifs disponibles. Séparez tarif initial, retenue de dépassement et caution à restituer.',
 'Un état de location seul n’est pas une preuve d’encaissement. Contrôlez la confirmation du fournisseur et la référence ; rapprochez les journaux en cas de doute.',
 'Exportez la période et le périmètre utiles. Les rapports présentent un récapitulatif et les détails. Conservez une copie datée et partagez uniquement les informations nécessaires au destinataire.',
 'Les retraits payout sont des paiements réels. Respectez les permissions financières et vérifiez numéro bénéficiaire, montant, canal et statut final.'
 ],'Préparer un export d’une période de test et expliquer chaque montant du récapitulatif.','/admin/finance'],
 ['Analyser la rentabilité','Mesurer le résultat sans surestimer le revenu.',[
 'Renseignez l’investissement et les coûts par station : frais, maintenance, emplacement et autres charges justifiées.',
 'La rentabilité distingue revenu de location, retenues et coûts enregistrés. La caution n’est pas un chiffre d’affaires. Une donnée non renseignée n’est pas une charge réellement nulle.',
 'L’enregistrement d’un coût analytique ne crée pas un décaissement physique de caisse : consignez séparément le mouvement si nécessaire.',
 'Comparez périodes, utilisation et coût des emplacements. Une projection n’est pas une garantie de bénéfice : basez vos décisions sur plusieurs semaines de données rapprochées.'
 ],'Renseigner un investissement et une charge de test puis expliquer leur effet dans le rapport.','/admin/stations/profitability'],
 ['Routine d’ouverture et de clôture','Installer une exploitation régulière.',[
 'À l’ouverture : contrôler alimentation, connexion, dernière lecture, stock, QR, lieux et canaux du support. Vérifier À surveiller et les incidents ouverts.',
 'Pendant la journée : aider au retrait et au retour, suivre les sorties en attente et les remboursements, noter les anomalies avec références précises.',
 'À la clôture : rapprocher paiements, remboursements et caisse ; vérifier les retards et incidents non traités ; préparer la transmission au responsable.',
 'Chaque semaine : contrôler étiquettes, horaires, coordonnées, état matériel, rentabilité et limites des promotions. La sauvegarde et la mise à jour technique sont réalisées par le responsable autorisé.'
 ],'Effectuer une ouverture et une clôture de test avec une liste de contrôles signée.','/admin'],
 ['Utiliser Formation et évolutions','Partager une proposition sans la confondre avec une fonction livrée.',[
 'Le guide se consulte depuis le dashboard et s’exporte en PDF. La version indique la date de référence ; les paramètres réels peuvent évoluer.',
 'Les propositions décrivent un besoin, son intérêt, un périmètre, une priorité, un statut et éventuellement un budget ou un délai indicatif. Un élément proposé n’est pas automatiquement développé ni déployé.',
 'Seul un compte autorisé peut créer ou modifier une proposition. Le statut Validée formalise le choix de suivi mais ne déclenche aucune facturation ni installation.',
 'Exportez une proposition ou le portefeuille en PDF. Téléchargez-le puis joignez-le manuellement à un email ou à WhatsApp. L’export ne partage pas automatiquement les données du dashboard.'
 ],'Créer une proposition, la relire avec le client, modifier son statut et exporter son PDF.','/admin/training'],
 ['Valider la formation','Confirmer l’autonomie et organiser le suivi.',[
 'Session suggérée : 30 minutes de découverte et stations, 45 minutes de parcours client et incidents, 30 minutes de finance et promotions, 15 minutes de contrôle final.',
 'Critères de réussite : retrouver une location, contrôler le paiement et le retour, expliquer le tarif, traiter un incident, configurer le support, vérifier la carte et exporter un document.',
 'Questions de contrôle : un retour sur la page de paiement prouve-t-il le débit ? Une caution est-elle un revenu ? Peut-on renvoyer un payout en attente ? Où se trouve le reçu ? Qui peut changer les paramètres ?',
 'Réponses : non, attendre la confirmation ; non, distinguer la caution ; non, vérifier le premier essai ; dans Mes locations après retour confirmé ; un compte disposant des permissions requises.',
 'Le formateur note date, participants, exercices réussis, difficultés et actions de suivi. Prévoir une revue après une semaine d’exploitation.'
 ],'Réaliser les critères d’autonomie sans assistance, puis noter les points à revoir.','/admin/training'],
 ]
];
