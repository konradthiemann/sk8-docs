---
id: ADR-008
title: Sprache, Namenskonventionen, Branching
status: akzeptiert
date: 2026-09-07
agents: [architect]
tags: [konventionen, git, sprache]
---

## Entscheidung

| Bereich | Regel |
|---|---|
| UI-Texte | Deutsch, direkt im Code (kein i18n-Framework – Single-User, YAGNI) |
| Backend-Fehlermeldungen (Validator) | Deutsch über Symfony Translator, `default_locale: de` |
| Code, Bezeichner, Kommentare | Englisch |
| Commit-Messages | Englisch, Conventional Commits, Betreff ≤ 50 Zeichen, Body ≤ 72, imperativ, kleingeschrieben, ohne Trailer |
| Dokumentation (sk8-docs, ADRs, READMEs) | Deutsch, Datumsformat „7. September 2026" |
| Datenbank | `snake_case`, Singular-Tabellennamen (`skate_session`), UUID v7 als Primärschlüssel |
| PHP | PSR-12/PER-CS, `final` als Standard, Konstruktor-Promotion, `readonly` wo möglich |
| TypeScript | `camelCase`, Komponenten `PascalCase`, eine Komponente pro Datei, Tests neben der Datei (`Foo.test.tsx`) |

**Branching:** `main` = Production, `develop` = Development, Feature-Branches `feat/<slug>` mit Pull-Request nach `develop`. Der Entwickler committet und pusht selbst; Agenten liefern die fertige Commit-Message als Klartext.

**Warum Code Englisch, Doku Deutsch:** Bibliotheken, Fehlermeldungen und Community sind englisch – gemischte Bezeichner (`getBenutzerListe`) stören das Lesen. Die Dokumentation richtet sich ausschließlich an den Entwickler und soll ohne Übersetzungsaufwand im Kopf lesbar sein.

## Was du daraus lernst

Warum Konventionen vor dem ersten Feature festgelegt werden: Sie sind billig zu befolgen und teuer nachzuziehen.
