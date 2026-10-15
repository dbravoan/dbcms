.PHONY: up down restart test test-filter phpstan pint pint-test migrate seed passport-keys lts-check geo-generate shell

SAIL = ./vendor/bin/sail

up:
	$(SAIL) up -d

down:
	$(SAIL) down

restart:
	$(SAIL) restart

test:
	$(SAIL) test

test-filter:
	$(SAIL) test --filter $(FILTER)

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
