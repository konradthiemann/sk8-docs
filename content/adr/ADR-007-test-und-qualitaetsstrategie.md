---
id: ADR-007
title: Test- und Qualitätsstrategie – TDD verpflichtend
status: akzeptiert
date: 2026-09-07
agents: [architect, tester]
tags: [testing, tdd, phpunit, vitest, qualität]
---

## Kontext

Vorgabe: Tests werden **immer vor** der Implementierung geschrieben; Hooks erzwingen Lint, Format und grüne Tests vor jedem Commit. Der gesamte Code entsteht durch Agenten – Tests sind damit die wichtigste Sicherung gegen stille Fehler.

## Entscheidung

**Backend (`sk8-backend`, `sk8-docs`)**

| Ebene | Werkzeug | Was wird geprüft |
|---|---|---|
| Unit | PHPUnit 12, `tests/Unit/` | Services und Domänenlogik ohne Kernel |
| Functional | PHPUnit `WebTestCase`, `tests/Functional/` | HTTP-Verträge gegen echte PostgreSQL-Testdatenbank (`sk8_backend_test`) |
| Isolation | `dama/doctrine-test-bundle` | Jeder Test läuft in einer Transaktion, die zurückgerollt wird |
| Fixtures | `zenstruck/foundry` | Lesbare Testdaten-Factories |
| Statik | PHPStan Level max + `phpstan-strict-rules` + `phpstan-symfony` + `phpstan-doctrine` | Typfehler vor der Laufzeit |
| Stil | php-cs-fixer (`@Symfony`, `@PER-CS`) | Einheitliche Formatierung |

**Frontends (`sk8-skate`, `sk8-nutrition`, `sk8-habits`)**

| Ebene | Werkzeug | Was wird geprüft |
|---|---|---|
| Unit/Komponente | Vitest + Testing Library (jsdom) | Verhalten aus Nutzersicht (Rollen, Texte), nicht Implementierungsdetails |
| API-Grenze | MSW | Handler simulieren die Backend-API exakt nach OpenAPI |
| Typen | `tsc --noEmit` | Vollständige Typprüfung |
| Stil | Biome (`lint` + `format`) | |
| E2E | Playwright (später, kritische Flows) | Session anlegen, Mahlzeit loggen, Habit abhaken |

**Prozess**

- Die **Tester-Rolle** schreibt Tests und Akzeptanzkriterien zuerst; ein Pre-Write-Hook der Entwicklungsumgebung blockiert Schreibzugriffe auf `src/`, solange im betroffenen Repo keine ungecommitteten Teständerungen existieren.
- **Git-Hooks** liegen versioniert in `.githooks/pre-commit` (Lint, Format-Check, Tests) und werden per `scripts/setup.sh` (`git config core.hooksPath .githooks`) aktiviert.
- **GitHub Actions** pro Repo (lint + test); Railway-Option „Wait for CI" verhindert Deploys bei roten Checks.

## Alternativen

Pest statt PHPUnit (angenehmer, aber PHPUnit ist der Standard, den man lernen sollte), Jest statt Vitest (langsamer, ESM-Reibung), Cypress statt Playwright (Playwright ist schneller und multi-browser), SQLite für Tests (verdeckt Postgres-spezifisches Verhalten wie JSONB).

## Konsequenzen

- Kein Feature ohne mindestens einen Functional- oder Komponententest. Doku-Einträge listen die Tests und was sie beweisen.
- Testdatenbank muss lokal existieren (Docker-Compose legt sie an).

## Was du daraus lernst

Test-Pyramide, Unterschied Unit ↔ Functional, Transaktions-Rollback als Isolationsstrategie, API-Mocking mit MSW, Testing-Library-Philosophie („teste wie ein Nutzer").
