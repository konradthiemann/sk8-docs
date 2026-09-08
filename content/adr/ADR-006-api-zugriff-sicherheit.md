---
id: ADR-006
title: API-Zugriff ohne Login – statischer API-Key, CORS, Rate-Limits
status: akzeptiert
date: 2026-09-07
agents: [architect]
tags: [backend, security, api, cors]
---

## Kontext

Multi-User und Authentifizierung sind explizit Non-Goals. Die Backend-API ist auf Railway aber **öffentlich erreichbar**, und einige Endpunkte lösen bezahlte KI-Aufrufe aus. „Gar keine Absicherung" ist deshalb keine Option.

## Entscheidung

1. **Statischer API-Key** über Header `X-Api-Key`, Wert aus Env `APP_API_KEY`. Umgesetzt mit dem Symfony-Security-**Access-Token-Authenticator** und einem eigenen `AccessTokenHandler`, der den Key vergleicht und einen technischen `ApiUser` zurückgibt. Geschützt: alle `/api/*`-Routen; frei: `/api/health` und `/api/doc`.
2. **Frontends** bekommen den Key zur Build-Zeit (`VITE_API_KEY`). Der Key landet damit im Bundle – ein **bewusst akzeptiertes Restrisiko** für eine Single-User-App ohne fremde personenbezogene Daten. Rotation: neuen Wert in Railway setzen, Redeploy.
3. **CORS-Allowlist** (NelmioCorsBundle) auf die drei Frontend-Origins je Environment.
4. **Rate-Limiter** (Symfony RateLimiter, Cache-Storage) auf alle KI-Endpunkte als Kostenschutz, z. B. 30 Anfragen/Stunde.
5. **Anthropic-Key** existiert nur im Backend, niemals im Frontend.

## Alternativen

| Alternative | Bewertung |
|---|---|
| Vollständiges Login (JWT/Session) | Non-Goal; erheblicher Aufwand ohne Nutzen für einen Nutzer. |
| Basic Auth / Zugriffsschutz vor Railway (z. B. Cloudflare Access) | Zusätzlicher Anbieter; PWA-Installation und `fetch` werden umständlich. |
| Keine Absicherung | Offene KI-Endpunkte = offene Rechnung. |

## Konsequenzen

- Jeder Functional-Test setzt den Header; ein Test prüft explizit die 401-Antwort ohne Key.
- Wird die Plattform je mehrbenutzerfähig, ersetzt ein User-Entity-basiertes Login den `AccessTokenHandler` – die Security-Konfiguration ist dafür bereits die richtige Stelle.

## Was du daraus lernst

Symfony Security *ohne* User-Tabelle, das Authenticator-System, warum CORS ein Browser-Schutz und kein Server-Schutz ist.
