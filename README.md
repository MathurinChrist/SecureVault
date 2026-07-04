# SecureVault — Gestionnaire de mots de passe sécurisé

Application web de gestion de mots de passe développée avec **Symfony 7**, **PostgreSQL 16** et **FrankenPHP**. Interface responsive, chiffrement AES-256-GCM, authentification Google OAuth2 et API REST JWT.

---

## Fonctionnalités

- Gestion de coffres-forts chiffrés (AES-256-GCM)
- Générateur de mots de passe intégré (home + dashboard)
- Recherche dans les coffres (titre, identifiant, URL, nom du coffre)
- Partage de coffres avec permissions granulaires (READ / WRITE / ADMIN)
- Authentification par e-mail + mot de passe
- **Authentification Google OAuth2** (connexion et inscription)
- Vérification d'e-mail obligatoire à l'inscription
- Double authentification par e-mail (2FA, optionnelle par compte)
- Audit de sécurité (score, mots de passe faibles / anciens)
- Journal d'activité, alertes de sécurité, notifications
- Suivi des tentatives de connexion
- Tableau de bord admin (EasyAdmin) à `/admin`
- API REST v1 (JWT)
- Interface **100% gratuite** — aucun abonnement requis
- Design responsive / mobile-first

---

## Prérequis

- [Docker](https://docs.docker.com/get-docker/)
- [Docker Compose](https://docs.docker.com/compose/install/)
- [Make](https://www.gnu.org/software/make/)

---

## Lancer l'application en local (Docker)

### 1. Démarrer les conteneurs

```bash
make up
```

| Service    | Rôle                                    | Accès                   |
| :--------- | :-------------------------------------- | :---------------------- |
| `app`      | Application Symfony (FrankenPHP)        | http://localhost:8080   |
| `database` | PostgreSQL 16                           | `localhost:5432`        |
| `mailer`   | Mailpit — capture les e-mails sortants  | http://localhost:8025   |

### 2. Installer les dépendances PHP

```bash
make composer-install
```

### 3. Configurer les variables d'environnement

Copiez `.env.example` en `.env.local` et remplissez les valeurs :

```bash
cp .env.example .env.local
```

Variables minimales à définir :

```dotenv
VAULT_ENCRYPTION_KEY=<openssl rand -base64 32>
JWT_PASSPHRASE=votre-passphrase
Google_Client_ID=votre-client-id
Google_Client_Secret=votre-client-secret
```

### 4. Préparer la base de données

```bash
make migrate
```

Pour repartir d'une base vierge avec données de démonstration :

```bash
make db-setup
```

### 5. Générer les clés JWT

```bash
make jwt-keys
```

Les clés RSA sont créées dans `config/jwt/` (gitignorées). À relancer uniquement si vous supprimez le volume.

L'application est accessible sur **http://localhost:8080**.

---

## Variables d'environnement

| Variable               | Obligatoire | Description                                                       |
| :--------------------- | :---------: | :---------------------------------------------------------------- |
| `VAULT_ENCRYPTION_KEY` | Oui         | Clé AES-256 pour chiffrer les mots de passe                      |
| `MAILER_DSN`           | Oui         | DSN SMTP (`smtp://mailer:1025` via Mailpit en dev)                |
| `JWT_SECRET_KEY`       | Oui         | Chemin vers la clé privée RSA (géré via volume Docker)            |
| `JWT_PUBLIC_KEY`       | Oui         | Chemin vers la clé publique RSA (géré via volume Docker)          |
| `JWT_PASSPHRASE`       | Oui         | Passphrase des clés JWT                                           |
| `Google_Client_ID`     | Oui         | Client ID OAuth2 Google (Google Cloud Console)                    |
| `Google_Client_Secret` | Oui         | Client Secret OAuth2 Google                                       |
| `DATABASE_URL`         | Oui         | URL de connexion PostgreSQL                                       |

### Générer `VAULT_ENCRYPTION_KEY`

```bash
openssl rand -base64 32
```

---

## Référence des commandes Makefile

### Docker & infrastructure

| Commande            | Description                                    |
| :------------------ | :--------------------------------------------- |
| `make up`           | Démarre les conteneurs en arrière-plan          |
| `make down`         | Arrête et supprime les conteneurs               |
| `make restart`      | Redémarre tous les conteneurs                  |
| `make build`        | Reconstruit les images Docker (sans cache)     |
| `make logs`         | Affiche les logs de tous les conteneurs        |
| `make ps`           | Liste les conteneurs en cours d'exécution      |
| `make shell`        | Ouvre un shell dans le conteneur `app`         |
| `make db-shell`     | Ouvre un shell psql dans `database`            |

### Application

| Commande                 | Description                                          |
| :----------------------- | :--------------------------------------------------- |
| `make composer-install`  | Installe les dépendances Composer                    |
| `make migrate`           | Exécute les migrations Doctrine                      |
| `make db-setup`          | Recrée la BDD, migre et charge les fixtures          |
| `make fixtures`          | Charge les fixtures sans purger                      |
| `make jwt-keys`          | Génère les clés RSA pour JWT                         |
| `make cc`                | Vide le cache Symfony                                |
| `make log_tail`          | Suit les logs Symfony en temps réel                  |

### Tests

| Commande               | Description                                         |
| :--------------------- | :-------------------------------------------------- |
| `make test`            | Lance tous les tests                                |
| `make test-unit`       | Tests unitaires                                     |
| `make test-functional` | Tests fonctionnels (WebTestCase)                    |
| `make test-e2e`        | Tests E2E (navigateur headless Panther)             |

---

## Authentification Google OAuth2

### Configuration Google Cloud Console

1. Créer un projet sur [console.cloud.google.com](https://console.cloud.google.com)
2. Activer l'API **Google Identity** (OAuth 2.0)
3. Créer un Client OAuth → Type : **Application Web**
4. Ajouter les URIs de redirection autorisés :
   - Dev : `http://localhost:8080/connect/google/callback`
   - Prod : `https://votre-domaine.com/connect/google/callback`
5. Copier `Client ID` et `Client Secret` dans `.env.local`

### Comportement

| Situation                              | Résultat                                                              |
| :------------------------------------- | :-------------------------------------------------------------------- |
| Nouveau compte Google                  | Compte créé automatiquement + connexion + flash "Bienvenue"           |
| Email existant sans Google             | Liaison automatique du compte + connexion + flash informatif          |
| Email déjà lié à Google                | Connexion directe                                                     |
| Clic "S'inscrire" avec compte existant | Connexion automatique + flash "Vous avez déjà un compte"             |

Les utilisateurs Google **ne passent pas par la 2FA** (identité déjà prouvée par Google).

---

## Interface web

### URLs principales

| Page                       | URL                          |
| :------------------------- | :--------------------------- |
| Accueil                    | `/`                          |
| Inscription                | `/register`                  |
| Connexion                  | `/login`                     |
| Connexion Google           | `/connect/google`            |
| Dashboard                  | `/dashboard`                 |
| Coffres                    | `/vaults`                    |
| Tous les mots de passe     | `/passwords`                 |
| Partages                   | `/shares`                    |
| Alertes de sécurité        | `/alerts`                    |
| Notifications              | `/notifications`             |
| Profil                     | `/profile`                   |
| Administration             | `/admin`                     |
| Vérification 2FA           | `/2fa/verify`                |

### Générateur de mots de passe

Disponible sur la **page d'accueil** et dans le **dashboard** (colonne droite). Paramètres : longueur (8–40), majuscules, minuscules, chiffres, symboles. Raccourci ⌘K / Ctrl+K pour focaliser la recherche dans le dashboard.

### E-mails (Mailpit en dev)

Tous les e-mails sortants sont interceptés par Mailpit : http://localhost:8025

- Confirmation d'inscription
- Code 2FA (6 chiffres, valable 10 min)

---

## API REST

Préfixe : `/api/v1/` — toutes les routes (sauf login) requièrent un token JWT :

```
Authorization: Bearer <token>
```

### Authentification

```
POST /api/v1/auth/login
{ "email": "user@example.com", "password": "..." }
→ { "token": "eyJ..." }
```

### Coffres

| Méthode  | Route                  | Description             |
| :------- | :--------------------- | :---------------------- |
| `GET`    | `/api/v1/vaults`       | Liste des coffres       |
| `GET`    | `/api/v1/vaults/{id}`  | Détail d'un coffre      |
| `POST`   | `/api/v1/vaults`       | Créer un coffre         |
| `PATCH`  | `/api/v1/vaults/{id}`  | Modifier un coffre      |
| `DELETE` | `/api/v1/vaults/{id}`  | Supprimer un coffre     |

### Mots de passe

| Méthode  | Route                                       | Description                         |
| :------- | :------------------------------------------ | :---------------------------------- |
| `GET`    | `/api/v1/vaults/{vaultId}/passwords`        | Liste des entrées                   |
| `GET`    | `/api/v1/vaults/{vaultId}/passwords/{id}`   | Détail avec mot de passe déchiffré  |
| `POST`   | `/api/v1/vaults/{vaultId}/passwords`        | Créer une entrée                    |
| `PATCH`  | `/api/v1/vaults/{vaultId}/passwords/{id}`   | Modifier une entrée                 |
| `DELETE` | `/api/v1/vaults/{vaultId}/passwords/{id}`   | Supprimer une entrée                |

---

## Structure du projet

```
src/
├── Controller/
│   ├── Api/               # API REST stateless (JWT)
│   ├── Admin/             # Tableau de bord EasyAdmin
│   ├── GoogleController   # OAuth2 Google (connect + callback)
│   └── *.php              # Contrôleurs web (dashboard, vaults, 2FA…)
├── Entity/                # 15 entités Doctrine (User, Vault, PasswordEntry…)
├── Security/
│   └── GoogleAuthenticator.php  # Authenticator OAuth2 Google
├── Service/               # EncryptionService, TwoFactorService, AlertService…
├── EventSubscriber/       # TwoFactorSubscriber, EmailVerificationSubscriber…
├── Repository/            # Dont PasswordEntryRepository (searchByUser)
└── Command/               # GenerateImagesCommand (Gemini API)
templates/
├── home/                  # Page d'accueil (avec générateur interactif)
├── emails/                # Templates e-mails (thème marque)
├── security/              # Login, 2FA
├── registration/          # Inscription, vérification e-mail
└── dashboard/, vault/, passwords/, alerts/, …
migrations/                # Migrations Doctrine
config/
├── jwt/                   # Clés RSA (gitignorées)
└── packages/
    └── knpu_oauth2_client.yaml  # Config Google OAuth2
```

**Infrastructure :**
- `Dockerfile` : FrankenPHP
- `compose.yaml` : `app` (8080), `database` (5432), `mailer` (8025)
- `Makefile` : raccourcis pour toutes les commandes courantes

---

## Configuration base de données

PostgreSQL 16 — utilisateur `app`, base `app`, port `5432`.
