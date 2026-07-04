# SPÉCIFICATION FONCTIONNELLE
# SecureVault – Gestionnaire de mots de passe sécurisé

---

# 1. Présentation du projet

## 1.1 Contexte

La multiplication des services numériques impose aux utilisateurs de gérer un nombre croissant d'identifiants et de mots de passe. Cette situation conduit souvent à l'utilisation de mots de passe faibles, à leur réutilisation, ou à un stockage non sécurisé.

**SecureVault** est une application web permettant de stocker, organiser, partager et sécuriser des mots de passe dans des coffres numériques chiffrés. Elle s'inspire de Bitwarden, LastPass et 1Password.

## 1.2 Objectifs

- Gestion sécurisée des mots de passe (AES-256-GCM)
- Organisation par coffres thématiques
- Partage sécurisé avec gestion des permissions
- Détection des risques (mots de passe faibles, anciens)
- Authentification moderne (email + Google OAuth2 + 2FA)
- Administration complète de la plateforme
- API REST pour usage programmatique

## 1.3 Modèle économique

L'application est **100% gratuite**. Toutes les fonctionnalités sont accessibles sans abonnement ni carte bancaire.

---

# 2. Acteurs

## Visiteur
- Consulter la page d'accueil
- Créer un compte (email ou Google)
- Se connecter (email ou Google)
- Utiliser le générateur de mots de passe public

## Utilisateur authentifié
- Gérer son profil (nom, e-mail, mot de passe, photo)
- Créer, modifier, archiver, supprimer des coffres
- Gérer des mots de passe dans les coffres
- Partager des coffres avec d'autres utilisateurs
- Consulter les alertes et notifications
- Activer / désactiver la 2FA
- Utiliser la recherche globale
- Utiliser l'API REST

## Administrateur
- Accéder au back-office `/admin` (EasyAdmin)
- Gérer tous les utilisateurs (CRUD, rôles, statuts)
- Consulter tous les coffres, alertes, journaux
- Gérer les permissions et rôles

---

# 3. Entités du système

Le système contient **15 entités métier** (dont `googleId` ajouté sur `User`).

## 3.1 User

Représente un utilisateur enregistré.

| Champ              | Type      | Description                                    |
| :----------------- | :-------- | :--------------------------------------------- |
| `id`               | integer   | Identifiant auto-incrémenté                    |
| `email`            | string    | Adresse e-mail (unique)                        |
| `password`         | string    | Hash Argon2id (vide pour comptes Google)       |
| `firstName`        | string    | Prénom                                         |
| `lastName`         | string    | Nom                                            |
| `isActive`         | boolean   | Compte actif                                   |
| `emailVerified`    | boolean   | E-mail vérifié (automatique via Google)        |
| `profileImage`     | string    | Chemin vers l'image de profil (nullable)       |
| `encryptionKey`    | string    | Clé de chiffrement personnelle (nullable)      |
| `is2faEnabled`     | boolean   | 2FA activée                                    |
| `twoFactorSecret`  | string    | Secret 2FA (nullable)                          |
| `googleId`         | string    | Identifiant Google OAuth2 (nullable, unique)   |
| `createdAt`        | datetime  | Date de création                               |
| `updatedAt`        | datetime  | Date de dernière modification (nullable)       |

**Relations :** ManyToMany Role · OneToMany Vault · OneToMany Notification · OneToMany ActivityLog · OneToMany Alert · OneToMany LoginAttempt · OneToMany UserSession

---

## 3.2 Role

Définit un rôle fonctionnel (`ROLE_USER`, `ROLE_MANAGER`, `ROLE_ADMIN`).

| Champ         | Type   |
| :------------ | :----- |
| `id`          | int    |
| `name`        | string |
| `description` | string |

**Relations :** ManyToMany User · ManyToMany Permission

---

## 3.3 Permission

Permission précise attachée à un rôle (`USER_MANAGE`, `VAULT_CREATE`, `ALERT_MANAGE`…).

| Champ         | Type   |
| :------------ | :----- |
| `id`          | int    |
| `code`        | string |
| `description` | string |

**Relations :** ManyToMany Role

---

## 3.4 Vault

Coffre contenant des mots de passe.

| Champ         | Type     |
| :------------ | :------- |
| `id`          | int      |
| `name`        | string   |
| `description` | string   |
| `archived`    | boolean  |
| `createdAt`   | datetime |
| `updatedAt`   | datetime |

**Relations :** ManyToOne User (owner) · OneToMany PasswordEntry · OneToMany SharedVault · ManyToMany Tag

---

## 3.5 VaultPermission

Niveau d'accès à un coffre partagé.

| Code    | Description    |
| :------ | :------------- |
| `READ`  | Consultation   |
| `WRITE` | Modification   |
| `ADMIN` | Administration |

---

## 3.6 SharedVault

Partage d'un coffre entre deux utilisateurs.

| Champ      | Type     |
| :--------- | :------- |
| `id`       | int      |
| `accepted` | boolean  |
| `sharedAt` | datetime |

**Relations :** ManyToOne User (sender) · ManyToOne User (recipient) · ManyToOne Vault · ManyToOne VaultPermission

---

## 3.7 PasswordEntry

Entrée de mot de passe chiffrée.

| Champ               | Type     |
| :------------------ | :------- |
| `id`                | int      |
| `title`             | string   |
| `username`          | string   |
| `encryptedPassword` | string   |
| `url`               | string   |
| `notes`             | text     |
| `favorite`          | boolean  |
| `createdAt`         | datetime |
| `updatedAt`         | datetime |

**Relations :** ManyToOne Vault · ManyToOne User · ManyToMany Category · ManyToMany Tag · OneToMany PasswordHistory

---

## 3.8 Category

Organisation des mots de passe (Travail, Banque, Personnel…).

**Relations :** ManyToMany PasswordEntry

---

## 3.9 Tag

Étiquettes libres (Important, Urgent, Personnel…).

**Relations :** ManyToMany PasswordEntry · ManyToMany Vault

---

## 3.10 Alert

Alerte de sécurité générée par le système.

| Champ         | Type     |
| :------------ | :------- |
| `id`          | int      |
| `title`       | string   |
| `description` | text     |
| `type`        | string   |
| `category`    | string   |
| `isRead`      | boolean  |
| `createdAt`   | datetime |

**Relations :** ManyToOne User

---

## 3.11 Notification

Notification interne de l'application.

| Champ      | Type     |
| :--------- | :------- |
| `id`       | int      |
| `title`    | string   |
| `message`  | text     |
| `type`     | string   |
| `isRead`   | boolean  |
| `isDismissed` | boolean |
| `createdAt` | datetime |

**Relations :** ManyToOne User

---

## 3.12 ActivityLog

Journal de toutes les actions utilisateur.

| Champ       | Type     |
| :---------- | :------- |
| `id`        | int      |
| `action`    | string   |
| `ipAddress` | string   |
| `userAgent` | string   |
| `createdAt` | datetime |

**Relations :** ManyToOne User

---

## 3.13 LoginAttempt

Historique des tentatives de connexion.

| Champ       | Type     |
| :---------- | :------- |
| `id`        | int      |
| `ipAddress` | string   |
| `success`   | boolean  |
| `createdAt` | datetime |

**Relations :** ManyToOne User

---

## 3.14 UserSession

Sessions actives de l'utilisateur.

**Relations :** ManyToOne User

---

## 3.15 PasswordHistory

Historique des anciens mots de passe.

| Champ                  | Type     |
| :--------------------- | :------- |
| `id`                   | int      |
| `previousPasswordHash` | string   |
| `changedAt`            | datetime |

**Relations :** ManyToOne PasswordEntry

---

# 4. Fonctionnalités implémentées

## Gestion des comptes

| Fonctionnalité                         | Statut |
| :------------------------------------- | :----: |
| Inscription par e-mail                 | ✅     |
| Inscription par Google OAuth2          | ✅     |
| Vérification d'e-mail obligatoire      | ✅     |
| Connexion par e-mail + mot de passe    | ✅     |
| Connexion par Google OAuth2            | ✅     |
| Liaison automatique compte existant    | ✅     |
| Double authentification (2FA e-mail)   | ✅     |
| Gestion du profil (nom, photo, mdp)    | ✅     |
| Déconnexion                            | ✅     |

## Gestion des coffres

| Fonctionnalité        | Statut |
| :-------------------- | :----: |
| Création              | ✅     |
| Modification          | ✅     |
| Archivage             | ✅     |
| Suppression           | ✅     |
| Partage (READ/WRITE/ADMIN) | ✅ |
| Accepter / refuser partage | ✅ |
| Révoquer un partage   | ✅     |

## Gestion des mots de passe

| Fonctionnalité                | Statut |
| :---------------------------- | :----: |
| Création                      | ✅     |
| Consultation (déchiffrement)  | ✅     |
| Modification                  | ✅     |
| Suppression                   | ✅     |
| Vue globale (`/passwords`)    | ✅     |
| Recherche (titre/url/coffre)  | ✅     |
| Favoris                       | ✅     |

## Générateur de mots de passe

Disponible sur la page d'accueil et dans le dashboard.

| Paramètre           | Options                  |
| :------------------ | :----------------------- |
| Longueur            | 8 à 40 caractères        |
| Majuscules          | A-Z                      |
| Minuscules          | a-z                      |
| Chiffres            | 0-9                      |
| Caractères spéciaux | !@#$%^&*-_=+?            |
| Indicateur de force | Très faible → Très fort  |
| Temps de craquage   | Estimé en temps réel     |

## Audit de sécurité

| Fonctionnalité                        | Statut |
| :------------------------------------ | :----: |
| Score de sécurité sur 100             | ✅     |
| Détection mots de passe faibles       | ✅     |
| Détection mots de passe anciens (6m)  | ✅     |
| Centre d'alertes                      | ✅     |
| Suivi des connexions suspectes        | ✅     |

## Tableau de bord

- Nombre de coffres actifs
- Nombre de mots de passe
- Nombre d'alertes non lues
- Score de sécurité (jauge circulaire)
- Activité récente
- Générateur de mots de passe (colonne droite)
- Panneau d'alertes (colonne droite)

---

# 5. Interface utilisateur

## Design system

- **Police :** Manrope (Google Fonts) + IBM Plex Mono (générateur)
- **Palette :** vert marque `#142F32`, lime `#E3FFCC`, charcoal `#282930`, canvas `#e9ecef`
- **Logo :** cercle pointillé SVG (identique web + emails)
- **Framework CSS :** Tailwind CSS (CDN) avec palette nommée

## Responsive / Mobile-first

- Sidebar off-canvas avec bouton hamburger (mobile)
- Navbar home avec drawer animé
- Formulaires, tableaux et grilles adaptés à tous les écrans
- Scroll horizontal sur les tableaux de données

## Animations

- Icônes flottantes animées sur la home et les pages auth (CSS `@keyframes`)
- Logos de marques (Google, GitHub, Spotify…) dans une orbite animée
- Anneaux rotatifs en pointillés autour de l'orbite

## Pages principales

| Page          | Description                                                   |
| :------------ | :------------------------------------------------------------ |
| Home `/`      | Hero, générateur, fonctionnalités, sécurité, tarifs, services |
| Login `/login` | Fond canvas, icônes flottantes, bouton Google               |
| Register `/register` | Même style, barres de force du mot de passe          |
| 2FA `/2fa/verify` | Page autonome, saisie du code à 6 chiffres              |
| Dashboard     | Stats, jauge score, activité, générateur, alertes            |

---

# 6. Sécurité

## Authentification

| Mécanisme                  | Détails                                             |
| :------------------------- | :-------------------------------------------------- |
| Email + mot de passe       | Symfony Security + form_login                       |
| Google OAuth2              | KnpU OAuth2 Client Bundle + league/oauth2-google    |
| Double authentification    | Code 6 chiffres par e-mail, TTL 10 min              |
| JWT (API)                  | LexikJWTAuthenticationBundle, clés RSA              |

## Chiffrement

| Élément               | Algorithme    |
| :-------------------- | :------------ |
| Mots de passe stockés | AES-256-GCM   |
| Mots de passe users   | Argon2id      |

## Contrôle d'accès

- **Rôles :** `ROLE_USER`, `ROLE_MANAGER`, `ROLE_ADMIN`
- **VaultVoter :** `VIEW` / `EDIT` / `DELETE` / `SHARE`
- **2FA :** bloquée pour les logins Google (identité prouvée par Google)

---

# 7. API REST

**Préfixe :** `/api/v1/` · **Auth :** JWT Bearer Token

| Groupe         | Endpoints                                          |
| :------------- | :------------------------------------------------- |
| Auth           | `POST /api/v1/auth/login`                          |
| Coffres        | CRUD `/api/v1/vaults`                              |
| Mots de passe  | CRUD `/api/v1/vaults/{vaultId}/passwords`          |

---

# 8. E-mails transactionnels

Envoyés via **Symfony Mailer** · Interceptés par **Mailpit** en développement.

| E-mail                    | Déclencheur                              |
| :------------------------ | :--------------------------------------- |
| Confirmation d'inscription | Après inscription par e-mail           |
| Code 2FA                  | À chaque connexion avec 2FA activée     |

> Note : l'e-mail de réinitialisation de mot de passe n'est pas encore implémenté.

---

# 9. Services

| Service                    | Rôle                                                  |
| :------------------------- | :---------------------------------------------------- |
| `EncryptionService`        | Chiffrement / déchiffrement AES-256-GCM               |
| `TwoFactorService`         | Génération, envoi et vérification des codes 2FA       |
| `AlertService`             | Création et gestion des alertes de sécurité           |
| `NotificationService`      | Création et envoi des notifications internes          |
| `ActivityLogService`       | Journalisation des actions utilisateur                |
| `LoginAttemptService`      | Suivi des tentatives de connexion                     |
| `PasswordGeneratorService` | Génération de mots de passe avec score de force       |
| `EmailVerificationService` | Envoi et vérification du lien de confirmation e-mail  |
| `FileUploader`             | Upload et gestion des images de profil                |

---

# 10. Event Subscribers

| Subscriber                      | Événements écoutés                                               |
| :------------------------------ | :--------------------------------------------------------------- |
| `TwoFactorSubscriber`           | `LoginSuccessEvent` (déclenche 2FA), `KernelEvents::REQUEST` (bloque si 2FA pending) |
| `EmailVerificationSubscriber`   | Vérifie le statut e-mail sur chaque requête                      |

---

# 11. Sécurité — Authenticators

| Authenticator        | Mécanisme                                                         |
| :------------------- | :---------------------------------------------------------------- |
| `form_login`         | E-mail + mot de passe (Symfony natif)                            |
| `GoogleAuthenticator`| OAuth2 Google — création/liaison/connexion automatique           |

---

# 12. Administration

Back-office EasyAdmin accessible à `/admin` (rôle `ROLE_ADMIN`).

| Section              | Contenu                                          |
| :------------------- | :----------------------------------------------- |
| Utilisateurs         | CRUD complet, rôles, e-mail vérifié              |
| Coffres              | Vue et gestion de tous les coffres               |
| Alertes              | Consultation et suppression                      |
| Journal d'activité   | Historique de toutes les actions                 |
| Tentatives connexion | IP, dates, succès/échec                          |

---

# 13. Tests

| Type           | Commande               | Couverture                          |
| :------------- | :--------------------- | :---------------------------------- |
| Unitaires      | `make test-unit`       | Services, Security                  |
| Fonctionnels   | `make test-functional` | Contrôleurs (WebTestCase)           |
| E2E            | `make test-e2e`        | Navigateur headless (Panther)       |

---

# 14. Infrastructure

| Composant    | Technologie          | Version  |
| :----------- | :------------------- | :------- |
| Backend      | Symfony              | 7.x      |
| Serveur      | FrankenPHP           | Latest   |
| Base données | PostgreSQL           | 16       |
| ORM          | Doctrine             | Latest   |
| Auth OAuth2  | KnpU OAuth2 Bundle   | 2.x      |
| Auth JWT     | LexikJWT Bundle      | Latest   |
| Admin        | EasyAdminBundle      | Latest   |
| CSS          | Tailwind CSS         | CDN      |
| Mailer (dev) | Mailpit              | Latest   |

---

# 15. Variables d'environnement sensibles

| Variable               | Description                              |
| :--------------------- | :--------------------------------------- |
| `VAULT_ENCRYPTION_KEY` | Clé AES-256 — ne jamais commiter         |
| `JWT_PASSPHRASE`       | Passphrase clés RSA                      |
| `Google_Client_ID`     | OAuth2 Google — Google Cloud Console     |
| `Google_Client_Secret` | OAuth2 Google — à régénérer si exposé    |
| `DATABASE_URL`         | URL connexion PostgreSQL                 |

> `.env` est gitignorée. Utiliser `.env.example` comme référence pour l'équipe.

---

# 16. Livrables

- [x] Cahier des charges
- [x] README avec guide de démarrage
- [x] Code source (Symfony 7)
- [x] Migrations Doctrine
- [x] Fixtures de démonstration
- [x] API REST documentée
- [x] Templates e-mails
- [x] Interface responsive (mobile-first)
- [ ] Documentation API (Swagger/OpenAPI) — à venir
- [ ] Réinitialisation de mot de passe — à venir
- [ ] Déploiement VPS — à venir
