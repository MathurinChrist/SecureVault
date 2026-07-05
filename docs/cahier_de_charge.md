# SPÉCIFICATION FONCTIONNELLE
# SecureVault – Gestionnaire de mots de passe sécurisé

---

# 1. Présentation du projet

## 1.1 Contexte

La multiplication des services numériques impose aux utilisateurs de gérer un nombre croissant d'identifiants et de mots de passe. Cette situation conduit souvent à l'utilisation de mots de passe faibles, à leur réutilisation, ou à un stockage non sécurisé.

**SecureVault** est une application web permettant de stocker, organiser, partager et sécuriser des mots de passe dans des coffres numériques chiffrés. Elle s'inspire de Dashlane, Bitwarden et 1Password.

## 1.2 Objectifs

- Gestion sécurisée des mots de passe (AES-256-GCM + clé PBKDF2 par utilisateur)
- Organisation par coffres thématiques
- Partage sécurisé avec gestion des permissions
- Détection des risques (mots de passe faibles, anciens)
- Authentification moderne (email + Google OAuth2 + 2FA)
- Administration complète via EasyAdmin, reskinné aux couleurs du site
- Page de contact avec stockage en base et notification par e-mail
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
- Envoyer un message via le formulaire de contact (`/contact`)

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
- Accéder à EasyAdmin (`/easyadmin`), reskinné aux couleurs du site
- Consulter les statistiques globales (utilisateurs, coffres, connexions, messages)
- Consulter la liste des utilisateurs inscrits
- Lire et gérer les messages de contact (lecture, marquage lu/non lu)
- Consulter le journal d'activité récente
- Gérer en CRUD complet : utilisateurs, rôles, coffres, alertes, tentatives de connexion, journaux d'activité, messages de contact

---

# 3. Entités du système

Le système contient **17 entités métier**.

## 3.1 User

Représente un utilisateur enregistré.

| Champ              | Type      | Description                                              |
| :----------------- | :-------- | :------------------------------------------------------- |
| `id`               | integer   | Identifiant auto-incrémenté                              |
| `email`            | string    | Adresse e-mail (unique)                                  |
| `password`         | string    | Hash Argon2id (vide pour comptes Google)                 |
| `firstName`        | string    | Prénom                                                   |
| `lastName`         | string    | Nom                                                      |
| `isActive`         | boolean   | Compte actif                                             |
| `emailVerified`    | boolean   | E-mail vérifié (automatique via Google)                  |
| `profileImage`     | string    | Chemin vers l'image de profil (nullable)                 |
| `encryptionKey`    | string    | **Salt PBKDF2** pour dériver la clé de chiffrement       |
| `is2faEnabled`     | boolean   | 2FA activée                                              |
| `twoFactorSecret`  | string    | Secret 2FA (nullable)                                    |
| `googleId`         | string    | Identifiant Google OAuth2 (nullable, unique)             |
| `createdAt`        | datetime  | Date de création                                         |
| `updatedAt`        | datetime  | Date de dernière modification (nullable)                 |

> `encryptionKey` sert de salt pour PBKDF2. La clé réelle est dérivée à la connexion et stockée uniquement en session — jamais en base de données.

**Relations :** ManyToMany Role · OneToMany Vault · OneToMany BaseNotification (STI — Alert et Notification) · OneToMany ActivityLog · OneToMany LoginAttempt · OneToMany UserSession

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

| Champ               | Type     | Description                                         |
| :------------------ | :------- | :-------------------------------------------------- |
| `id`                | int      |                                                     |
| `title`             | string   |                                                     |
| `username`          | string   |                                                     |
| `encryptedPassword` | string   | Chiffré AES-256-GCM                                 |
| `keyVersion`        | int      | `0` = ancienne clé partagée · `1` = clé PBKDF2 user |
| `url`               | string   |                                                     |
| `notes`             | text     |                                                     |
| `favorite`          | boolean  |                                                     |
| `createdAt`         | datetime |                                                     |
| `updatedAt`         | datetime |                                                     |

**Relations :** ManyToOne Vault · ManyToOne User · ManyToMany Category · ManyToMany Tag · OneToMany PasswordHistory

---

## 3.8 ContactMessage

Message envoyé via le formulaire de contact public.

| Champ       | Type     | Description                          |
| :---------- | :------- | :----------------------------------- |
| `id`        | int      |                                      |
| `name`      | string   | Nom de l'expéditeur                  |
| `email`     | string   | E-mail de l'expéditeur               |
| `subject`   | string   | Sujet sélectionné                    |
| `message`   | text     | Contenu du message                   |
| `isRead`    | boolean  | Lu par l'admin (défaut : false)      |
| `createdAt` | datetime | Date de réception                    |

---

## 3.9 Category

Organisation des mots de passe (Travail, Banque, Personnel…).

**Relations :** ManyToMany PasswordEntry

---

## 3.10 Tag

Étiquettes libres (Important, Urgent, Personnel…).

**Relations :** ManyToMany PasswordEntry · ManyToMany Vault

---

## 3.11 BaseNotification *(entité parente — STI)*

Entité abstraite parente de `Alert` et `Notification`. Implémente l'**héritage Single Table Inheritance (STI)** Doctrine : une seule table `base_notification` avec une colonne discriminante `discr`.

| Champ       | Type     | Description                    |
| :---------- | :------- | :----------------------------- |
| `id`        | int      |                                |
| `discr`     | string   | `alert` ou `notification`      |
| `title`     | string   |                                |
| `type`      | string   | info / warning / danger / …    |
| `isRead`    | boolean  |                                |
| `createdAt` | datetime |                                |

**Relations :** ManyToOne User

---

## 3.12 Alert *(extends BaseNotification)*

Alerte de sécurité générée par le système.

Champs supplémentaires (dans la table `base_notification`) :

| Champ         | Type   |
| :------------ | :----- |
| `description` | text   |
| `category`    | string |

---

## 3.13 Notification *(extends BaseNotification)*

Notification interne de l'application.

Champs supplémentaires :

| Champ    | Type     |
| :------- | :------- |
| `message`| text     |
| `isSent` | boolean  |
| `sentAt` | datetime |

---

## 3.14 ActivityLog

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

## 3.15 LoginAttempt

Historique des tentatives de connexion.

| Champ       | Type     |
| :---------- | :------- |
| `id`        | int      |
| `ipAddress` | string   |
| `success`   | boolean  |
| `createdAt` | datetime |

**Relations :** ManyToOne User

---

## 3.16 UserSession

Sessions actives de l'utilisateur.

**Relations :** ManyToOne User

---

## 3.17 PasswordHistory

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
| Réinitialisation de mot de passe oublié | ✅    |
| Gestion du profil (nom, photo, mdp)    | ✅     |
| Déconnexion                            | ✅     |

## Gestion des coffres

| Fonctionnalité             | Statut |
| :------------------------- | :----: |
| Création                   | ✅     |
| Modification               | ✅     |
| Archivage                  | ✅     |
| Suppression                | ✅     |
| Partage (READ/WRITE/ADMIN) | ✅     |
| Accepter / refuser partage | ✅     |
| Révoquer un partage        | ✅     |

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

## Contact

| Fonctionnalité                            | Statut |
| :---------------------------------------- | :----: |
| Formulaire public `/contact`              | ✅     |
| Validation (CSRF, champs, email)          | ✅     |
| Stockage du message en base de données    | ✅     |
| E-mail de notification à l'admin          | ✅     |
| E-mail de confirmation à l'expéditeur     | ✅     |
| Lien "Contact" dans la nav et le footer   | ✅     |

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

| Fonctionnalité                                        | Statut |
| :---------------------------------------------------- | :----: |
| Score de sécurité sur 100                             | ✅     |
| Détection mots de passe faibles                       | ✅     |
| Détection mots de passe anciens (6m)                  | ✅     |
| Centre d'alertes                                      | ✅     |
| Suivi des connexions suspectes                        | ✅     |
| Vérification fuite HaveIBeenPwned (k-anonymity)       | ✅     |
| Commande CLI `securevault:check-leaked-passwords`     | ✅     |

## Tableau de bord

- Nombre de coffres actifs
- Nombre de mots de passe
- Nombre d'alertes non lues
- Score de sécurité (jauge circulaire animée GSAP)
- Liste des derniers mots de passe avec déchiffrement à la demande
- Panneau d'alertes actives

---

# 5. Interface utilisateur

## Design system

Inspiré de **Dashlane** — sobre, humain, sans surcharge visuelle.

- **Police :** Manrope (Google Fonts)
- **Palette :**
  - `#142F32` — teal foncé (marque, sidebar)
  - `#2f7d5b` — vert accent (logo, liens)
  - `#E3FFCC` — lime pale (hover actif, badges)
  - `#181d22` — encre (texte principal)
  - `#6a7480` — encre atténuée (labels, sous-titres)
  - `#e9ecef` — canvas (fond page)
  - `#e0e4e8` — bordures
  - `#ffffff` — surface (cartes)
- **Logo :** cercle pointillé SVG avec point vert central
- **Framework CSS :** Tailwind CSS (CDN) avec palette nommée étendue
- **Animations :** GSAP 3.12 — entrées staggerées, jauge animée, icônes flottantes
- **Filtres Twig personnalisés** (`NotificationExtension`) :
  - `time_ago` — convertit une date en texte lisible ("il y a 3 min", "il y a 2 j")
  - `password_strength` — retourne la force d'un mot de passe en clair ("Fort", "Très faible"…)

## Responsive / Mobile-first

- Sidebar off-canvas avec bouton hamburger (mobile)
- Grilles adaptatives (`grid-cols-1 → xl:grid-cols-3`)
- Formulaires et tableaux adaptés à tous les écrans

## Pages principales

| Page                  | Description                                                        |
| :-------------------- | :----------------------------------------------------------------- |
| Home `/`              | Hero, générateur, fonctionnalités, sécurité, tarifs               |
| Login `/login`        | Fond canvas, icônes flottantes animées, connexion Google           |
| Register `/register`  | Même style, barres de force du mot de passe                        |
| Contact `/contact`    | Formulaire centré, icônes flottantes, sujets prédéfinis, trust row |
| 2FA `/2fa/verify`     | Page autonome, saisie du code à 6 chiffres                         |
| Dashboard `/dashboard`| Stats, jauge GSAP, liste mots de passe, alertes                   |

---

# 6. Sécurité

## Authentification

| Mécanisme               | Détails                                             |
| :---------------------- | :-------------------------------------------------- |
| Email + mot de passe    | Symfony Security + form_login                       |
| Google OAuth2           | KnpU OAuth2 Client Bundle + league/oauth2-google    |
| Double authentification | Code 6 chiffres par e-mail, TTL 10 min              |
| JWT (API)               | LexikJWTAuthenticationBundle, clés RSA              |

## Chiffrement des mots de passe (architecture per-user)

| Étape                  | Détail                                                                          |
| :--------------------- | :------------------------------------------------------------------------------ |
| Salt                   | 64 caractères hex aléatoires, générés à l'inscription, stockés sur `User`       |
| Dérivation de clé      | `PBKDF2(SHA-256, mot_de_passe_login, salt, 100 000 itérations, 32 octets)`      |
| Stockage de la clé     | **Session uniquement** — jamais en base de données                              |
| Chiffrement            | AES-256-GCM (IV aléatoire + tag d'authentification)                            |
| Migration transparente | À la prochaine connexion, les entrées `keyVersion=0` passent en `keyVersion=1` |

> Conséquence : même un administrateur avec accès complet à la base de données et au fichier `.env` **ne peut pas déchiffrer** les mots de passe des utilisateurs sans connaître leur mot de passe en clair.

## Mots de passe utilisateurs

Hashés avec **Argon2id** via Symfony Security.

## Contrôle d'accès

- **Rôles :** `ROLE_USER`, `ROLE_MANAGER`, `ROLE_ADMIN`
- **VaultVoter :** `VIEW` / `EDIT` / `DELETE` / `SHARE` — basé sur la propriété ou le partage accepté
- **Protection CSRF :** tous les formulaires (Symfony + token manuel pour contact)
- **2FA :** bloquée pour les logins Google (identité prouvée par Google)

---

# 7. API REST

**Préfixe :** `/api/v1/` · **Auth :** JWT Bearer Token

| Groupe        | Endpoints                                 |
| :------------ | :---------------------------------------- |
| Auth          | `POST /api/v1/auth/login`                 |
| Coffres       | CRUD `/api/v1/vaults`                     |
| Mots de passe | CRUD `/api/v1/vaults/{vaultId}/passwords` |

## Sérialisation

Les réponses JSON utilisent le **Symfony Serializer** avec des groupes de normalisation :

| Entité          | Groupe lecture   | Groupe écriture   |
| :-------------- | :--------------- | :---------------- |
| `Vault`         | `vault:read`     | `vault:write`     |
| `PasswordEntry` | `password:read`  | `password:write`  |

Les champs sensibles (`encryptedPassword`, `keyVersion`, relations Doctrine circulaires) sont **exclus** des groupes de sérialisation.

---

# 8. E-mails transactionnels

Envoyés via **Symfony Mailer** · Interceptés par **Mailpit** en développement (`http://localhost:8025`).

| E-mail                        | Déclencheur                              |
| :---------------------------- | :--------------------------------------- |
| Confirmation d'inscription    | Après inscription par e-mail             |
| Code 2FA                      | À chaque connexion avec 2FA activée      |
| Notification de contact       | À la réception d'un message `/contact`   |
| Confirmation de contact       | Envoyée à l'expéditeur du message        |
| Réinitialisation de mot de passe | Après demande via `/reset-password`   |

---

# 9. Services

| Service                    | Rôle                                                                          |
| :------------------------- | :---------------------------------------------------------------------------- |
| `EncryptionService`        | Chiffrement / déchiffrement AES-256-GCM                                       |
| `VaultKeyService`          | Dérivation PBKDF2, stockage/lecture de la clé en session                      |
| `PwnedPasswordService`     | Vérification fuite via API HaveIBeenPwned (HttpClient, k-anonymity SHA-1)     |
| `TwoFactorService`         | Génération, envoi et vérification des codes 2FA                               |
| `AlertService`             | Création et gestion des alertes de sécurité                                   |
| `NotificationService`      | Création et envoi des notifications internes                                  |
| `ActivityLogService`       | Journalisation des actions utilisateur                                        |
| `LoginAttemptService`      | Suivi des tentatives de connexion                                             |
| `PasswordGeneratorService` | Génération de mots de passe avec score de force                               |
| `EmailVerificationService` | Envoi et vérification du lien de confirmation e-mail                          |
| `FileUploader`             | Upload et gestion des images de profil                                        |

---

# 10. Event Subscribers

| Subscriber                    | Événements écoutés                                                                   |
| :---------------------------- | :----------------------------------------------------------------------------------- |
| `TwoFactorSubscriber`         | `LoginSuccessEvent` (déclenche 2FA) · `KernelEvents::REQUEST` (bloque si 2FA pending) |
| `EmailVerificationSubscriber` | Vérifie le statut e-mail sur chaque requête                                          |
| `LoginSuccessSubscriber`      | `LoginSuccessEvent` — dérive la clé PBKDF2, stocke en session, migre les entrées    |
| `EmailSubscriber`             | `UserRegisteredEvent` — envoie l'e-mail de bienvenue                                |

---

# 11. Sécurité — Authenticators

| Authenticator       | Mécanisme                                              |
| :------------------ | :----------------------------------------------------- |
| `form_login`        | E-mail + mot de passe (Symfony natif)                  |
| `GoogleAuthenticator` | OAuth2 Google — création/liaison/connexion automatique |

---

# 12. Administration

## EasyAdmin (`/easyadmin`)

Interface d'administration unique, reskinnée aux couleurs du site (sidebar teal, accent vert, Manrope). Le tableau de bord affiche les stats (utilisateurs, coffres, connexions échouées, messages non lus), les messages de contact non lus et l'activité récente ; le reste du back-office est du CRUD EasyAdmin standard.

| Section              | Contenu                                         |
| :------------------- | :---------------------------------------------- |
| Utilisateurs         | CRUD complet, rôles, e-mail vérifié             |
| Coffres              | Vue et gestion de tous les coffres              |
| Alertes              | Consultation et suppression                     |
| Messages de contact  | CRUD avec switch lu/non lu                      |
| Journal d'activité   | Historique de toutes les actions                |
| Tentatives connexion | IP, dates, succès/échec                         |

---

# 13. Tests & Qualité

| Type            | Commande               | Couverture                            |
| :-------------- | :--------------------- | :------------------------------------ |
| Unitaires       | `make test-unit`       | Services, Security (VaultVoter)       |
| Fonctionnels    | `make test-functional` | Contrôleurs (WebTestCase)             |
| E2E             | `make test-e2e`        | Navigateur headless (Panther)         |
| Analyse statique| `php vendor/bin/phpstan analyse` | PHPStan niveau 5 + baseline |

## Pipeline CI (GitHub Actions)

Déclenchée à chaque push sur `main`, `develop`, `feat/**` :

| Job              | Description                                          |
| :--------------- | :--------------------------------------------------- |
| `unit-tests`     | Tests unitaires PHP 8.4                              |
| `functional-tests` | Tests fonctionnels avec PostgreSQL 16              |
| `e2e-tests`      | Tests Panther (Chrome headless)                      |
| `lint`           | `lint:twig` + `lint:yaml`                            |
| `phpstan`        | Analyse statique niveau 5 avec warm-up Symfony       |

---

# 14. Infrastructure

| Composant    | Technologie        | Version |
| :----------- | :----------------- | :------ |
| Langage      | PHP                | 8.4     |
| Backend      | Symfony            | 7.x     |
| Serveur      | FrankenPHP         | Latest  |
| Base données | PostgreSQL         | 16      |
| ORM          | Doctrine           | Latest  |
| Auth OAuth2  | KnpU OAuth2 Bundle | 2.x     |
| Auth JWT     | LexikJWT Bundle    | Latest  |
| Administration | EasyAdminBundle  | Latest  |
| CSS          | Tailwind CSS       | CDN     |
| Animations   | GSAP               | 3.12.5  |
| Mailer (dev) | Mailpit            | Latest  |

---

# 15. Variables d'environnement sensibles

| Variable               | Description                                         |
| :--------------------- | :-------------------------------------------------- |
| `VAULT_ENCRYPTION_KEY` | Salt de base pour fallback AES — ne jamais commiter |
| `JWT_PASSPHRASE`       | Passphrase clés RSA                                 |
| `Google_Client_ID`     | OAuth2 Google — Google Cloud Console                |
| `Google_Client_Secret` | OAuth2 Google — à régénérer si exposé               |
| `DATABASE_URL`         | URL connexion PostgreSQL                            |

> `.env` est gitignorée. Utiliser `.env.example` comme référence pour l'équipe.

---

# 16. Livrables

- [x] Cahier des charges
- [x] README avec guide de démarrage + comptes de test
- [x] Code source (Symfony 7)
- [x] Migrations Doctrine
- [x] Fixtures réalistes (FakerPHP — 10 users, 15 coffres, ~48 mots de passe)
- [x] API REST sérialisée avec Symfony Serializer + groupes (`vault:read`, `password:read`)
- [x] Templates e-mails (bienvenue, 2FA, contact)
- [x] Interface responsive (mobile-first)
- [x] Page de contact avec stockage BD + e-mails
- [x] Administration EasyAdmin reskinnée aux couleurs du site
- [x] Chiffrement per-user (PBKDF2 + migration automatique)
- [x] Héritage d'entités Doctrine (STI — `BaseNotification` → `Alert` + `Notification`)
- [x] Form Events (PRE_SET_DATA + PRE_SUBMIT dans `PasswordEntryType`)
- [x] Consommation API externe (HaveIBeenPwned via `HttpClient`)
- [x] Commande CLI (`securevault:check-leaked-passwords`)
- [x] Filtres Twig personnalisés (`time_ago`, `password_strength`)
- [x] Pipeline CI GitHub Actions (unit, functional, E2E, lint, PHPStan niveau 5)
- [x] Réinitialisation de mot de passe (`/reset-password`, SymfonyCasts ResetPasswordBundle)
- [ ] Documentation API (Swagger/OpenAPI) — à venir
- [ ] Déploiement VPS — à venir
