# HeyCharge : vérification de la documentation pour Thiebapower V1

Date : 28 septembre 2026.

Le lien proposé, https://developer.heycharge.com/, présente des SDK mobiles et une API liés à des équipements de recharge **AC/DC**. Le site https://www.heycharge.com/ présente l'entreprise comme un fournisseur de recharge de **véhicules électriques**. Le fournisseur des stations de batteries externes utilisées ici présente son offre sur https://heycharge.global/ : stations de partage de powerbanks, applications, bornes et logiciel de gestion. La similarité des noms ne démontre pas une compatibilité entre leurs API.

**Conclusion pour la V1 :** ne pas envoyer de commandes matérielles vers `developer.heycharge.com`. Le service `HeyChargeOpenApi::release()` reste fermé. Le parcours administrateur peut simuler la sortie et le retour, tout en indiquant explicitement que les encaissements et reversements Paiement Pro sont réels.

Pour activer l'option 3 chez le fournisseur des powerbanks, obtenir de `heycharge.global` :

1. Le domaine API, l'environnement de test, l'authentification et une clé limitée à nos stations.
2. Le contrat de commande de libération et les identifiants station, slot et batterie ; la sémantique d'idempotence et de reprise après délai réseau.
3. Le format des événements de sortie et de retour physique, leur signature et leurs possibilités de rejeu/réconciliation.
4. La consultation du statut d'une station et d'un ordre en cas d'issue inconnue.
5. Une station de test et les cas d'erreur certifiés avant d'ouvrir le mode normal.

Références : https://developer.heycharge.com/ ; https://www.heycharge.com/ ; https://heycharge.global/ ; document du fournisseur des powerbanks « How to develop your own shared power bank rental system » (option 3).
