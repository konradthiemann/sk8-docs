# sk8-docs

Die Lern-Dokumentation der SK8-Plattform – als eigenständige Symfony-Anwendung, nicht als
statische Site. Jedes Feature bekommt einen Chronik-Eintrag (Was / Warum / Wie / Tests /
Lernpunkte), jede Architektur-Entscheidung ein ADR. Du liest hier nach, was gebaut wurde und
**warum** – und lernst dabei PHP, Symfony, Doctrine und PostgreSQL an echtem Code.

## Zweck und Funktionsweise

```
content/**/*.md   →   bin/console docs:import   →   PostgreSQL (sk8_docs)   →   Twig-Seiten
 (Quelle der            (validiert, rendert,          (Index: doc_entry,       (Chronik, ADRs,
  Wahrheit, in Git)      schreibt idempotent)          doc_adr, doc_tag)        Suche, Lernpfad)
```

Die Markdown-Dateien sind die **Quelle der Wahrheit**; die Datenbank ist ein wegwerfbarer Index –
`docs:import` stellt sie jederzeit vollständig wieder her. Verletzt eine Datei das Schema, bricht
der Import mit einer klaren deutschen Fehlermeldung ab und schreibt **nichts**. Details und
Begründung: [ADR-004](content/adr/ADR-004-docs-plattform.md).

Gleicher Stack wie das Backend (Symfony 7.4 LTS, PHP 8.5, Doctrine, PostgreSQL 16, FrankenPHP),
aber **Twig statt React** – damit der Unterschied Server-Rendering ↔ SPA erlebbar wird
([ADR-002](content/adr/ADR-002-backend-stack.md)).

## Inhalte schreiben

Chronik-Eintrag: `content/entries/NNNN-slug.md` (`NNNN` vierstellig, fortlaufend; `slug`
kebab-case, englisch). Architektur-Entscheidung: `content/adr/ADR-NNN-slug.md`.

```markdown
---
id: 1                              # int, muss zur Nummer im Dateinamen passen
title: Projekt-Initialisierung
date: 2026-09-07                   # ISO, JJJJ-MM-TT
type: infrastruktur                # feature · infrastruktur · entscheidung · recherche · refactoring
agents: [architect, implementer]   # Rollen: architect, tester, implementer, uiux, researcher, agentic-engineer
repos: [sk8-docs]
tags: [symfony, docker]            # optional
summary: Ein Satz für die Übersicht.
learning_path: 1                   # optional, Position im Lernpfad
adrs: [ADR-004]                    # optional, verknüpfte Entscheidungen
---

## Was
## Warum
## Wie
## Tests
## Lernpunkte
```

Die fünf Abschnitte sind **Pflicht und in dieser Reihenfolge**; dazwischen sind
`## Datenbank`, `## API`, `## Alternativen` und `## Kosten` erlaubt. Ein ADR braucht
`id`, `title`, `status` (`vorgeschlagen` · `akzeptiert` · `abgelöst`), `date` und `agents`;
optional `tags`, `supersedes`, `superseded_by`. Das vollständige Schema steht in
[ADR-004](content/adr/ADR-004-docs-plattform.md).

Nach dem Schreiben prüfen – validiert Frontmatter, Abschnitte und Dateinamen, schreibt nichts:

```bash
make console ARGS="docs:import --dry-run"
```

Im Markdown funktionieren GFM-Tabellen, Task-Listen, Fußnoten-freie Links,
` ```mermaid `-Diagramme (clientseitig gerendert) und Code-Blöcke mit Sprache
(` ```php ` → Syntax-Highlighting). Relative Links zwischen Inhalten werden automatisch in
App-Routen umgeschrieben: `../adr/ADR-006-api-zugriff-sicherheit.md` → `/adr/ADR-006`,
`../entries/0002-trick-tree.md` → `/eintrag/trick-tree`.

## Einrichtung

Es wird **kein lokales PHP** benötigt – alles läuft im Container ([ADR-002](content/adr/ADR-002-backend-stack.md)).
Voraussetzungen: Docker und die PostgreSQL-Instanz aus `sk8-infrastructure` (`make up` dort).

```bash
scripts/setup.sh          # git-Hooks, .env.local, Dev-Image, Abhängigkeiten
make up                   # http://localhost:8001
make migrate              # Tabellen anlegen
make import               # content/ nach PostgreSQL
```

Läuft PostgreSQL auf einem anderen Host-Port als 5432, gehört der abweichende Wert in die
ignorierten Dateien `.env.local` und `.env.test.local` – nie in `.env`:

```dotenv
DATABASE_URL="postgresql://sk8:sk8@host.docker.internal:5433/sk8_docs?serverVersion=16&charset=utf8"
```

`scripts/setup.sh` legt beide Dateien an; mit `POSTGRES_PORT=5433 scripts/setup.sh` direkt mit
dem passenden Port.

## Befehle

| Befehl | Wirkung |
|---|---|
| `make up` / `make down` | App starten (Port 8001) / stoppen |
| `make logs` | Container-Logs verfolgen |
| `make check` | phpstan (Level max), php-cs-fixer (dry-run), phpunit – muss grün sein |
| `make test` | Testdatenbank migrieren und alle Tests ausführen |
| `make test-content` | nur die Inhalte prüfen (schnell, ohne Datenbank) |
| `make cs-fix` | Code-Stil korrigieren |
| `make migrate` | Doctrine-Migrationen ausführen |
| `make import` | `docs:import` – Markdown nach PostgreSQL |
| `make console ARGS="…"` | beliebiger Console-Befehl, z. B. `debug:router` |
| `make composer ARGS="…"` | Composer, z. B. `require league/csv` |
| `make sh` | Shell im Container |

Ist lokal irgendwann PHP installiert, laufen dieselben Ziele ohne Docker – und
`composer check` funktioniert direkt.

## Seiten

| Route | Inhalt |
|---|---|
| `/` | Chronik, neueste zuerst; Filter `?type=&repo=&tag=`, Contest-Countdown |
| `/eintrag/{slug}` | ein Chronik-Eintrag |
| `/adr` · `/adr/ADR-NNN` | Entscheidungen mit Status-Badge · eine Entscheidung |
| `/lernpfad` | kuratierte Lesereihenfolge (`learning_path`) |
| `/tags` · `/tags/{name}` | Themen · alles zu einem Thema |
| `/repos` · `/repos/{name}` | Repositories · alles zu einem Repository |
| `/suche?q=…` | Volltextsuche über Einträge und ADRs, mit Trefferausschnitten |
| `/health` | `{"status":"ok"}` für den Railway-Healthcheck |

## Struktur

```
content/            Quelle der Wahrheit: entries/NNNN-slug.md, adr/ADR-NNN-slug.md
src/
├── Command/        docs:import
├── Content/        Frontmatter-Parser, Validator, Markdown-Renderer, Importer
├── Controller/     dünne HTTP-Schicht, rendert Twig
├── Doctrine/       tsvector-Typ, JSONB_CONTAINS für DQL
├── Entity/         DocEntry, DocAdr, DocTag + Enums
├── Repository/     Abfragen, inkl. Volltextsuche
└── Service/        ContestCountdown
templates/          base.html.twig + eine Datei je Seite, _partials/_meta.html.twig
assets/             app.js (Importmap) und styles/app.css – kein Node, kein Build-Schritt
migrations/         Doctrine-Migrationen (inkl. tsvector-Spalten und GIN-Indizes)
tests/              Unit · Functional · Content (drei PHPUnit-Testsuites)
frankenphp/         Caddyfile, PHP-INI, Entrypoint
```

Frontend-Abhängigkeiten (Mermaid, highlight.js, Chart.js) kommen über die
Symfony-**AssetMapper**-Importmap: kein Node, kein Bundler, keine `<script>`-Tags von einem CDN.
Neue Pakete mit `make console ARGS="importmap:require <paket>"`.

## Tests

```bash
make test                                   # alles
make console ARGS="…"                       # oder gezielt:
docker compose run --rm --no-deps php php vendor/bin/phpunit --testsuite unit
```

| Testsuite | Was sie prüft |
|---|---|
| `unit` | Frontmatter-Parser, Validator, Markdown-Renderer – ohne Kernel und Datenbank |
| `functional` | `docs:import` und alle Seiten gegen die echte Testdatenbank `sk8_docs_test` |
| `content` | jede Datei in `content/` gegen das Schema – schnell, ohne Datenbank |

Jeder Test läuft in einer Transaktion, die danach zurückgerollt wird
(`dama/doctrine-test-bundle`, [ADR-007](content/adr/ADR-007-test-und-qualitaetsstrategie.md)).

## Deployment

Railway, Builder `DOCKERFILE`, Healthcheck `/health` (siehe `railway.json` und
[ADR-005](content/adr/ADR-005-datenbank-deployment.md)). Der Entrypoint wartet beim Start auf die
Datenbank, führt `doctrine:migrations:migrate` aus und importiert `content/` – ein Deploy
aktualisiert die Doku also automatisch. Umgebungsvariablen werden ausschließlich in Railway
gesetzt; im Repo stehen nur ungefährliche Defaults.

| Variable | Bedeutung |
|---|---|
| `APP_ENV` | `prod` in Railway |
| `APP_SECRET` | 32 Hex-Zeichen (`openssl rand -hex 16`) |
| `DATABASE_URL` | Verweis auf den Postgres-Service, Datenbank `sk8_docs` |
| `DEFAULT_URI` | öffentliche Basis-URL (für absolute Links aus der CLI) |
| `RUN_MIGRATIONS` | `1` erzwingt Migration + Import auch außerhalb von `prod` |
