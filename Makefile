.PHONY: help setup up down restart logs test pint pint-test analyse migrate seed fresh serve clean

help: ## Show this help menu
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-18s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

setup: ## Initial project setup (env, install, key, migrate, seed)
	cp -n .env.example .env || true
	composer install
	php artisan key:generate
	php artisan migrate:fresh --seed

up: ## Start Docker containers
	docker compose up -d

down: ## Stop Docker containers
	docker compose down

restart: ## Restart Docker containers
	docker compose restart

logs: ## Tail Docker container logs
	docker compose logs -f

migrate: ## Run database migrations
	php artisan migrate

seed: ## Run database seeders
	php artisan db:seed

fresh: ## Drop all tables, re-run all migrations and seed
	php artisan migrate:fresh --seed

pint: ## Run Laravel Pint to format code
	./vendor/bin/pint

pint-test: ## Check code formatting without making changes
	./vendor/bin/pint --test

analyse: ## Run Larastan static analysis at level 6
	./vendor/bin/phpstan analyse --memory-limit=512M

test: pint-test analyse ## Run all quality checks: Pint, Larastan, Pest
	./vendor/bin/pest

serve: ## Start local PHP development server
	php artisan serve --port=8000

clean: ## Clear all application caches
	php artisan cache:clear
	php artisan config:clear
	php artisan route:clear
	php artisan view:clear
