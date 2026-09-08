# sk8-docs – Entwicklungsbefehle.
# Läuft PHP lokal, werden die Befehle direkt ausgeführt; sonst im Dev-Container (ADR-002).
#   make composer ARGS="require foo"   make console ARGS="docs:import --dry-run"

SHELL := bash
.SHELLFLAGS := -eu -o pipefail -c
.DEFAULT_GOAL := help

COMPOSE := docker compose
ifeq (,$(shell command -v php 2>/dev/null))
  RUN := $(COMPOSE) run --rm --no-deps php
else
  RUN :=
endif
PHP := $(RUN) php
COMPOSER := $(RUN) composer
CONSOLE := $(PHP) bin/console

# Argumente für composer/console/test: ARGS="..." (kurz: c="...")
c ?=
ARGS ?= $(c)

.PHONY: help build up down logs sh composer console test test-content phpstan cs cs-fix check migrate import

help: ## Zeigt alle Befehle
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

build: ## Baut das Dev-Image
	$(COMPOSE) build --pull php

up: ## Startet die App unter http://localhost:8001
	$(COMPOSE) up -d --wait php

down: ## Stoppt die App
	$(COMPOSE) down --remove-orphans

logs: ## Folgt den Container-Logs
	$(COMPOSE) logs -f php

sh: ## Shell im Container
	$(COMPOSE) run --rm --no-deps php sh

composer: ## Composer, z. B. make composer ARGS="require foo"
	$(COMPOSER) $(ARGS)

console: ## Symfony-Console, z. B. make console ARGS="docs:import --dry-run"
	$(CONSOLE) $(ARGS)

test: ## Migriert die Testdatenbank und führt alle Tests aus
	$(CONSOLE) --env=test doctrine:database:create --if-not-exists -q
	$(CONSOLE) --env=test doctrine:migrations:migrate --no-interaction --allow-no-migration -q
	$(PHP) vendor/bin/phpunit $(ARGS)

test-content: ## Prüft nur die Markdown-Inhalte (Frontmatter, Abschnitte)
	$(PHP) vendor/bin/phpunit --testsuite content

phpstan: ## Statische Analyse (Level max)
	$(CONSOLE) cache:warmup -q
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

cs: ## Code-Stil prüfen
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Code-Stil korrigieren
	$(PHP) vendor/bin/php-cs-fixer fix

check: phpstan cs test ## Alles prüfen: phpstan, cs, test

migrate: ## Migrationen ausführen
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

import: ## Markdown-Inhalte in PostgreSQL importieren
	$(CONSOLE) docs:import
