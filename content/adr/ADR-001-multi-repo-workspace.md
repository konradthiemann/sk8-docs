---
id: ADR-001
title: Multi-Repo-Struktur mit gemeinsamem Workspace
status: akzeptiert
date: 2026-09-07
agents: [architect]
tags: [architektur, repos, tooling]
---

## Kontext

Das Projekt besteht laut Vorgabe aus sechs Repositories (`sk8-backend`, `sk8-skate`, `sk8-nutrition`, `sk8-habits`, `sk8-docs`, `sk8-infrastructure`). Die KI-Agenten brauchen trotzdem eine repoübergreifende Sicht: Jeder Feature-Lauf berührt Backend, ein Frontend und die Dokumentation. Zusätzlich gilt die globale Regel, dass keine KI-Konfiguration (Agenten, Skills, Hooks, Zustandsdateien) in ein Git-Repo gelangt.

## Entscheidung

Ein **Workspace-Verzeichnis** (`~/init-project/`), das selbst **kein Git-Repo** ist, bildet die Wurzel. Die sechs Repos liegen darin als Geschwister-Verzeichnisse. Die gesamte Konfiguration der Entwicklungsumgebung liegt **ausschließlich im Workspace-Root** und wird in jedem Repo per `.gitignore` ausgeschlossen. Die Entwicklungsumgebung wird immer aus dem Workspace-Root gestartet, damit alle sechs Repos gleichzeitig sichtbar sind.

## Alternativen

| Alternative | Warum verworfen |
|---|---|
| Monorepo (pnpm-Workspaces + Composer-Path-Repositories) | Widerspricht der Vorgabe; Railway-Services sind mit einem Repo pro Service einfacher; Multi-Repo ist die in Firmen übliche Realität und damit Lernstoff. |
| Git-Submodule in einem Meta-Repo | Hohe Bedienkomplexität (detached HEADs, doppelte Commits) ohne Nutzen für einen Einzelentwickler. |
| Entwicklungskonfiguration pro Repo | Verstößt gegen die Regel, dass Werkzeugkonfiguration nicht in Produktrepos gehört; sechsfache Pflege. |

## Konsequenzen

- Gemeinsamer Code (z. B. API-Typen) kann nicht direkt geteilt werden. Der **Vertrag** zwischen Backend und Frontends ist die OpenAPI-Spezifikation des Backends; TypeScript-Typen werden daraus pro Frontend generiert (siehe ADR-003).
- Jeder Feature-Lauf schreibt seinen Doku-Eintrag über den Geschwister-Pfad nach `sk8-docs/content/entries/`.
- Repoübergreifende Änderungen erzeugen mehrere Commits (einer pro Repo). Die Commit-Messages referenzieren sich gegenseitig über den Feature-Slug.

## Was du daraus lernst

- Unterschied zwischen *Repository* (versionierte Einheit, ein Deploy-Artefakt) und *Workspace* (lokale Arbeitsumgebung).
- Warum bei Multi-Repo-Architekturen ein maschinenlesbarer Vertrag (OpenAPI) wichtiger ist als geteilter Code.
