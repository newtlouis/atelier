# Toutes les commandes tournent dans le conteneur : rien à installer en local
# hormis un moteur de conteneurs.
DC := docker compose
PHP := $(DC) exec -T app

.DEFAULT_GOAL := help
.PHONY: help up down install test qa stan cs deptrac audit shell db-reset

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) \
		| awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Démarre l'application (http://localhost:8080)
	$(DC) up -d --build
	@$(MAKE) --no-print-directory install
	@echo "→ http://localhost:8080"

down: ## Arrête tout et supprime les volumes
	$(DC) down -v

install: ## Installe les dépendances PHP
	$(PHP) composer install --no-interaction

test: ## Lance la suite de tests
	# La configuration de test suffixe la base (atelier_test) : il faut la créer.
	$(PHP) php bin/console --env=test doctrine:database:create --if-not-exists -q
	$(PHP) vendor/bin/phpunit

qa: stan cs deptrac audit ## Lance toutes les vérifications de qualité

stan: ## Analyse statique (PHPStan, niveau 10)
	$(PHP) vendor/bin/phpstan analyse --memory-limit=512M

cs: ## Vérifie le style de code
	$(PHP) vendor/bin/php-cs-fixer check --diff

cs-fix: ## Corrige le style de code
	$(PHP) vendor/bin/php-cs-fixer fix

deptrac: ## Vérifie les dépendances entre domaines
	$(PHP) vendor/bin/deptrac analyse --fail-on-uncovered --report-uncovered

audit: ## Vérifie les vulnérabilités des dépendances
	$(PHP) composer audit

shell: ## Ouvre un shell dans le conteneur applicatif
	$(DC) exec app sh

db-reset: ## Recrée la base de développement
	$(PHP) php bin/console doctrine:database:drop --force --if-exists
	$(PHP) php bin/console doctrine:database:create
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction
