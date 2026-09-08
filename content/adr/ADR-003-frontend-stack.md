---
id: ADR-003
title: Frontend-Stack – Vite, React 19, TanStack, Tailwind, shadcn/ui
status: akzeptiert
date: 2026-09-07
agents: [architect, uiux]
tags: [frontend, react, vite, tailwind, pwa, tooling]
---

## Kontext

Drei React-PWAs (`sk8-skate`, `sk8-nutrition`, `sk8-habits`) mit identischen Grundanforderungen: installierbar, deutsch, minimalistisch-dicht, UX-Telemetrie, typisierter Zugriff auf die Backend-API. Vorgabe: Die Agenten wählen das React-Ökosystem, das mit KI-gestützter Entwicklung die besten Ergebnisse liefert, und dokumentieren die Begründung. Kein Offline-Betrieb, keine Push-Notifications, kein SEO.

## Entscheidung

| Bereich | Wahl | Begründung in einem Satz |
|---|---|---|
| Build | Vite 8 + TypeScript 7 (strict) | Schnell, wenig Konfiguration, statisches Bundle für Railway. |
| UI-Lib | React 19 | Vorgabe. |
| Routing | TanStack Router (dateibasiert) | Wie Nuxt-Pages (Vorwissen), vollständig typisierte Params – Fehler fallen beim Kompilieren auf, nicht zur Laufzeit. |
| Server-State | TanStack Query | Caching, Retry, Invalidation ohne Boilerplate; gleiche Familie wie Router. |
| Client-State | Zustand (sparsam) | Nur für UI-Zustand, der nicht vom Server kommt (z. B. offener Dialog). |
| Styling | Tailwind CSS v4 | Utility-First, Design-Tokens als CSS-Variablen – identische Token-Datei in allen drei Apps. |
| Komponenten | shadcn/ui (Radix-Primitives) | Code liegt im Repo → KI passt Komponenten direkt an; Barrierefreiheit durch Radix; kein Runtime-Bundle einer fremden UI-Lib. |
| Formulare | react-hook-form + zod | Schema-Validierung, die dieselben Regeln wie das Backend abbildet. |
| API-Client | openapi-typescript + openapi-fetch | Typen aus der Backend-OpenAPI-Spec generiert (ADR-001: Vertrag statt Shared Code). |
| Charts | Recharts | Deklarativ, ausreichend für Verläufe/Korrelationen. |
| Trick-Tree | @xyflow/react | Graph-Darstellung mit Zoom/Pan für die Progressions-Karte. |
| Datum | date-fns (`de`-Locale) | Tree-shakeable, deutsches Format „7. September 2026". |
| PWA | vite-plugin-pwa | Manifest + minimaler Service-Worker für Installierbarkeit; kein Offline-Cache-Versprechen. |
| Tests | Vitest 5 + Testing Library + MSW 2; Playwright (E2E, später) | Verhalten statt Implementierung testen; API per MSW mocken. |
| Lint/Format | Biome | Ein Tool statt ESLint + Prettier + fünf Plugins; deterministisch, sehr schnell. |
| Paketmanager | pnpm | Lokal vorhanden, schnell, strikte Auflösung. |

## Warum nicht …

- **Next.js:** Kein SSR/SEO-Bedarf, Single-User, statisches Hosting reicht. Server Components und Caching-Semantik sind zusätzliche Fehlerquellen, die hier nichts einbringen.
- **React Router v7 (Framework Mode):** Gut, aber TanStack Router bietet strengere Typisierung von Route-Params und Search-Params – genau die Stelle, an der falsche Annahmen über Routen sonst erst zur Laufzeit auffallen.
- **MUI / Chakra / Ant:** Fertige Design-Sprache, schwer auf „minimalistisch, aber informationsdicht" zu trimmen; Runtime-CSS-in-JS-Kosten.
- **Redux:** Overkill für Single-User-Apps, deren Zustand fast vollständig Server-State ist.
- **ESLint + Prettier:** Funktioniert, aber Konfigurationsaufwand und Plugin-Konflikte kosten Zeit, die hier keinen Lernwert hat.

## Shared Code zwischen den drei Apps

Bewusst **kein** eigenes npm-Paket. Die drei Apps entstehen aus derselben Vorlage (`sk8-skate` ist die Referenz). Gemeinsame Änderungen (Design-Tokens, Telemetrie-Hook, API-Client-Basis) werden per Skill in alle drei Repos gespiegelt. Sobald das schmerzt (dritte identische Änderung an derselben Datei), wird dieses ADR durch ein „shared package"-ADR abgelöst.

## Konsequenzen

- Jede App hat dieselbe Ordnerstruktur: `src/routes/` (Dateirouten), `src/features/<domain>/` (Komponenten + Hooks + Tests je Fachbereich), `src/components/ui/` (shadcn), `src/lib/` (API-Client, Telemetrie, Utils).
- `pnpm gen:api` erzeugt `src/lib/api/schema.d.ts` aus der Backend-Spec (`GET /api/doc.json`).
- Stand 7. September 2026 installiert: Vite 8.2, React 19.2, TanStack Router 1.170 / Query 5.102, Tailwind 4.3, Vitest 5.0, Biome 2.5, vite-plugin-pwa 1.3, MSW 2.15, zod 4.5, TypeScript 7.0.
- Deutsche UI-Texte stehen direkt im Code (Single-User, kein i18n-Framework – YAGNI).

## Was du daraus lernst

Mapping deines Vue/Nuxt-Wissens: Composition API ↔ Hooks, Pinia ↔ Query + Zustand, Nuxt `pages/` ↔ TanStack `routes/`, `<script setup>` ↔ Funktionskomponenten, Vuetify/Nuxt UI ↔ shadcn/ui.
