# Projet SecureVault - Guide de Gestion

Bienvenue dans le projet **SecureVault**. Ce guide fournit des instructions sur la gestion de l'infrastructure du projet à l'aide de Docker et du `Makefile` fourni.

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

### `VAULT_ENCRYPTION_KEY`

Cette clé est utilisée pour chiffrer/déchiffrer les mots de passe stockés en base de données (AES-256-GCM via `EncryptionService`).

**Générer une clé sécurisée** (à faire une seule fois à l'installation) :

```bash
# Option 1 — OpenSSL (recommandée)
openssl rand -base64 32

# Option 2 — PHP
php -r "echo base64_encode(random_bytes(32));"
```

**Ajouter la valeur dans `.env.local`** 

##  Structure du projet

- `Dockerfile` : Basé sur **FrankenPHP** pour un serveur web performant tout-en-un.
- `compose.yaml` : Configuration des services (App, DB).
- `Makefile` : Raccourcis pour les commandes courantes.
