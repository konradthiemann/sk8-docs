---
id: ADR-002
title: Backend-Stack – Symfony 7.4 LTS auf FrankenPHP
status: akzeptiert
date: 2026-09-07
agents: [architect]
tags: [backend, php, symfony, doctrine, postgresql]
---

## Kontext

Das Backend ist das Herz der Plattform: REST-API für drei Frontends, Datenhaltung, KI-Engine, UX-Telemetrie. Vorgabe: PHP 8.3+, Symfony 7, PostgreSQL. Der Entwickler kennt JS/TS/Vue und lernt PHP/Symfony durch dieses Projekt – die Architektur muss also Symfony-Grundlagen *sichtbar* machen statt sie zu verstecken.

## Entscheidung

| Baustein | Wahl |
|---|---|
| PHP | 8.5 im Container (`dunglas/frankenphp:1-php8.5`); lokale Installation optional |
| Framework | Symfony 7.4 LTS (Support bis Ende 2028) |
| App-Server | FrankenPHP (ein Container, kein nginx + php-fpm) |
| ORM | Doctrine ORM 3 + Doctrine Migrations |
| Datenbank | PostgreSQL 16 |
| API-Stil | Explizite Controller + Request/Response-DTOs + Symfony Serializer + Validator |
| OpenAPI | NelmioApiDocBundle (Attribute → OpenAPI 3.0, UI unter `/api/doc`, JSON unter `/api/doc.json`) |
| CORS | NelmioCorsBundle |
| Async | Symfony Messenger mit Doctrine-Transport (für KI-Batch-Jobs) |
| Logging | Monolog (JSON nach stdout → Railway-Logs) |

**Schichtung (bewusst leicht, kein volles DDD/CQRS):**

```
src/
├── Controller/   HTTP-Eingang, mappt Request → DTO, ruft Service
├── Dto/          Request- und Response-Objekte mit Validator-Attributen
├── Service/      Geschäftslogik, frameworkarm, unit-testbar
├── Entity/       Doctrine-Entities (Persistenzmodell)
├── Repository/   Doctrine-Repositories (Abfragen)
└── Ai/           Anthropic-Client, Prompts, Caching (siehe ADR-010)
```

## Warum so – und nicht anders

- **Kein API Platform.** API Platform ist sehr produktiv, verdeckt aber Routing, Controller, Serializer und Validator hinter Attributen und State-Providern. Für jemanden, der Symfony *lernen* will, ist das kontraproduktiv; Fehler sind für Einsteiger schwer zu debuggen. Mit KI-Unterstützung ist Boilerplate kein Engpass. Kann später ergänzt werden, falls Filter/Pagination-Bedarf explodiert.
- **FrankenPHP statt nginx + php-fpm.** Ein Prozess, ein Port, optionaler Worker-Mode, offizielles Vorbild `dunglas/symfony-docker`. Railway terminiert TLS selbst, Caddy-Auto-HTTPS wird nicht benötigt.
- **Symfony 7.4 LTS statt 8.0.** LTS-Support, alle Bundles kompatibel, mehr Lernmaterial. 8.0 ist inhaltlich 7.4 ohne Deprecations – der spätere Umstieg ist trivial.
- **PHP 8.5, ausschließlich im Container.** Vorgabe war 8.3+. Der Entwicklungsrechner (macOS 14.4) hat keine aktuelle PHP-Installation und Homebrew kann PHP dort derzeit nicht bauen (veraltete Command Line Tools). Deshalb laufen Composer, Console und PHPUnit über das Dev-Target des Dockerfiles (`make composer`, `make console`, `make test`). Vorteil: lokal und auf Railway läuft exakt dasselbe Image. Sobald lokal PHP installiert ist, funktionieren dieselben Befehle auch direkt (`composer check`).

## Alternativen

Laravel (nicht Vorgabe), API Platform (s. o.), nginx + php-fpm (zwei Container, mehr Konfiguration), Symfony 8.0 (kein LTS), MySQL (Vorgabe war PostgreSQL; JSONB und Volltext sprechen ohnehin für Postgres).

## Konsequenzen

- OpenAPI-Spec wird per Attributen gepflegt und ist der Vertrag für alle Frontends (`make openapi`).
- **Nachtrag 7. September 2026:** Die Spec wird als **OpenAPI 3.0.0** ausgeliefert, nicht 3.1.
  NelmioApiDoc 5 bietet für die Version keinen Konfigurationsschlüssel, nur einen Setter am
  Generator-Service; ein Wechsel bräuchte einen Compiler-Pass. Zudem ändert 3.1 die Darstellung von
  `nullable` in Typ-Arrays, was die Typen der bereits gegen 3.0 gebauten Frontends verschieben würde.
  Für `openapi-typescript` ist 3.0 vollständig ausreichend.
- Jede Geschäftslogik liegt in `Service/` und ist ohne Kernel testbar; HTTP-Verhalten wird per `WebTestCase` gegen echte Postgres getestet (ADR-007).
- Migrationen laufen beim Container-Start (Single-User-Kompromiss, siehe ADR-005).

## Was du daraus lernst

Symfony-Request-Lifecycle (Kernel → Router → Controller → Response), Dependency-Injection/Autowiring, Doctrine Unit of Work, Migrations-Workflow, Attribute in PHP 8 – jeweils dort, wo Vue/Nuxt-Vorwissen anknüpft (Composables ↔ Services, Nuxt-Server-Routes ↔ Controller).
