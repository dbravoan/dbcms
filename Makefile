.PHONY: up down restart test test-filter phpstan pint pint-test migrate seed passport-keys lts-check geo-generate shell

SAIL = ./vendor/bin/sail

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

test:
	$(SAIL) test

test-filter:
	$(SAIL) test --filter $(FILTER)

phpstan:
	$(SAIL) exec laravel.test vendor/bin/phpstan analyse --memory-limit=2G

pint:
	$(SAIL) exec laravel.test vendor/bin/pint

pint-test:
	$(SAIL) exec laravel.test vendor/bin/pint --test

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
	docker compose exec -it laravel.test bash
