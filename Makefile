# --- Variables ---
DOCKER_COMPOSE = docker compose
PHP = $(DOCKER_COMPOSE) exec app
SYMFONY = $(PHP) bin/console

# --- Cibles ---
.PHONY: help build up down restart logs ps shell migrate db-shell composer-install make-migration make-entity make-command fixtures messenger-consume

help: ## Affiche ce message d'aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

build: ## Construit les images Docker
	$(DOCKER_COMPOSE) build --pull --no-cache

up: ## Démarre les conteneurs en arrière-plan
	$(DOCKER_COMPOSE) up -d --remove-orphans

down: ## Arrête et supprime les conteneurs
	$(DOCKER_COMPOSE) down --remove-orphans

restart: ## Redémarre les conteneurs
	$(MAKE) down
	$(MAKE) up

logs: ## Affiche les logs de tous les conteneurs
	$(DOCKER_COMPOSE) logs -f

ps: ## Liste les conteneurs en cours d'exécution
	$(DOCKER_COMPOSE) ps

shell: ## Ouvre un shell dans le conteneur app
	$(DOCKER_COMPOSE) exec app sh

migrate: ## Exécute les migrations Doctrine
	$(SYMFONY) doctrine:migrations:migrate --no-interaction

db-shell: ## Accède au shell de la base de données PostgreSQL
	$(DOCKER_COMPOSE) exec database psql -U app -d app

composer-install: ## Installe les dépendances composer
	$(DOCKER_COMPOSE) exec app composer install

cc: ## Vide le cache Symfony
	$(SYMFONY) cache:clear

make-migration: ## Crée une nouvelle migration
	$(SYMFONY) make:migration

make-entity: ## Crée une nouvelle entité
	$(SYMFONY) make:entity

make-command: ## Crée une nouvelle commande Symfony
	$(SYMFONY) make:command

fixtures: ## Charge les fixtures de données
	$(SYMFONY) doctrine:fixtures:load --no-interaction

messenger-consume: ## Lance le worker Messenger pour traiter les messages (emails, etc.)
	$(SYMFONY) messenger:consume async -vv
log_tail:
	docker compose exec app tail -f var/log/dev.log
