# Projet SecureVault - Guide de Gestion

Bienvenue dans le projet **SecureVault**. Ce guide fournit des instructions sur la gestion de l'infrastructure du projet à l'aide de Docker et du `Makefile` fourni.

## Fonctionnalités

- Gestion de coffres-forts chiffrés (AES-256-GCM)
- Entrées de mots de passe avec titre, identifiant, URL, notes et favoris
- Partage de coffres avec permissions granulaires (VIEW / EDIT / DELETE)
- Authentification JWT pour l'API REST
- Vérification d'e-mail obligatoire à l'inscription
- Authentification à deux facteurs par e-mail (2FA, optionnelle par compte)
- Tableau de bord admin (EasyAdmin) à `/admin` — CRUD complet sur toutes les entités
- Journal d'activité, alertes de sécurité, notifications en temps réel
- Suivi des tentatives de connexion

## Prérequis

Avant de commencer, assurez-vous d'avoir les éléments suivants installés sur votre machine :
- [Docker](https://docs.docker.com/get-docker/)
- [Docker Compose](https://docs.docker.com/compose/install/)
- [Make](https://www.gnu.org/software/make/)

## ⚡ Démarrage Rapide (Installation)

Si vous venez de cloner le projet, exécutez ces commandes dans l'ordre pour tout mettre en place :

1.  **Démarrer l'infrastructure :**
    ```bash
    make up
    ```

2.  **Installer les dépendances PHP :**
    ```bash
    make composer-install
    ```

3.  **Préparer la base de données (Migrations) :**
    ```bash
    make migrate
    ```

4.  **Générer les clés JWT :**
    ```bash
    make shell
    php bin/console lexik:jwt:generate-keypair
    ```
    Les clés sont créées dans `config/jwt/` (gitignorées — à faire une seule fois par environnement).

5.  **Configurer les variables d'environnement :**
    Créez `.env.local` à la racine et remplissez les valeurs requises (voir section [Variables d'environnement](#variables-denvironnement)).

Une fois ces étapes terminées, le serveur est accessible sur [http://localhost](http://localhost).

##  Mise en route

Le serveur web tourne sous **FrankenPHP**. Il démarre automatiquement dès que vous lancez `make up`.

- **Serveur Web :** Accessible sur le port `80` (HTTP) et `443` (HTTPS).
- **Accès local :** [http://localhost](http://localhost).

##  Référence des commandes Makefile

Le projet inclut un `Makefile` pour simplifier les tâches courantes.

| Commande | Description |
| :--- | :--- |
| `make help` | Affiche la liste des commandes disponibles. |
| `make build` | Construit les images Docker à partir de zéro. |
| `make up` | Démarre les conteneurs (Serveur + DB) en arrière-plan. |
| `make down` | Arrête et supprime les conteneurs du projet. |
| `make restart` | Redémarre tous les conteneurs. |
| `make logs` | Affiche les logs en temps réel. |
| `make ps` | Liste tous les conteneurs en cours d'exécution. |
| `make shell` | Ouvre un shell interactif dans le conteneur `app`. |
| `make db-shell` | Ouvre un shell psql dans le conteneur `database`. |
| `make migrate` | Exécute les migrations de base de données. |
| `make composer-install` | Installe les dépendances avec Composer. |
| `make cc` | Vide le cache de Symfony. |
| **Génération de Code** | |
| `make make-migration` | Génère une nouvelle classe de migration. |
| `make make-entity` | Crée ou modifie une entité Doctrine. |
| `make make-command` | Crée une nouvelle commande Symfony. |
| `make fixtures` | Charge les données de test (fixtures) en base. |

##  Configuration de la base de données

Le projet utilise **PostgreSQL 16**.
- **Utilisateur :** `app`
- **Mot de passe :** `!ChangeMe!`
- **Base de données :** `app`
- **Hôte :** `database` (interne Docker) ou `localhost` (externe).

## Variables d'environnement

Toutes les variables secrètes doivent être définies dans `.env.local` (jamais commité).

| Variable | Obligatoire | Description |
| :--- | :---: | :--- |
| `VAULT_ENCRYPTION_KEY` | Oui | Clé AES-256 pour chiffrer/déchiffrer les mots de passe en base |
| `MAILER_DSN` | Oui | DSN SMTP pour l'envoi d'e-mails (vérification d'e-mail, 2FA) |
| `JWT_SECRET_KEY` | Oui | Chemin vers la clé privée RSA (`%kernel.project_dir%/config/jwt/private.pem`) |
| `JWT_PUBLIC_KEY` | Oui | Chemin vers la clé publique RSA (`%kernel.project_dir%/config/jwt/public.pem`) |
| `JWT_PASSPHRASE` | Oui | Passphrase utilisée lors de la génération des clés JWT |

### `VAULT_ENCRYPTION_KEY`

**Générer une clé sécurisée** (à faire une seule fois à l'installation) :

```bash
# Option 1 — OpenSSL (recommandée)
openssl rand -base64 32

# Option 2 — PHP
php -r "echo base64_encode(random_bytes(32));"
```

### Clés JWT

```bash
# Via la commande Symfony (recommandée)
php bin/console lexik:jwt:generate-keypair

# Ou manuellement avec OpenSSL
mkdir -p config/jwt
openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

> `config/jwt/*.pem` est gitignorée — les clés doivent être générées localement sur chaque environnement.

## API REST

L'API REST est préfixée `/api/v1/`. Toutes les routes (sauf le login) requièrent un token JWT en header :

```
Authorization: Bearer <token>
```

### Authentification

```
POST /api/v1/auth/login
Content-Type: application/json

{ "email": "user@example.com", "password": "motdepasse" }
```

Retourne `{ "token": "..." }`.

### Coffres (Vaults)

| Méthode | Route | Description |
| :--- | :--- | :--- |
| `GET` | `/api/v1/vaults` | Liste des coffres de l'utilisateur |
| `GET` | `/api/v1/vaults/{id}` | Détail d'un coffre |
| `POST` | `/api/v1/vaults` | Créer un coffre (`name` requis, `description` optionnel) |
| `PATCH` | `/api/v1/vaults/{id}` | Modifier (`name`, `description`, `archived`) |
| `DELETE` | `/api/v1/vaults/{id}` | Supprimer un coffre |

### Entrées de mots de passe

| Méthode | Route | Description |
| :--- | :--- | :--- |
| `GET` | `/api/v1/vaults/{vaultId}/passwords` | Liste des entrées (sans mot de passe déchiffré) |
| `GET` | `/api/v1/vaults/{vaultId}/passwords/{id}` | Détail avec mot de passe déchiffré |
| `POST` | `/api/v1/vaults/{vaultId}/passwords` | Créer (`title` et `password` requis) |
| `PATCH` | `/api/v1/vaults/{vaultId}/passwords/{id}` | Modifier (`title`, `username`, `url`, `notes`, `favorite`, `password`) |
| `DELETE` | `/api/v1/vaults/{vaultId}/passwords/{id}` | Supprimer |

## Tests

```bash
# Entrer dans le conteneur
make shell

# Tests unitaires et fonctionnels (WebTestCase)
php bin/phpunit

# Tests d'un répertoire spécifique
php bin/phpunit tests/Controller/
php bin/phpunit tests/Service/

# Tests E2E via Panther (navigateur headless — nécessite le serveur démarré)
php bin/phpunit tests/E2E/
```

Les tests fonctionnels utilisent la base `app_test` (configurée dans `.env.test`). Les tests E2E utilisent l'environnement `panther`.

##  Structure du projet

```
src/
├── Controller/
│   ├── Api/            # API REST stateless (JWT)
│   ├── Admin/          # Tableau de bord EasyAdmin (CRUD)
│   └── *.php           # Contrôleurs web (dashboard, vaults, profil, 2FA…)
├── Entity/             # 15 entités Doctrine (User, Vault, PasswordEntry…)
├── Service/            # Chiffrement, 2FA, alertes, notifications, activité
├── EventSubscriber/    # Vérification e-mail, blocage 2FA, notifications login
└── Security/           # VaultVoter (VIEW / EDIT / DELETE / SHARE)
templates/
├── emails/             # Templates Twig pour e-mails transactionnels
├── security/           # Pages login, vérification 2FA
└── vault/, profile/, alerts/, …
tests/
├── Controller/         # Tests fonctionnels WebTestCase
├── E2E/                # Tests Panther (navigateur headless)
└── Service/            # Tests unitaires
migrations/             # Migrations Doctrine
config/jwt/             # Clés RSA (gitignorées)
```

**Infrastructure :**
- `Dockerfile` : Basé sur **FrankenPHP** pour un serveur web performant tout-en-un.
- `compose.yaml` : Configuration des services (App, DB).
- `Makefile` : Raccourcis pour les commandes courantes.
