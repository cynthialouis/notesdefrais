.DEFAULT_GOAL := help
.PHONY: help install dev stop test test-back api-types db e2e-db

# Mêmes limites d'upload que backend/php.ini (justificatifs de 5 Mo), que PHP CLI ne lit pas
PHPUNIT = php -d upload_max_filesize=6M -d post_max_size=8M bin/phpunit

help: ## Affiche cette aide
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  make %-10s %s\n", $$1, $$2}'

install: ## Installe les dépendances des deux projets
	cd backend && composer install
	npm --prefix frontend install

dev: ## Lance l'API en arrière-plan puis le front (Ctrl+C arrête le front, make stop arrête l'API)
	cd backend && symfony serve -d --no-tls
	npm --prefix frontend run dev

stop: ## Arrête l'API
	cd backend && symfony server:stop

test: test-back ## Lance les tests des deux projets
	npm --prefix frontend run test

test-back: ## Migre la base de test et lance PHPUnit (filtre : make test-back ARGS="--filter Nom")
	cd backend && php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test && $(PHPUNIT) $(ARGS)

api-types: ## Régénère les types TypeScript du front depuis la spec OpenAPI du back
	cd backend && php bin/console nelmio:apidoc:dump > ../frontend/src/api/openapi.json
	npm --prefix frontend run gen:api

db: ## Met la base de dev à jour et recharge les données de démo (les catégories, insérées par migration, sont conservées)
	cd backend && php bin/console doctrine:migrations:migrate --no-interaction
	cd backend && php bin/console doctrine:fixtures:load --no-interaction --purge-exclusions=category

e2e-db: ## Remet la base e2e dans l'état des données de démo (lancé avant chaque test e2e)
	cd backend && php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=e2e
	cd backend && php bin/console doctrine:fixtures:load --no-interaction --purge-exclusions=category --env=e2e
