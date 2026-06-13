# ── Variables ─────────────────────────────────────────────────────────────────
DOCKER_COMPOSE  = docker compose
PHP             = $(DOCKER_COMPOSE) exec app
SYMFONY         = $(PHP) bin/console
PHPUNIT         = $(PHP) php vendor/bin/phpunit --configuration phpunit.dist.xml

# ── Cibles principales ────────────────────────────────────────────────────────
.PHONY: help build up down restart logs ps shell \
        db-setup migrate db-shell composer-install cc \
        make-migration make-entity make-command \
        fixtures fixtures-append fixtures-test \
        messenger-consume \
        test test-unit test-functional test-e2e \
        test-db-setup jwt-keys log_tail

help: ## Affiche ce message d'aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

# ── Docker ────────────────────────────────────────────────────────────────────
build: ## Construit les images Docker (sans cache)
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

# ── Application ───────────────────────────────────────────────────────────────
db-setup: ## Crée, migre et charge les fixtures (env dev)
	$(PHP) php bin/console doctrine:database:drop --force --if-exists
	$(PHP) php bin/console doctrine:database:create
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction
	$(PHP) php bin/console doctrine:fixtures:load --no-interaction

migrate: ## Exécute les migrations Doctrine (env dev)
	$(SYMFONY) doctrine:migrations:migrate --no-interaction

db-shell: ## Accède au shell PostgreSQL
	$(DOCKER_COMPOSE) exec database psql -U app -d app

composer-install: ## Installe les dépendances Composer
	$(DOCKER_COMPOSE) exec app composer install

cc: ## Vide le cache Symfony
	$(SYMFONY) cache:clear

make-migration: ## Génère une nouvelle migration
	$(SYMFONY) make:migration

make-entity: ## Génère une nouvelle entité
	$(SYMFONY) make:entity

make-command: ## Génère une nouvelle commande
	$(SYMFONY) make:command

fixtures: ## Charge les fixtures (⚠ purge la BDD dev)
	$(SYMFONY) doctrine:fixtures:load --no-interaction

fixtures-append: ## Charge les fixtures sans purger la BDD dev
	$(SYMFONY) doctrine:fixtures:load --no-interaction --append

fixtures-test: ## Charge les fixtures dans la BDD de test
	$(PHP) php bin/console doctrine:fixtures:load --no-interaction --append --env=test

messenger-consume: ## Lance le worker Messenger
	$(SYMFONY) messenger:consume async -vv

log_tail: ## Suit les logs Symfony en temps réel
	$(DOCKER_COMPOSE) exec app tail -f var/log/dev.log

jwt-keys: ## Génère les clés JWT RSA dans le conteneur app
	$(PHP) sh -c ' \
		mkdir -p config/jwt && \
		openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096 -pass pass:$${JWT_PASSPHRASE:-securevault} && \
		openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:$${JWT_PASSPHRASE:-securevault} && \
		echo "JWT keys generated." \
	'

# ── Tests ─────────────────────────────────────────────────────────────────────
test-db-setup: ## Crée, migre et charge les fixtures dans la BDD de test
	$(PHP) php bin/console doctrine:database:drop --force --if-exists --env=test
	$(PHP) php bin/console doctrine:database:create --env=test
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction --env=test
	$(PHP) php bin/console doctrine:fixtures:load --no-interaction --append --env=test

test-unit: ## Lance les tests unitaires (Service + Security)
	$(PHPUNIT) tests/Service/ tests/Security/

test-functional: test-db-setup ## Lance les tests fonctionnels (Controllers)
	$(PHPUNIT) tests/Controller/

test-e2e: test-db-setup ## Lance les tests E2E Panther (nécessite Chrome dans le conteneur)
	$(PHPUNIT) tests/E2E/

test: test-db-setup ## Lance tous les tests (unit + functional + e2e)
	$(PHPUNIT) tests/Service/ tests/Security/
	$(PHPUNIT) tests/Controller/
	$(PHPUNIT) tests/E2E/
