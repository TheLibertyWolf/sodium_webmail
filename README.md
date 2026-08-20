# Sodium Webmail

Sodium est un webmail professionnel multi-utilisateurs et multi-comptes. Il centralise des boîtes IMAP dans une interface unifiée et permet de consulter, classer, rédiger, programmer et suivre les messages selon des habilitations précises.

[![Version](https://img.shields.io/badge/version-1.3.0-2271b1)](https://github.com/TheLibertyWolf/sodium_webmail/releases)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D%208.2-777bb4)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-%3E%3D%208.0-4479a1)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/license-propri%C3%A9taire-46b450)](LICENSE.md)
[![Languages](https://img.shields.io/badge/languages-FR%20%7C%20EN%20%7C%20DE%20%7C%20IT%20%7C%20ES%20%7C%20PT-f48120)](#langues)

<sub>🇫🇷 Français · 🇬🇧 English · 🇩🇪 Deutsch · 🇮🇹 Italiano · 🇪🇸 Español · 🇵🇹 Português</sub>

> **Sodium Webmail 1.3.0** — version stable, officiellement disponible.

## Aperçu

![Aperçu de Sodium Webmail : connexion, boîte unifiée, comptes mails, lecture et rédaction](docs/screenshots/sodium-overview.png)

L’interface réunit l’authentification, la boîte de réception unifiée, la gestion multi-comptes, la lecture sécurisée et le rédacteur de messages. Sodium Outlook adopte une navigation horizontale complète ; Roundcube utilise un rail d’applications et un panneau de dossiers distinct.

## Fonctionnalités principales

- boîte de réception unifiée et navigation par compte et dossier ;
- comptes IMAP et SMTP provenant de plusieurs domaines ou hébergements ;
- compteurs de messages non lus et synchronisation périodique ;
- lecture sécurisée des messages et contrôle des images distantes ;
- aperçu et téléchargement des pièces jointes, dont téléchargement groupé en ZIP ;
- rédaction HTML, pièces jointes par glisser-déposer, Cc, Cci et priorité ;
- réponses, réponses à tous, transferts et regroupement par conversations ;
- brouillons, envois programmés, annulation différée et boîte d’envoi ;
- signatures, tags et modèles personnels ou partagés ;
- réponses automatiques et périodes d’absence ;
- contacts suggérés à partir des correspondants connus ;
- messages marqués, filtres lu/non lu et actions multiples ;
- notifications de bureau et Web Push ;
- quatre interfaces : Sodium Light, Sodium Dark, Sodium Outlook à navigation horizontale et Roundcube à panneaux spécialisés ;
- interface disponible en français, anglais, allemand, italien, espagnol et portugais, avec préférence propre à chaque utilisateur ;
- gestion autonome des utilisateurs, comptes, habilitations et paramètres d’instance ;
- obligation 2FA configurable individuellement par utilisateur ;
- protection Cloudflare Turnstile administrable depuis les paramètres de sécurité ;
- choix du transport des notifications système : SMTP Sodium, API Brevo ou fonction PHP `mail()` ;
- contrôle de la dernière exécution du cron avec commande cPanel prête à copier ;
- activation par licence liée au domaine.
- contrôle des versions GitHub et assistant de mise à jour avec sauvegarde et migrations SQL.

## Prérequis serveur

- serveur web Apache 2.4 compatible `.htaccess` et HTTPS ;
- PHP 8.2 ou version ultérieure ;
- MySQL 8 ou MariaDB 10.5 ou version ultérieure — une base SQL est obligatoire, Sodium ne peut pas fonctionner sans base de données ;
- accès sortant HTTPS vers le serveur de licences et, si activé, Cloudflare Turnstile ;
- accès réseau aux serveurs IMAP et SMTP configurés ;
- tâche cron exécutable au minimum une fois par minute ;
- certificat TLS valide sur le domaine d’installation.

### Extensions PHP obligatoires

- `imap` ;
- `pdo_mysql` ;
- `openssl` ;
- `curl` ;
- `mbstring` ;
- `fileinfo` ;
- `zip` ;
- `json` ;
- `iconv`.

Le wizard vérifie automatiquement ces dépendances avant l’installation.

### Base de données et droits SQL

Sodium nécessite une base MySQL ou MariaDB dédiée. La base et l’utilisateur SQL doivent être créés avant de lancer le wizard. L’utilisateur SQL doit disposer, uniquement sur la base Sodium, des droits suivants :

```text
ALTER
CREATE
DELETE
INSERT
SELECT
UPDATE
```

Ces six privilèges couvrent la création initiale des tables, les migrations de colonnes et le fonctionnement courant de Sodium. Aucun droit sur les routines, vues, triggers, événements, tables temporaires ou autres bases n’est requis.

Il n’est pas nécessaire d’accorder des privilèges globaux, la gestion des utilisateurs SQL ou l’accès aux autres bases du serveur.

## Composants d’interface

- Bootstrap `5.3.8`, fourni localement ou chargé par CDN selon le réglage d’instance ;
- Bootstrap Icons `1.13.1`, fourni localement ou chargé par CDN selon le réglage d’instance ;
- JavaScript natif, sans framework applicatif ;
- PWA avec manifeste et service worker.

DataTables n’est pas requis par Sodium.

## Langues

La langue est choisie pendant l’installation puis peut être personnalisée dans le profil de chaque utilisateur. Le français est la langue source et de repli.

| Langue | Locale Sodium | Couverture | État |
|---|---|---:|---|
| 🇫🇷 Français | `fr` | 125/125 — 100 % | Langue source |
| 🇬🇧 Anglais | `en` | 125/125 — 100 % | Complet |
| 🇩🇪 Allemand | `de` | 125/125 — 100 % | Complet |
| 🇮🇹 Italien | `it` | 125/125 — 100 % | Complet |
| 🇪🇸 Espagnol | `es` | 125/125 — 100 % | Complet |
| 🇵🇹 Portugais | `pt` | 125/125 — 100 % | Complet |

Les règles de traduction et de contribution sont détaillées dans [TRANSLATIONS.md](TRANSLATIONS.md).

## Installation

1. Déployer le contenu du produit à la racine du domaine.
2. Faire pointer le domaine vers cette racine et activer HTTPS.
3. Créer une base MySQL ou MariaDB vide et un utilisateur disposant des droits nécessaires sur cette base.
4. Ouvrir le domaine dans un navigateur.
5. Suivre le wizard : accueil et thème, dépendances, licence, activation, base SQL, administrateur et sécurité.
6. Configurer le premier compte mail depuis `Administration > Comptes mails`.
7. Configurer la tâche cron de traitement des envois.

Le domaine détecté doit être préalablement et exclusivement affecté à la clé dans le gestionnaire de licences Jessy System.

## Tâche cron

Le traitement des brouillons programmés, de la file d’envoi et des opérations différées utilise :

```text
https://votre-domaine.example/cron/send-mail-queue.php?token=VOTRE_JETON_CRON
```

Le jeton est généré durant l’installation et conservé dans `config.local.php`. Une exécution toutes les minutes est recommandée. Ne publiez jamais ce jeton et ne l’inscrivez pas dans un dépôt de code.

Exemple cPanel :

```cron
* * * * * curl --fail --silent --show-error "https://votre-domaine.example/cron/send-mail-queue.php?token=VOTRE_JETON_CRON" >/dev/null
```

## Configuration de la messagerie

Chaque boîte requiert :

- une adresse et un nom d’affichage ;
- un serveur, un port et un mode de chiffrement IMAP ;
- un serveur, un port et un mode de chiffrement SMTP ;
- un identifiant et un mot de passe ;
- éventuellement un label, une couleur et une icône PNG ou WebP.

Les mots de passe de messagerie sont chiffrés en AES-256-GCM à l’aide d’une clé propre à l’instance.

### Notifications système et mot de passe perdu

Dans `Administration > Paramètres généraux`, choisissez le transport utilisé pour les notifications et les codes de récupération :

- **Compte mail Sodium** : utilise les paramètres SMTP d’un compte mail actif ;
- **API Brevo** : nécessite une adresse expéditeur validée chez Brevo et une clé API, stockée chiffrée ;
- **PHP mail()** : choix initial par défaut, sous réserve que la fonction soit correctement configurée par l’hébergeur.

Si le transport sélectionné n’est pas utilisable, Sodium n’annonce pas l’envoi d’un code et indique clairement que la procédure de mot de passe perdu est indisponible. L’envoi PHP doit faire l’objet d’un contrôle de délivrabilité et d’une configuration correcte de SPF et DKIM.

## Sécurité

Sodium met notamment en œuvre :

- HTTPS obligatoire et HSTS ;
- cookies de session `Secure`, `HttpOnly` et `SameSite` ;
- protection CSRF des opérations modificatrices ;
- politique CSP, protection anti-framing et `Permissions-Policy` ;
- chiffrement des secrets de messagerie ;
- contrôle d’accès par utilisateur, aptitude et compte mail ;
- authentification à deux facteurs ;
- Cloudflare Turnstile facultatif, mais vivement recommandé pour la connexion et la récupération de mot de passe ;
- masquage des erreurs techniques côté client ;
- contrôle de licence lié au domaine.

Les fichiers `config.local.php`, `.sodium-mail-key` et `.installed` sont confidentiels, exclus de l’accès HTTP et doivent être sauvegardés dans un emplacement sécurisé.

## Navigateurs

Sodium vise les versions modernes de Chrome, Edge, Firefox et Safari sur ordinateur, tablette et mobile. Les notifications Web Push dépendent des capacités et des autorisations du navigateur et du système d’exploitation.

## Sauvegarde

Une sauvegarde complète doit inclure :

- la base SQL ;
- `config.local.php` ;
- `.sodium-mail-key` ;
- les icônes de comptes téléversées et les autres fichiers applicatifs persistants.

Sans `.sodium-mail-key`, les mots de passe de messagerie déjà chiffrés ne pourront pas être récupérés.

## Mise à jour

Avant toute mise à jour :

1. sauvegarder la base et les fichiers confidentiels ;
2. vérifier la compatibilité de PHP et des extensions ;
3. déployer les nouveaux fichiers sans écraser `config.local.php` ni `.sodium-mail-key` ;
4. vider ou renouveler le cache PWA si la version du service worker change ;
  5. contrôler la connexion, la relève IMAP, l’envoi SMTP et la tâche cron.

Un utilisateur disposant de l’aptitude « Mises à jour » peut également utiliser `Administration > Paramètres généraux` pour comparer la version installée à la dernière version GitHub. L’assistant accepte le téléchargement officiel ou une archive ZIP manuelle, crée une sauvegarde privée du code remplacé, protège les données propres à l’instance et exécute les migrations SQL encore absentes.

## Licence

Sodium est un logiciel propriétaire édité par Jessy System. Son installation et son utilisation nécessitent une licence valide affectée au domaine. Consultez [LICENSE.md](LICENSE.md) pour les conditions générales applicables.

Copyright © 2026 Jessy System — Tous droits réservés.

## Projet et contributions

Avant de participer, consultez le [guide de contribution](CONTRIBUTING.md), le [code de conduite](CODE_OF_CONDUCT.md), la [politique de sécurité](SECURITY.md), le [support](SUPPORT.md) et le [guide de traduction](TRANSLATIONS.md).
