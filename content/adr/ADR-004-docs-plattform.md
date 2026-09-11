---
id: ADR-004
title: Dokumentationsplattform – Markdown im Repo, PostgreSQL als Index
status: akzeptiert
date: 2026-09-07
agents: [architect]
tags: [docs, symfony, twig, markdown, postgresql]
---

## Kontext

`sk8-docs` soll eine eigenständige Symfony/PHP-Webanwendung (keine statische Site) sein, die jedes Feature chronologisch mit WAS/WARUM/WIE dokumentiert und dem Entwickler PHP/Symfony, React, Datenbankdesign und Architektur beibringt. Inhalte werden von KI-Agenten nach jedem Feature erzeugt.

## Entscheidung

- **Gleicher Stack wie das Backend** (Symfony 7.4, PHP 8.5, Doctrine, PostgreSQL, FrankenPHP) – jede Symfony-Lektion wirkt doppelt. **Twig** statt React im Frontend, damit der Kontrast Server-Rendering ↔ SPA erlebbar wird.
- **Inhalte als Markdown-Dateien mit YAML-Frontmatter im Repo**: `content/entries/NNNN-slug.md` (Chronik) und `content/adr/ADR-NNN-slug.md` (Entscheidungen). Versioniert, diffbar, von Agenten trivial zu schreiben.
- **Import in PostgreSQL** per Console-Command `bin/console docs:import` (idempotent, Upsert nach `id`). Tabellen: `doc_entry`, `doc_adr`, `doc_tag`, `doc_entry_tag`, Volltext per `tsvector`-Spalte (`german`-Konfiguration). Der Import läuft beim Container-Start.
- **Rendering**: `league/commonmark` (GFM-Tabellen, Frontmatter-Extension, Heading-Permalinks), Mermaid (Client-Side für Diagramme), highlight.js (Code), Chart.js (Kennzahlen wie Tests je Feature).
- **Navigation**: Chronik · ADRs · Tags · Repos · Suche · Lernpfad (kuratierte Reihenfolge über Frontmatter `learning_path`).
- **Sprache**: Deutsch, Datumsformat „7. September 2026".

## Frontmatter-Schema

**Chronik-Eintrag (`content/entries/0001-slug.md`)**

| Feld | Typ | Pflicht | Beispiel |
|---|---|---|---|
| `id` | int | ja | `1` |
| `title` | string | ja | `Projekt-Initialisierung` |
| `date` | ISO-Datum | ja | `2026-09-07` |
| `type` | enum | ja | `feature` · `infrastruktur` · `entscheidung` · `recherche` · `refactoring` |
| `agents` | Liste | ja | `[architect, implementer, tester]` |
| `repos` | Liste | ja | `[sk8-backend, sk8-skate]` |
| `tags` | Liste | nein | `[doctrine, tdd]` |
| `summary` | string | ja | Ein Satz für die Übersicht |
| `learning_path` | int | nein | Position im Lernpfad |
| `adrs` | Liste | nein | `[ADR-002]` – verknüpfte Entscheidungen |
| `tickets` | Liste | nein | `[T-0102, T-0104]` – Ticket-Kennungen, Format `T-NNXX` oder `R-NN` |

Pflicht-Abschnitte im Body: `## Was`, `## Warum`, `## Wie`, `## Tests`, `## Lernpunkte`. Optional: `## Datenbank`, `## API`, `## Alternativen`.

**ADR (`content/adr/ADR-001-slug.md`)**

| Feld | Typ | Pflicht |
|---|---|---|
| `id` | `ADR-NNN` | ja |
| `title` | string | ja |
| `status` | `vorgeschlagen` · `akzeptiert` · `abgelöst` | ja |
| `date` | ISO-Datum | ja |
| `agents` | Liste | ja |
| `tags` | Liste | nein |
| `supersedes` / `superseded_by` | `ADR-NNN` | nein |

## Alternativen

| Alternative | Warum verworfen |
|---|---|
| Statische Site (VitePress, Docusaurus) | Vorgabe ist eine Symfony-App; keine Suche/Filter ohne Zusatzdienste; kein Lerneffekt für PHP. |
| Notion / Wiki | Nicht versioniert mit dem Code, nicht von Agenten reproduzierbar befüllbar. |
| Inhalte nur in der DB (CMS-Editor) | Agenten schreiben Dateien einfacher als API-Calls; Git bleibt die Wahrheit. |

## Konsequenzen

- Die Markdown-Dateien sind die Quelle der Wahrheit; die Datenbank ist ein wegwerfbarer Index (`docs:import` stellt alles wieder her).
- Frontmatter-Verstöße brechen den Import mit klarer Fehlermeldung → Tests im Docs-Repo prüfen jede Datei.

## Was du daraus lernst

Symfony Console Commands, Twig-Templating und -Vererbung, Doctrine mit PostgreSQL-Volltextsuche, eine Markdown-Pipeline mit Extensions, idempotente Import-Jobs.
