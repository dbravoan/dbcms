.DEFAULT_GOAL := help

.PHONY: help init up down restart test test-integration test-release test-filter phpstan pint pint-test migrate seed passport-keys support-check geo-generate shell prepare-release prepare-deploy

SAIL = ./vendor/bin/sail

help:
	@echo 'make init — guided project setup (Docker required)'
	@echo 'make up / down / restart — manage official Sail'
	@echo 'make test / test-integration / test-release — verification lanes'

init:
	./bin/project-init $(ARGS)

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

support-check:
	bash ./bin/check-framework-support.sh

geo-generate:
	bash ./bin/generate-llms-txt.sh

shell:
	$(SAIL) shell

# Exact committed source; signing key stays outside the repository.
prepare-release:
	bin/prepare-delivery --kind=release --sha=$$(git rev-parse HEAD) $(ARGS)

prepare-deploy:
	bin/prepare-delivery --kind=deploy --sha=$$(git rev-parse HEAD) $(ARGS)
