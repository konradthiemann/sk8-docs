---
id: ADR-010
title: KI-Integration – nur im Backend, kostenbewusst, cachend
status: akzeptiert
date: 2026-09-07
agents: [architect]
tags: [ki, anthropic, backend, kosten]
---

## Kontext

KI-Aufgaben: Etiketten-Scan per Vision, Mustererkennung über App-Grenzen, Prognosen, adaptive Trainingspläne, Mahlzeitenvorschläge. Vorgabe: kleinstes ausreichendes Modell, Antworten cachen, Batch-Verarbeitung, API-Keys kommen später vom Entwickler.

## Entscheidung

1. **Alle KI-Aufrufe laufen im Backend** (`src/Ai/`). Frontends kennen weder Key noch Modell.
2. **Symfony HttpClient** direkt gegen die Anthropic Messages API statt eines SDKs – die HTTP-Ebene (Header, Retry, Timeouts, Streaming-Verzicht) ist Lernstoff und hält Abhängigkeiten klein. Ein SDK-Wechsel bleibt hinter dem Interface `AiClientInterface` möglich.
3. **Modellwahl konfigurierbar** über Env (`AI_MODEL_VISION`, `AI_MODEL_TEXT`). Standard: das kleinste Modell, das die Aufgabe zuverlässig löst (Kandidat: Haiku 4.5). Die konkrete Modell-ID und der Preis werden **bei der Implementierung** gegen die aktuelle Anthropic-Dokumentation verifiziert, nicht aus dem Gedächtnis gesetzt.
4. **Kostenkontrolle:** Response-Cache in PostgreSQL (`ai_response_cache`, Schlüssel = SHA-256 aus Modell + Prompt + Bild-Hash), Prompt-Caching für lange System-Prompts, Batch-Verarbeitung nicht-interaktiver Analysen über Messenger (nächtlicher Lauf), Rate-Limits (ADR-006), Token-Zähler in `ai_usage`-Tabelle für ein Kosten-Dashboard.
5. **Ohne Key lauffähig:** Fehlt `ANTHROPIC_API_KEY`, liefern KI-Endpunkte `503` mit deutscher Meldung; alle übrigen Funktionen arbeiten normal. Ein `FakeAiClient` bedient Tests und lokale Entwicklung.
6. **Etiketten-Scan** (Vorbild: Doewe-Receipt-Scanning): Bild → Vision-Prompt mit striktem JSON-Schema (Nährwerte je 100 g, Portionsgröße, Zutaten) → Validierung im Backend → Vorschlag, den der Nutzer im Frontend bestätigt oder korrigiert. Nie ungeprüft speichern.

## Alternativen

Offizielles PHP-SDK (später möglich), KI-Aufrufe im Frontend (Key-Leck, keine Kostenkontrolle), größere Modelle als Standard (unnötig teuer für strukturierte Extraktion).

## Konsequenzen

- Jedes KI-Feature liefert Tests gegen den `FakeAiClient` plus einen aufgezeichneten Beispiel-Response.
- Kosten werden pro Feature im Doku-Eintrag geschätzt (Tokens × Preis).

## Was du daraus lernst

HTTP-Client-Konfiguration in Symfony, Interfaces für austauschbare Adapter, Caching-Strategien, Kosten als Architekturtreiber.
