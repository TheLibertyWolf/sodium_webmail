# Journal des modifications

Toutes les évolutions notables de Sodium Webmail sont consignées dans ce fichier.

## [0.10.0] — 2026-08-19

### Améliorations

- ajout d’un compte expéditeur par défaut, configurable par utilisateur dans Paramètres → Messages et utilisé lors d’une nouvelle rédaction depuis une vue unifiée.

### Corrections

- remplacement isolé de la signature lors d’un changement d’expéditeur : le corps, les modèles et le message cité sont désormais préservés quelle que soit la position configurée de la signature ;
- la signature est enveloppée dans une `<div class="signature">` indépendante avec son propre espacement, sans paragraphes éditables susceptibles de contenir puis de supprimer le texte saisi.

## [0.9.4] — 2026-08-09

### Améliorations

- ajout d’un périmètre de recherche sélectionnable : expéditeur/destinataire, objet, corps du message ou partout, avec recherche IMAP dans le contenu des messages ;
- ajout d’une recherche approfondie progressive à la demande : exploration d’un dossier par clic sans rechargement, ajout dynamique des résultats et recherche IMAP `TEXT` ;
- affichage des destinataires dans les dossiers individuels « Envoyés » : destinataire principal précédé du label « À », compteur compact des `Cc`/`Cci` et infobulle détaillée colorée ;
- regroupement des réglages de réception, d’envoi et de réponses dans une card unique avec un bouton d’enregistrement commun ;
- alignement corrigé de l’option « Partager avec les utilisateurs du compte » dans les fenêtres d’ajout et de modification des modèles de réponse ;
- ouverture systématique des liens contenus dans les messages et les réponses dans un nouvel onglet sécurisé.

### Corrections

- sécurisation centralisée de toutes les dates : prise en charge des commentaires de fuseau RFC présents dans certains en-têtes IMAP et valeur neutre pour toute date réellement invalide, sans erreur PHP ni mauvais classement ;
- suppression du défilement horizontal des dossiers vides sur mobile ;
- fermeture de l’infobulle des destinataires avant l’ouverture d’un message envoyé afin qu’elle ne recouvre plus la fenêtre de lecture.

## [0.9.3] — 2026-08-04

### Renforcement du 8 août 2026

- gestion des adresses mails supplémentaires : limite numérique ou illimitée, formulaire complet identique à l’administration, modification, icône, couleur, label et réglages IMAP/SMTP ;
- footer de lecture stabilisé sur une ligne en affichage ordinateur, avec bouton Fermer réservé au desktop ;
- documentation SQL réduite aux six privilèges réellement utilisés par Sodium.

- expiration de session traitée par une redirection vers la connexion, y compris pour les appels API, sans page blanche technique ; durée serveur portée à quatre heures ;
- téléversement immédiat et individuel des pièces jointes vers un stockage temporaire privé, avec progression par fichier, suppression individuelle et plafond global de 25 Mo ;
- auto-enregistrement du brouillon toutes les 30 secondes pendant la rédaction ;
- compteurs de courrier et de boîte d’envoi synchronisés en direct, relève silencieuse hors nouveaux messages et actualisation partielle de la boîte d’envoi ;
- lecture et rédaction longues sans double barre de défilement, actions de lecture réorganisées et adaptées au mobile ;
- copie d’une adresse mail depuis l’en-tête de lecture avec confirmation par toast ;
- ajout des comptes mails personnels avec quota par utilisateur, exclusions de domaines/adresses, séparation des comptes imposés et bannissement administratif ;
- contrôle des autorisations renforcé : chaque identifiant reçu reste vérifié contre l’utilisateur et ses comptes accessibles ; les nouveaux fichiers temporaires utilisent des jetons aléatoires de 256 bits.

### Maintenance

- ajout d’un manifeste npm déclaratif pour rendre les bibliothèques front-end visibles dans le Dependency graph GitHub.

### Corrections

- prise en compte prioritaire de l’en-tête `Reply-To` lors d’une réponse, avec repli sur `From`, et affichage de l’adresse effective dans la lecture du message.
- retour automatique à la page d’origine après un envoi ou une réponse, sans redirection vers la boîte d’envoi ;
- placement automatique du curseur dans le corps du message à l’ouverture d’une rédaction ou d’une réponse.
- distinction entre les véritables ressources intégrées au corps et les pièces jointes possédant un `Content-ID`, afin que ces dernières restent visibles et téléchargeables.

### Ajouts

- actions « Déplacer vers » et « Ajouter un tag » directement dans la modal de lecture d’un message.

## [0.9.2] — 2026-07-31

### Ajouts

- confirmation avant de quitter une rédaction, avec choix entre l’enregistrement en brouillon, l’abandon ou la poursuite de la rédaction ;
- gestion automatique de la disponibilité de l’action « Répondre à tous » selon les participants du message.

### Corrections

- actualisation automatique des dates relatives dans les listes de messages sans rechargement de page ;
- ajout cumulatif des pièces jointes lors de sélections successives, sans remplacement des fichiers déjà choisis ;
- conservation de la suppression individuelle des pièces jointes et nettoyage complet à la fermeture de la rédaction.

## [0.9.1] — 2026-07-30

### Ajouts

- configuration et contrôle du cron depuis l’administration ;
- paramètres de sécurité Cloudflare Turnstile ;
- obligation 2FA configurable par utilisateur ;
- transports des messages système par compte SMTP Sodium, API Brevo ou fonction PHP `mail()` ;
- documentation des droits SQL nécessaires à l’installation.

### Améliorations

- libellés et descriptions des aptitudes disponibles en français ;
- documentation d’installation et prérequis enrichis.

## [0.9.0] — 2026-07-29

- première version bêta publique de Sodium Webmail.
