---
id: ADR-005
title: Datenbank-Layout und Deployment auf Railway
status: akzeptiert
date: 2026-09-07
agents: [architect]
tags: [infrastruktur, railway, docker, postgresql, deployment]
---

## Kontext

Vorgabe: Hosting auf Railway, Docker, eine PostgreSQL-Instanz mit mehreren Schemas oder Datenbanken, Auto-Deploy aus Git, Environments Development und Production. Zwei Dienste brauchen eine Datenbank (`sk8-backend`, `sk8-docs`), drei Frontends sind statische Bundles.

## Entscheidung

**Railway-Projekt `sk8`** mit zwei Environments:

| Environment | Git-Branch | Zweck |
|---|---|---|
| `production` | `main` | Täglich genutzte Apps |
| `development` | `develop` | Vorschau neuer Features |

**Services je Environment:** `postgres`, `backend`, `docs`, `skate`, `nutrition`, `habits`.

**Datenbank:** Eine Postgres-Instanz, **zwei Datenbanken** (`sk8_backend`, `sk8_docs`), angelegt durch `sk8-infrastructure/scripts/db-bootstrap.sh`. Lokal legt ein Init-Script im Postgres-Container zusätzlich `sk8_backend_test` und `sk8_docs_test` an.

**Build & Runtime pro Repo:**

| Repo | Dockerfile | Healthcheck |
|---|---|---|
| backend, docs | Multi-Stage auf `dunglas/frankenphp:1-php8.5`; Composer im Build-Stage; Migrationen (`doctrine:migrations:migrate -n`) im Entrypoint | `GET /api/health` bzw. `GET /health` |
| skate, nutrition, habits | Stage 1 `node:22-alpine` + pnpm → `dist/`; Stage 2 `caddy:2-alpine` mit SPA-Fallback und Cache-Headern | `GET /` |

Jedes Repo enthält eine `railway.json` (Builder `DOCKERFILE`, Healthcheck-Pfad, Restart-Policy `ON_FAILURE`). Umgebungsvariablen werden ausschließlich in Railway gesetzt; `.env` im Repo enthält nur ungefährliche Defaults, `.env.local` ist ignoriert.

**Lokale Entwicklung:** `sk8-infrastructure/docker-compose.yml` startet Postgres 16 (Ports 5432) und optional alle Services im Verbund. Standardweg: Postgres im Container, Backend/Docs via `symfony serve`, Frontends via `pnpm dev`.

| Dienst | Lokaler Port |
|---|---|
| postgres | 5432 |
| backend | 8000 |
| docs | 8001 |
| skate | 5173 |
| nutrition | 5174 |
| habits | 5175 |

**Port-Konflikt auf dem Entwicklungsrechner:** Auf 5432 lauscht ein eigener Homebrew-`postgresql@16`
von Konrad, der nicht gestoppt wird. Der Compose-Standard bleibt daher 5432 (portabel), wird aber lokal
über `POSTGRES_PORT=5433` in `sk8-infrastructure/.env` (gitignored) überschrieben. Die PHP-Repos tragen
die abweichende `DATABASE_URL` nur in ihren gitignorierten `.env.local`/`.env.test.local`; versionierte
Dateien nennen immer 5432.

## Warum so

- **Getrennte Datenbanken statt Schemas:** Doctrine-Konfiguration bleibt trivial (eine `DATABASE_URL` pro Dienst), saubere Trennung, Backup je Dienst. Eine Instanz hält die Kosten niedrig.
- **Dockerfile statt Nixpacks:** Volle Kontrolle über PHP-Extensions, Opcache, Worker-Mode; lokal und in Railway identisches Image.
- **Migrationen beim Start:** Für einen einzelnen Nutzer ist ein kurzer Start-Delay akzeptabel; spart einen separaten Deploy-Schritt. Bei Mehrinstanz-Betrieb müsste das in einen Pre-Deploy-Job wandern.
- **Caddy für Frontends:** Wenige MB, korrekte SPA-Fallback-Regel in drei Zeilen, saubere Cache-Header für gehashte Assets.

## Alternativen

Zwei Postgres-Services (doppelte Kosten), Schemas mit `search_path` (in Doctrine umständlich), Vercel/Netlify für Frontends (zusätzlicher Anbieter ohne Nutzen), Nixpacks (weniger Kontrolle).

## Konsequenzen

- Railway-Ressourcen werden bewusst erst nach Freigabe des Entwicklers angelegt (Kosten). Vorbereitet sind Dockerfiles, `railway.json` und der Skill `/deploy`.
- `develop`-Branch muss existieren, bevor das Development-Environment verknüpft wird.

## Was du daraus lernst

12-Factor-Prinzipien (Config über Env), Multi-Stage-Docker-Builds, Healthchecks, warum Migrationen ein Deploy-Thema sind.
