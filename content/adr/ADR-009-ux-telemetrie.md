---
id: ADR-009
title: UX-Telemetrie – eigene Events, eigene Datenbank
status: akzeptiert
date: 2026-09-07
agents: [architect, uiux]
tags: [ux, telemetrie, frontend, backend, postgresql]
---

## Kontext

Der UI/UX-Agent soll anhand echter Nutzungsdaten (Interaktionen, Navigationspfade, Verweildauer, Feature-Nutzung) Verbesserungen vorschlagen. Daten müssen in PostgreSQL liegen. Kein Drittanbieter.

## Entscheidung

**Frontend:** Ein `useTelemetry()`-Hook plus `<TelemetryProvider>` in jeder App. Event-Typen:

| Typ | Ausgelöst durch | Felder |
|---|---|---|
| `screen_view` | Router-Wechsel | `screen` = Pfad, `meta: {from}` |
| `time_on_screen` | Verlassen eines Screens | `screen`, `meta: {ms}` |
| `interaction` | Klick/Submit auf markierten Elementen (`data-track="<domain>.<aktion>"`) | `screen`, `target` = data-track-Wert, `meta: {via: click|submit, tag}` |
| `navigation` | Menü/Back-Navigation | `target` = Zielpfad, `meta: {from, to, via}` |

Events werden gepuffert (max. 20 Stück oder 10 s) und als Batch an `POST /api/telemetry/events` gesendet; beim Verlassen der Seite (`pagehide`, `visibilitychange` → hidden) per `fetch(…, { keepalive: true })`. **Nicht** `navigator.sendBeacon`: Beacons können keinen `X-Api-Key`-Header setzen (ADR-006), `keepalive`-Fetches überleben das Schließen des Tabs ebenfalls. `session_id` ist eine zufällige UUID pro Tab, `app` identifiziert die App. Abschaltbar über `VITE_TELEMETRY=off`.

**Backend:** Tabelle `ux_event` (`id` uuid, `app` text, `session_id` uuid, `type` text, `screen` text, `target` text null, `meta` jsonb, `occurred_at` timestamptz, `received_at` timestamptz). Indizes auf (`app`, `occurred_at`) und (`type`). Auswertungen zunächst als SQL-Views (`ux_screen_dwell`, `ux_top_interactions`), später Dashboard in `sk8-docs`.

## Alternativen

Plausible/PostHog/GA (Drittanbieter, Kosten oder Datenabfluss, weniger Kontrolle über Event-Schema), Logs statt Tabelle (nicht abfragbar).

## Konsequenzen

- Jeder neue Screen bekommt beim Anlegen automatisch `screen_view`/`time_on_screen`; Interaktionen werden gezielt über `data-track` markiert.
- Telemetrie-Fehler dürfen die App nie stören (fire-and-forget, `catch` schluckt).

## Was du daraus lernst

Event-Design, JSONB in PostgreSQL, Batching und `fetch keepalive`, wie man aus Rohdaten Kennzahlen ableitet.
