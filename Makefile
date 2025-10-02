
##@ Help

## Source - https://www.thapaliya.com/en/writings/well-documented-makefiles/
help:  ## Display this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ Docker
docker-build: ## Build the docker containers
	@echo "Building and Starting Docker containers detached..."
	@INSTALL_DEPENDENCIES=true docker compose --env-file .env up --build -d

docker-up: ## Start the docker containers, without building them first.
	@echo "Starting main Docker containers detached..."
	@docker compose --env-file .env up -d

docker-up-with-logs: ## Start the docker containers, without building them first.
	@echo "Starting main Docker containers with log..."
	@docker compose --env-file .env up

docker-down: ## Close down docker containers.
	@echo "Closing down Docker containers..."
	@docker compose down

docker-prune: ## Close down docker containers and remove volumes.
	@echo "Closing down Docker containers..."
	@docker compose down -v

docker-php: ## Opens an interactive shell into the PHP Docker container
	@docker compose exec php bash

##@ Symfony

cc: ## Clears the symfony cache
	@docker compose exec php bin/console cache:clear

##@ Composer

composer-install: ## Installs vendor files via composer
	@docker compose exec php composer install

##@ Grump

grump-run: ## Run PHP Grump - without having to commit
	@docker compose exec php ./vendor/bin/grumphp run

grump-init: ## Runs the git:init command to force update the git hooks with any configuration changes
	@docker compose exec php ./vendor/bin/grumphp git:init

##@ Database

database: ## Sets up the dev database
	@make database-create
	@make database-migrations-execute
#	@make database-fixtures

database-create: ## Creates the database
	@docker compose exec php bin/console doctrine:database:drop --if-exists --force
	@docker compose exec php bin/console doctrine:database:create

database-fixtures: ## Runs the dev fixtures
	@docker compose exec php bin/console doctrine:fixtures:load -n --group=dev -e dev

database-migrations-execute: ## Runs the current set of migrations against the DB
	@docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

database-test: ## Sets up the test database
	@make database-create-test
	@make database-migrations-execute-test
	@make database-fixtures-test

database-create-test: ## Creates the database
	@docker compose exec -e APP_ENV=test php bin/console doctrine:database:drop --if-exists --force
	@docker compose exec -e APP_ENV=test php bin/console doctrine:database:create

database-fixtures-test: ## Runs the test fixtures
	@docker compose exec -e APP_ENV=test  php bin/console doctrine:fixtures:load -n --group=test -e test

database-migrations-execute-test: ## Runs the current set of migrations against the DB
	@docker compose exec -e APP_ENV=test  php bin/console doctrine:migrations:migrate --no-interaction

database-migrations-generate: ## Generates a new set of migrations
	@docker compose exec php bin/console doctrine:migrations:diff

##@ PHPStan

phpstan-baseline-generate: ## generates/updates the baseline file
	@docker compose exec php vendor/bin/phpstan analyse --generate-baseline

phpstan-run: ## Runs PHPStan
	@docker compose exec php vendor/bin/phpstan analyse


##@ Linting

cs-dry-run: ## Dry run of the PHP Code Standards checker
	@docker compose exec php php-cs-fixer --config=.php-cs-fixer.php fix -v --diff --dry-run

cs-fix: ## Automatically apply fixes from php-cs-fixer
	@docker compose exec php php-cs-fixer --config=.php-cs-fixer.php fix -v --diff

##@ Tests

test-phpunit: ## Runs PHPUnit
	@docker compose exec php php vendor/bin/simple-phpunit --configuration phpunit.xml.dist

##@ Mailpit
mailpit-delete-all-mail: ## Delete all mail from Mailpit
	curl -X DELETE http://stsi.local:8025/api/v1/messages

##@ Project

setup: ## setup the project
	make update-localdev-env
	make update-cert
	make docker-build
	make composer-install
	make database
	make database-test

update-localdev-env: ## Update the localdev env file
	git submodule update --init --remote --recursive
	./bootstrap/bitwarden/pull_local_dev_env.sh localdev-ISK-STSI-Web

update-cert: ## Generate a local SSL certificate
	mkdir -p bootstrap/nginx
	mkcert -key-file bootstrap/nginx/key.pem -cert-file bootstrap/nginx/cert.pem stsi.local localhost

user-setup: ## Sets up the application by loading in a default admin user. This is to used in live environemnts, or used in a run configuration to mock a live environment.
	@docker compose exec php bin/console app:setup password
