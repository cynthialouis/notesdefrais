.DEFAULT_GOAL := help
.PHONY: help install dev stop test

help: ## Affiche cette aide
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  make %-8s %s\n", $$1, $$2}'

install: ## Installe les dépendances des deux projets
	cd backend && composer install
	npm --prefix frontend install

dev: ## Lance l'API en arrière-plan puis le front (Ctrl+C arrête le front, make stop arrête l'API)
	cd backend && symfony serve -d --no-tls
	npm --prefix frontend run dev

stop: ## Arrête l'API
	cd backend && symfony server:stop

test: ## Lance les tests des deux projets
	cd backend && php bin/phpunit
	@echo "Front : pas encore de tests (Vitest à venir)"
