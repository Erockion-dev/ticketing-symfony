.DEFAULT_GOAL := help
NODE = docker compose exec node
PHP  = docker compose exec php

help: ## Liste les commandes
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-12s %s\n", $$1, $$2}'

front: ## Installe les deps et build le JS/CSS, puis s'arrête
	docker compose run --rm node sh -c "pnpm install && pnpm build"

watch: ## Build en continu (Ctrl+C pour arrêter)
	docker compose run --rm node sh -c "pnpm install && pnpm watch"

up: ## Démarre les containers
	docker compose up -d --build

down: ## Arrête les containers
	docker compose down

install: ## Installe les dépendances (composer + pnpm)
	$(PHP) composer install
	$(MAKE) front

php:
	docker exec -it symf_ticket_php /bin/bash

console: ## Ex : make console c="cache:clear"
	$(PHP) php bin/console $(c)

fixtures: ## Charge les fixtures dans la base de test
	$(PHP) php bin/console doctrine:fixtures:load --env=test --no-interaction

tests: fixtures ## Charge les fixtures puis lance les tests
	$(PHP) vendor/bin/phpunit tests/