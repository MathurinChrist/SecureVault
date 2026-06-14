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

## ⚡ Lancer l'application en local (Docker)

### 1. Démarrer les conteneurs

```bash
make up
```

Cela démarre trois services :

| Service | Rôle | Accès |
| :--- | :--- | :--- |
| `app` | Application Symfony (FrankenPHP) | http://localhost:8080 |
| `database` | PostgreSQL 16 | `localhost:5432` |
| `mailer` | Mailpit — capture les e-mails sortants | http://localhost:8025 |

### 2. Installer les dépendances PHP

```bash
make composer-install
```

### 3. Préparer la base de données

```bash
make migrate
```

Pour repartir d'une base vierge avec les données de démonstration :

```bash
make db-setup
```

### 4. Générer les clés JWT

```bash
make jwt-keys
```

Les clés RSA sont créées dans `config/jwt/` et persistées dans un volume Docker (`jwt_keys`). Elles sont générées une seule fois — à relancer uniquement si vous supprimez le volume.

> `config/jwt/*.pem` est gitignorée. Ne jamais commiter ces fichiers.

### 5. Configurer les variables d'environnement

Créez `.env.local` à la racine pour surcharger les valeurs par défaut :

```dotenv
# Clé AES-256 pour le chiffrement des mots de passe
VAULT_ENCRYPTION_KEY=<générer avec : openssl rand -base64 32>

# JWT (déjà configuré via le volume Docker, modifier si nécessaire)
JWT_PASSPHRASE=votre-passphrase

# SMTP — par défaut Mailpit (dev). Remplacer par votre SMTP en production.
# MAILER_DSN=smtp://user:pass@smtp.example.com:587
```

L'application est maintenant accessible sur **http://localhost:8080**.

### Accès aux e-mails (Mailpit)

En développement, tous les e-mails (vérification d'e-mail, codes 2FA) sont interceptés par **Mailpit** et consultables sur :

```
http://localhost:8025
```

Aucun e-mail réel n'est envoyé en mode dev.

---

##  Référence des commandes Makefile

### Docker & infrastructure

| Commande | Description |
| :--- | :--- |
| `make up` | Démarre les conteneurs en arrière-plan |
| `make down` | Arrête et supprime les conteneurs |
| `make restart` | Redémarre tous les conteneurs |
| `make build` | Reconstruit les images Docker (sans cache) |
| `make logs` | Affiche les logs de tous les conteneurs |
| `make ps` | Liste les conteneurs en cours d'exécution |
| `make shell` | Ouvre un shell dans le conteneur `app` |
| `make db-shell` | Ouvre un shell psql dans le conteneur `database` |

### Application

| Commande | Description |
| :--- | :--- |
| `make composer-install` | Installe les dépendances Composer |
| `make migrate` | Exécute les migrations Doctrine |
| `make db-setup` | Recrée la BDD, migre et charge les fixtures (purge) |
| `make fixtures` | Charge les fixtures sans purger la BDD |
| `make fixtures-append` | Ajoute les fixtures sans purger la BDD |
| `make jwt-keys` | Génère les clés RSA pour JWT |
| `make cc` | Vide le cache Symfony |
| `make messenger-consume` | Lance le worker Messenger |
| `make log_tail` | Suit les logs Symfony en temps réel |

### Génération de code

| Commande | Description |
| :--- | :--- |
| `make make-migration` | Génère une nouvelle classe de migration |
| `make make-entity` | Crée ou modifie une entité Doctrine |
| `make make-command` | Crée une nouvelle commande Symfony |

### Tests

| Commande | Description |
| :--- | :--- |
| `make test` | Lance tous les tests (unit + functional + E2E) |
| `make test-unit` | Tests unitaires (`tests/Service/`, `tests/Security/`) |
| `make test-functional` | Tests fonctionnels WebTestCase (`tests/Controller/`) |
| `make test-e2e` | Tests E2E Panther — navigateur headless (`tests/E2E/`) |
| `make test-db-setup` | Prépare la BDD de test (utilisé automatiquement par les cibles test-*) |
| `make fixtures-test` | Charge les fixtures dans la BDD de test |

> `make test-functional` et `make test-e2e` appellent automatiquement `make test-db-setup` avant de s'exécuter.

##  Configuration de la base de données

Le projet utilise **PostgreSQL 16**.
- **Utilisateur :** `app`
- **Mot de passe :** `!ChangeMe!`
- **Base de données :** `app`
- **Hôte :** `database` (interne Docker) ou `localhost:5432` (externe).

## Variables d'environnement

Toutes les variables secrètes doivent être définies dans `.env.local` (jamais commité).

| Variable | Obligatoire | Description |
| :--- | :---: | :--- |
| `VAULT_ENCRYPTION_KEY` | Oui | Clé AES-256 pour chiffrer/déchiffrer les mots de passe en base |
| `MAILER_DSN` | Oui | DSN SMTP (`smtp://mailer:1025` par défaut via Mailpit en dev) |
| `JWT_SECRET_KEY` | Oui | Chemin vers la clé privée RSA (géré automatiquement via volume Docker) |
| `JWT_PUBLIC_KEY` | Oui | Chemin vers la clé publique RSA (géré automatiquement via volume Docker) |
| `JWT_PASSPHRASE` | Oui | Passphrase utilisée lors de la génération des clés JWT |

### Générer `VAULT_ENCRYPTION_KEY`

```bash
# Option 1 — OpenSSL (recommandée)
openssl rand -base64 32

# Option 2 — PHP
php -r "echo base64_encode(random_bytes(32));"
```

## Utilisation de l'interface web

L'application est accessible sur **http://localhost:8080** après `make up`.

### Compte utilisateur

| Action | URL |
| :--- | :--- |
| Page d'accueil | `/` |
| Inscription | `/register` |
| Connexion | `/login` |
| Déconnexion | `/logout` |
| Profil (modifier nom, e-mail, mot de passe) | `/profile` |

Après l'inscription, un e-mail de vérification est envoyé. En développement, il est consultable sur **http://localhost:8025** (Mailpit). L'accès aux pages protégées est bloqué jusqu'à la vérification.

### Tableau de bord

`/dashboard` — vue synthétique : nombre de coffres, d'entrées, dernières activités et notifications non lues.

### Coffres-forts

`/vaults` — liste de tous les coffres de l'utilisateur.

| Action | Comment |
| :--- | :--- |
| Créer un coffre | Formulaire intégré dans la page `/vaults` |
| Voir le contenu | Clic sur un coffre → `/vaults/{id}` |
| Renommer | Formulaire sur la page de détail |
| Archiver / désarchiver | Bouton sur la page de détail |
| Supprimer | Bouton sur la page de détail (suppression définitive) |

### Mots de passe

Chaque coffre contient des entrées de mots de passe. Les mots de passe sont chiffrés en AES-256-GCM — ils ne sont jamais stockés en clair.

| Action | Comment |
| :--- | :--- |
| Ajouter une entrée | Formulaire sur la page du coffre (`/vaults/{id}`) |
| Afficher le mot de passe | Bouton "Révéler" → `/passwords/{id}/decrypt` |
| Modifier une entrée | Formulaire inline (`/password/{id}/edit`) |
| Supprimer une entrée | Bouton de suppression sur la page du coffre |
| Vue globale de toutes les entrées | `/passwords` |

### Partage de coffres

Un coffre peut être partagé avec d'autres utilisateurs inscrits.

| Action | Comment |
| :--- | :--- |
| Partager un coffre | Page du coffre → onglet partages → `/vaults/{id}/shares` |
| Envoyer une invitation | Formulaire avec e-mail du destinataire et niveau de permission (VIEW / EDIT / DELETE) |
| Accepter / refuser une invitation | `/shares` — liste des invitations reçues |
| Révoquer un partage | Bouton "Révoquer" sur la page de partages du coffre |

### Alertes de sécurité

`/alerts` — liste des événements de sécurité (connexions suspectes, tentatives échouées, etc.).

- Marquer une alerte comme lue
- Ignorer une alerte
- Activer / désactiver la 2FA depuis cette page (section en bas de page)

### Notifications

`/notifications` — historique des notifications (nouveaux partages, connexions, activités).

- Marquer comme lue individuellement ou en masse
- Ignorer une notification

### Authentification à deux facteurs (2FA)

La 2FA s'active depuis `/alerts` (section "Conseil de sécurité"). Une fois activée :

1. À chaque connexion, un code à 6 chiffres est envoyé par e-mail
2. Le code est valable 10 minutes
3. L'accès à toutes les pages est bloqué jusqu'à la saisie du code sur `/2fa/verify`
4. Un bouton "Renvoyer un code" est disponible si l'e-mail n'arrive pas

En développement, le code apparaît dans Mailpit sur **http://localhost:8025**.

### Administration

`/admin` — tableau de bord réservé aux utilisateurs avec le rôle `ROLE_ADMIN`.

| Section | Contenu |
| :--- | :--- |
| Utilisateurs | CRUD complet, rôles, statut e-mail vérifié |
| Coffres | Vue et gestion de tous les coffres |
| Alertes | Consultation et suppression des alertes |
| Journal d'activité | Historique de toutes les actions |
| Tentatives de connexion | Adresses IP, dates, succès/échec |

---

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
# Tous les tests
make test

# Par catégorie
make test-unit        # Services et voters
make test-functional  # Contrôleurs (WebTestCase)
make test-e2e         # Navigateur headless (Panther)
```

Les tests fonctionnels et E2E utilisent la base `app_test`. `make test-functional` et `make test-e2e` configurent cette base automatiquement avant de s'exécuter.

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
config/jwt/             # Clés RSA (gitignorées, générées par make jwt-keys)
```

**Infrastructure :**
- `Dockerfile` : Basé sur **FrankenPHP** pour un serveur web performant tout-en-un.
- `compose.yaml` : Trois services — `app` (port 8080), `database` (port 5432), `mailer` Mailpit (port 8025).
- `Makefile` : Raccourcis pour toutes les commandes courantes.
