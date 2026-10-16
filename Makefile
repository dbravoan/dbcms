.PHONY: up down restart test test-integration test-release test-filter phpstan pint pint-test migrate seed passport-keys lts-check geo-generate shell

SAIL = ./vendor/bin/sail

up:
	$(SAIL) up -d

down:
	$(SAIL) down

restart:
	$(SAIL) restart

test:
	$(SAIL) shell -c 'bash bin/test-suite fast'

test-integration:
	docker compose -p dbcms-quality -f docker/ci/compose.yml run --build --rm app bash bin/ci-check integration

test-release:
	docker compose -p dbcms-quality -f docker/ci/compose.yml run --build --rm app bash bin/ci-check release

test-filter:
	$(SAIL) pest --configuration=phpunit.fast.xml --filter '$(FILTER)'

phpstan:
	$(SAIL) bin phpstan analyse --memory-limit=2G

pint:
	$(SAIL) pint

pint-test:
	$(SAIL) pint --test

migrate:
	$(SAIL) artisan migrate

seed:
	$(SAIL) artisan db:seed

passport-keys:
	$(SAIL) artisan passport:keys --force

lts-check:
	bash ./bin/check-lts-updates.sh

geo-generate:
	bash ./bin/generate-llms-txt.sh

shell:
	$(SAIL) shell
