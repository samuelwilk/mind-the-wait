
##@ Help

## Source - https://www.thapaliya.com/en/writings/well-documented-makefiles/
help:  ## Display this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ Docker
docker-build: ## Build the docker containers
	@echo "Building and Starting Docker containers detached..."
	@INSTALL_DEPENDENCIES=true docker compose -f docker/compose.yaml --env-file .env.local up --build -d

docker-up: ## Start the docker containers, without building them first.
	@echo "Starting main Docker containers detached..."
	@docker compose -f docker/compose.yaml --env-file .env.local up -d

docker-up-with-logs: ## Start the docker containers, without building them first.
	@echo "Starting main Docker containers with log..."
	@docker compose -f docker/compose.yaml --env-file .env.local up

docker-down: ## Close down docker containers.
	@echo "Closing down Docker containers..."
	@docker compose -f docker/compose.yaml down

docker-prune: ## Close down docker containers and remove volumes.
	@echo "Closing down Docker containers..."
	@docker compose -f docker/compose.yaml down -v

docker-php: ## Opens an interactive shell into the PHP Docker container
	@docker compose -f docker/compose.yaml exec php bash

##@ Symfony

cc: ## Clears the symfony cache
	@docker compose -f docker/compose.yaml exec php bin/console cache:clear

##@ Composer

composer-install: ## Installs vendor files via composer
	@docker compose -f docker/compose.yaml exec php composer install

##@ Database

database: ## Sets up the dev database
	@make database-create
	@make database-migrations-execute
#	@make database-fixtures

database-create: ## Creates the database
	@docker compose -f docker/compose.yaml exec php bin/console doctrine:database:drop --if-exists --force
	@docker compose -f docker/compose.yaml exec php bin/console doctrine:database:create

database-fixtures: ## Runs the dev fixtures
	@docker compose -f docker/compose.yaml exec php bin/console doctrine:fixtures:load -n --group=dev -e dev

database-migrations-execute: ## Runs the current set of migrations against the DB
	@docker compose -f docker/compose.yaml exec php bin/console doctrine:migrations:migrate --no-interaction

database-test: ## Sets up the test database
	@make database-create-test
	@make database-migrations-execute-test
#	@make database-fixtures-test  # Optional: add if you create test fixtures

database-create-test: ## Creates the database
	@docker compose -f docker/compose.yaml exec -e APP_ENV=test php bin/console doctrine:database:drop --if-exists --force
	@docker compose -f docker/compose.yaml exec -e APP_ENV=test php bin/console doctrine:database:create

database-fixtures-test: ## Runs the test fixtures
	@docker compose -f docker/compose.yaml exec -e APP_ENV=test  php bin/console doctrine:fixtures:load -n --group=test -e test

database-migrations-execute-test: ## Runs the current set of migrations against the DB
	@docker compose -f docker/compose.yaml exec -e APP_ENV=test  php bin/console doctrine:migrations:migrate --no-interaction

database-migrations-generate: ## Generates a new set of migrations
	@docker compose -f docker/compose.yaml exec php bin/console doctrine:migrations:diff


##@ Linting

cs-dry-run: ## Dry run of the PHP Code Standards checker
	@docker compose -f docker/compose.yaml exec php php-cs-fixer --config=.php-cs-fixer.php fix -v --diff --dry-run

cs-fix: ## Automatically apply fixes from php-cs-fixer
	@docker compose -f docker/compose.yaml exec php php-cs-fixer --config=.php-cs-fixer.php fix -v --diff

##@ Tests

test-phpunit: database-test ## Runs PHPUnit (resets test database first)
	@docker compose -f docker/compose.yaml exec php vendor/bin/phpunit --configuration phpunit.dist.xml

##@ Application

score-tick: ## Runs the headway scoring cycle once
	@docker compose -f docker/compose.yaml exec php bin/console app:score:tick

##@ Mailpit
mailpit-delete-all-mail: ## Delete all mail from Mailpit
	curl -X DELETE http://stsi.local:8025/api/v1/messages

##@ Project

setup: ## Complete application setup - builds, installs deps, creates databases, loads GTFS data
	@echo "🚀 Starting complete mind-the-wait setup..."
	@echo ""
	@echo "📦 Step 1/7: Building and starting Docker containers..."
	@make docker-build
	@echo ""
	@echo "📚 Step 2/7: Installing Composer dependencies..."
	@make composer-install
	@echo ""
	@echo "🗄️  Step 3/7: Setting up development database..."
	@make database
	@echo ""
	@echo "🧪 Step 4/7: Setting up test database..."
	@make database-test
	@echo ""
	@echo "🚍 Step 5/7: Loading GTFS static data..."
	@make gtfs-load
	@echo ""
	@echo "🌤️  Step 6/7: Collecting initial weather data..."
	@make weather-collect
	@echo ""
	@echo "📊 Step 7/7: Running initial score calculation..."
	@make score-tick
	@echo ""
	@echo "✅ Setup complete! Application is ready to use."
	@echo ""
	@echo "📍 Access the application at: https://localhost"
	@echo "📊 Dashboard: https://localhost/"
	@echo "🔧 API docs: See docs/api/endpoints.md"
	@echo ""
	@echo "💡 Next steps:"
	@echo "  - Check realtime data: curl -sk https://localhost/api/realtime | jq"
	@echo "  - View scheduler logs: docker compose -f docker/compose.yaml --env-file .env.local logs -f scheduler"
	@echo "  - Run tests: make test-phpunit"

gtfs-load: ## Load GTFS static data (uses ArcGIS by default, or set MTW_GTFS_STATIC_URL for ZIP)
	@echo "Loading GTFS static data (this may take 1-5 minutes)..."
	@docker compose -f docker/compose.yaml exec php bin/console app:gtfs:load --mode=arcgis || \
		docker compose -f docker/compose.yaml exec php bin/console app:gtfs:load

weather-collect: ## Collect current weather data
	@docker compose -f docker/compose.yaml exec php bin/console app:collect:weather
