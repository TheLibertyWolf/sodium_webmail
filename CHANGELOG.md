# Journal des modifications

Toutes les évolutions notables de Sodium Webmail sont consignées dans ce fichier.

## [0.9.3] — 2026-08-04

### Maintenance

- ajout d’un manifeste npm déclaratif pour rendre les bibliothèques front-end visibles dans le Dependency graph GitHub.

### Corrections

- prise en compte prioritaire de l’en-tête `Reply-To` lors d’une réponse, avec repli sur `From`, et affichage de l’adresse effective dans la lecture du message.
- retour automatique à la page d’origine après un envoi ou une réponse, sans redirection vers la boîte d’envoi ;
- placement automatique du curseur dans le corps du message à l’ouverture d’une rédaction ou d’une réponse.

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
