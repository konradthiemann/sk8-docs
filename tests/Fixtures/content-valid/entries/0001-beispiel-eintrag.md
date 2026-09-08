---
id: 1
title: Beispiel-Eintrag
date: 2026-09-07
type: feature
agents: [architect, implementer]
repos: [sk8-docs]
tags: [symfony, fixture]
summary: Ein gültiger Eintrag für die Tests.
learning_path: 2
adrs: [ADR-001]
---

## Was

Ein Beispiel mit einem Diagramm.

```mermaid
graph TD
  A --> B
```

## Warum

Weil Tests Fixtures brauchen, siehe [ADR-001](../adr/ADR-001-erste-entscheidung.md).

## Wie

```php
<?php echo 'Hallo';
```

## Tests

| Datei | Beweist |
|---|---|
| `FooTest.php` | etwas |

## Lernpunkte

- Punkt eins
- Punkt zwei
