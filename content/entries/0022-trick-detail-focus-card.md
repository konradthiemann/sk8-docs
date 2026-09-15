---
id: 22
title: Trick-Detail, Verlaufsdiagramm und Fokus-Empfehlung
date: 2026-09-15
type: feature
agents: [architect, uiux, tester, implementer]
repos: [sk8-skate]
tags: [recharts, accessibility, tanstack-query, openapi-fetch]
summary: Ein Tipp auf einen Trick öffnet jetzt seine Detailseite mit Zahlenblock, Voraussetzungen, Freischaltungen und einem Verlaufsdiagramm samt Tabelle, während eine Fokus-Karte auf „Tricks" den nächsten empfohlenen Trick mit Begründung, Dosierung und Pausenhinweis nennt und denselben Trick in Graph und Liste markiert.
adrs: [ADR-009]
tickets: [T-0204]
---

## Was

„Tricks" zeigte bisher nur den Baum aus `T-0203`. Jetzt steht direkt unter der Überschrift eine
Fokus-Karte, die aus `GET /api/trick-recommendation` den nächsten sinnvollen Trick nennt – mit
Begründung, Dosierung („15–30 Versuche · 10–20 Minuten") und, falls nötig, einem Pausenhinweis für das
Knie. Genau dieser Trick (und bis zu ein weiterer) ist im Graph und in der Liste zusätzlich als Ring mit
Pfeil-Symbol markiert. Ein Tipp auf einen beliebigen Trick öffnet `/tricks/{slug}`: Name, Status, Kategorie,
ein Zahlenblock (Versuche, Treffer, beide Erfolgsquoten, erste Landung, letzter Übungstag),
Voraussetzungen und Freischaltungen als Links, und zuletzt der Verlauf als Diagramm plus Tabelle. Ein
unbekannter Slug zeigt eine eigene „Diesen Trick gibt es nicht"-Meldung statt des allgemeinen Fehlertexts.

```mermaid
flowchart LR
  A["/tricks öffnen"] -->|"GET /api/trick-tree"| B["Baum + Liste<br/>(T-0203, unverändert)"]
  A -->|"GET /api/trick-recommendation<br/>eigene Query"| C["FocusCard:<br/>primary, secondary, pauseHint"]
  C -->|"markiert dieselben Slugs in"| B
  B -->|"Tipp auf Knoten/Zeile"| D["/tricks/{slug}"]
  D -->|"GET /api/tricks/{slug}"| E{"200 / 404 / Fehler"}
  E -->|"200"| F["Kopf, Zahlen, Refs,<br/>SuccessRateChart + TrickHistoryTable"]
  E -->|"404"| G["„Diesen Trick gibt es nicht""]
  E -->|"Netzfehler/5xx"| H["Alert + Erneut versuchen"]
```

## Warum

Der Trick-Tree aus `T-0203` zeigt nur Zustände. Diese Ergänzung liefert laut Ticket „die zwei Dinge, die
ihn benutzbar machen: die Entwicklung … und die Handlungsanweisung". Der Pausenhinweis ist dabei keine
Nebensache: `CLAUDE.md` macht Knieschutz zur harten Nebenbedingung, und die Fokus-Karte ist der einzige
Ort, an dem die App vor der Session aktiv „spricht" – deshalb sitzt der Hinweis dort, nicht in einem
Untermenü.

Zwei Entscheidungen weichen bewusst vom Ticket-Wortlaut ab, beide im Code nachprüfbar:

1. **Recharts-Korrektur.** Das Ticket verlangt wörtlich `aria-hidden="true"` auf dem `<LineChart>`
   selbst. Recharts 3.10.1 kennt dieses Prop dort gar nicht (`BaseChartProps` in
   `node_modules/recharts/types/util/types.d.ts:1217-1240` listet eine feste Prop-Menge ohne
   `aria-hidden`) – der Ticket-Wortlaut kompiliert unter „TypeScript strict, keine ungeprüften Casts"
   nicht. Selbst mit einem Cast wäre er unvollständig: `CartesianChart.d.ts:7` setzt
   `accessibilityLayer: true` fest verdrahtet, was die Chart-SVG trotz eines `aria-hidden`-Elternelements
   weiterhin per Tab erreichbar ließe – ein Verstoß gegen genau die MDN-Regel, die unten als
   Lernpunkt 1 belegt ist. Umgesetzt stattdessen: `aria-hidden` auf `<ResponsiveContainer>` (rendert
   einen `<div>`, `ResponsiveContainer.d.ts:4` erweitert regulär `React.HTMLAttributes`) plus
   `accessibilityLayer={false}` auf `<LineChart>`.
2. **`ArrowUpRight` statt `Target` für die Fokus-Markierung.** Das Ticket schreibt `Target` vor – aber
   `TRICK_STATUS_META.uebe.icon` ist bereits `Target` (`status.ts:34`, aus `T-0203`). Ein Fokus-Trick mit
   Status „Übe ich" hätte zwei gleiche Symbole mit unterschiedlicher Bedeutung auf demselben Knoten
   gezeigt. Die `uiux`-Rolle hat dagegen entschieden, siehe Lernpunkt 4 unten.

Eine dritte, kleinere Entscheidung betrifft keine Abweichung vom Ticket, sondern eine Wiederverwendungsfrage
aus `T-0202`: `ApiRequestError` (Status + Fehlercode aus einer `{data, error, response}`-Antwort lesen)
lebte bisher feature-lokal in `src/features/sessions/api.ts`, bewusst ohne Extraktion – zu diesem
Zeitpunkt gab es nur einen Aufrufer. Mit `fetchTrickDetail()` kommt jetzt ein zweiter, echter
Verwendungsort für exakt dasselbe Verhalten (404 von jedem anderen Fehler unterscheiden) dazu. YAGNI
schützt eine Abstraktion ohne zweiten Anwendungsfall – der ist jetzt real, nicht mehr hypothetisch, also
wandert die Klasse nach `src/lib/api/errors.ts` und wird von beiden Feature-Ordnern importiert. Dieselbe
Regel wurde damit zweimal angewendet, einmal mit dem Ergebnis „bleibt lokal" (`T-0202`), einmal mit dem
Ergebnis „wird extrahiert" (`T-0204`) – der Unterschied ist nicht die Regel, sondern ob ein zweiter
Aufrufer bereits existiert.

## Wie

**1. `ApiRequestError` wandert in einen gemeinsamen Ort, weil ein zweiter Aufrufer real ist.**
`src/lib/api/errors.ts` ist 1:1 aus `sessions/api.ts` verschoben; `tricks/api.ts` nutzt sie für denselben
Zweck an einem zweiten Endpunkt:

```ts
// src/features/tricks/api.ts:52-60
export async function fetchTrickDetail(slug: string): Promise<TrickDetailResponse> {
  const { data, error, response } = await api.GET("/api/tricks/{slug}", {
    params: { path: { slug } },
  });
  if (error) {
    // response.status unterscheidet 404 ("gibt es nicht") von jedem anderen
    // Fehler (allgemeiner Fehlertext) - Verhalten identisch zu sessions/api.ts
    throw new ApiRequestError(response.status, error);
  }
  return data;
}
```

`sessions/api.ts` re-exportiert die Klasse, statt sie neu zu importieren und drei bestehende
Screens (`SessionDetailScreen.tsx` u. a.) zusätzlich anzufassen – kleinerer Diff, identisches Ergebnis:
eine einzige Klassendefinition für den App-weiten Fehlervertrag `{"error": "<code>", "violations"?: [...]}`.

**2. Zwei unabhängige Ladezustände statt eines gemeinsamen.** AK 10 verlangt ausdrücklich, dass ein
Fehler der Empfehlung den Trick-Tree nicht mitreißen darf. Das wird strukturell erzwungen, nicht per
Disziplin – zwei getrennte `useQuery`-Aufrufe, kein `Promise.all`:

```tsx
// src/features/tricks/components/TrickTreeScreen.tsx:56-62
const query = useQuery({ queryKey: trickKeys.tree(), queryFn: fetchTrickTree });
// AK 10: zwei getrennte Queries, zwei getrennte Fehlerbehandlungen - ein
// Fehler der Empfehlung darf den Trick-Tree nie mitreißen (design.md §5.4/§6).
const recommendationQuery = useQuery({
  queryKey: trickKeys.recommendation(),
  queryFn: fetchTrickRecommendation,
});
```

`FocusCard` bekommt daraus nur Props (`recommendation`, `isLoading`, `isError`, `onRetry`), keinen
eigenen Query-Zugriff – dieselbe Trennung „Screens laden, Feature-Komponenten stellen dar", die
`TrickTreeGraph`/`TrickTreeList` in `T-0203` bereits etabliert haben. Das hält `FocusCard.test.tsx` ohne
Netzwerk-Mocking testbar.

**3. Diagramm aus dem Vorlese- und Tastaturpfad entfernen, korrekt statt nur wörtlich.** Die Korrektur
aus dem „Warum"-Abschnitt landet so im Code:

```tsx
// src/features/tricks/components/SuccessRateChart.tsx:44-56
return (
  <ResponsiveContainer aria-hidden="true" width="100%" height={200}>
    <LineChart
      data={data}
      accessibilityLayer={false} // sonst bleibt das SVG trotz aria-hidden per Tab erreichbar
      margin={{ top: 8, right: 16, bottom: 8, left: 8 }}
    >
      <XAxis dataKey="sessionDate" tickFormatter={formatShortDate} />
      <YAxis domain={[0, 100]} tickFormatter={formatPercentTick} />
      {data.length > 0 && <Line dataKey="successRatePercent" dot />}
      <ReferenceLine y={masteryRate * 100} label="sitzt ab hier" />
    </LineChart>
  </ResponsiveContainer>
);
```

`masteryRate` kommt als eigenes Zahlenargument statt des ganzen `TrickPolicyView`-Objekts (spart
`SuccessRateChart` einen Feldzugriff) und wird ungerundet mit 100 multipliziert, damit die Linie exakt
auf der Skala sitzt (AK 13: „nicht bei einer festen Zahl"). `TrickDetailScreen.tsx` ruft die Komponente
erst ab zwei Verlaufseinträgen auf – eine Linie durch einen einzigen Punkt wäre keine sinnvolle Aussage,
das entscheidet der Aufrufer, nicht `SuccessRateChart` selbst.

**4. Ein zweites Symbol, weil ein wiederverwendetes zwei Bedeutungen trüge.** `status.ts:34` legt
`Target` bereits als Symbol für den Status „Übe ich" fest. Die Fokus-Markierung in Graph und Liste nutzt
stattdessen `ArrowUpRight`:

```tsx
// src/features/tricks/components/TrickTreeGraph.tsx:65-67
{data.isFocused && (
  // nicht Target: der Statusbadge "Übe ich" zeigt Target bereits (status.ts:34)
  <ArrowUpRight aria-hidden="true" className="absolute top-1 right-1 size-3.5 text-primary" />
)}
```

Der Ring (`ring-2 ring-primary`) und der Textbadge „Als Nächstes" (nur beim `primary`-Trick) bleiben wie
im Ticket. Für einen `secondary`-Fokus-Trick ohne Textbadge ist das Icon aber die einzige nicht-farbliche
Unterscheidung – mit `Target` wäre sie mehrdeutig gewesen. Begründung und Quelle stehen in Lernpunkt 4.

**5. Fünf Testkollisionen im Prüflauf gefunden und behoben – keine davon eine Implementierungslücke.**
Ein neuer `beforeEach` in `TrickTreeScreen.test.tsx` registrierte für jeden Test einen
Empfehlungs-Standardwert; dessen Beispiel-Slugs „50-50"/„Boardslide" waren zufällig identisch mit
Slugs, die mehrere unabhängige `T-0203`-Baum-Fixtures bereits verwendeten. Sobald `<FocusCard>`
mitrenderte, fanden ungescopte `screen.getByText(...)`/`getByRole(...)`-Abfragen denselben Text zweimal
und warfen. Die Fixture-Werte wurden auf erkennbar synthetische Platzhalter umbenannt
(`focus-example-primary`/„Fokus-Beispiel A"), und die eine Abfrage, die den Überlapp bewusst prüft
(Kriterium 12, gleicher Slug im Baum **und** in der Empfehlung), wurde eingegrenzt:

```ts
// TrickTreeScreen.test.tsx, Kriterium 12 (nach der Korrektur)
within(screen.getByRole("list", { name: "Trick-Tree als Liste" })).getByRole("link", {
  name: /50-50/,
});
```

Dieselbe Ursache traf `TrickDetailScreen.test.tsx`: `progress.recentSuccessRate` und der jüngste
Verlaufseintrag sind in der Fixture bewusst realistisch identisch (derselbe Wert, aus derselben
Session) – zwei Stellen im DOM zeigen dieselbe Zahl. Auch hier wurde nicht die Fixture „unrealistischer"
gemacht, sondern die Abfrage auf die jeweilige Zahlenblock-Kachel gescopt
(`within(screen.getByText(label).parentElement)`). Keine Assertion wurde abgeschwächt oder entfernt –
die Regel „Tests nie entschärfen, um grün zu werden" blieb unangetastet, es wurde nur eindeutig gemacht,
*wo* im DOM gesucht wird.

**Nebenbei erledigt, nicht Kern dieses Tickets:** `src/components/ui/table.tsx` (shadcn, manuell gebaut,
kein Netzwerkzugriff für den Generator verfügbar) und `src/lib/api/errors.ts` wurden per `/sync-frontends`
byte-identisch nach `sk8-nutrition` und `sk8-habits` gespiegelt – Letzteres, weil `src/lib/**` laut
`.claude/rules/react-frontend.md` ebenfalls zur „byte-identisch"-Liste gehört, nicht nur `src/components/ui/`.
Beide Apps liefen danach unverändert mit **59 von 59 Tests grün**.

## Tests

| Testdatei | Beweist |
|---|---|
| `TrickDetailScreen.test.tsx` | Kriterien 1–7: Aufbau (Kopf, Zahlen, Refs, Verlauf), Leerfälle (Beschreibung/Verlauf), 404 vs. allgemeiner Fehler mit „Erneut versuchen", Voraussetzung als Link |
| `FocusCard.test.tsx` | Kriterien 8, 9, 11: Empfehlung mit Begründung/Dosierung, Leertext bei `primary: null`, Pausenhinweis mit `role="alert"`; zusätzlich Laden-/Fehlerzustand |
| `TrickTreeScreen.test.tsx` | Kriterien 10, 12 (Erweiterung von `T-0203`): Empfehlungsfehler reißt den Baum nicht mit; genau zwei markierte Einträge, nur der erste mit Textbadge |
| `TrickHistoryTable.test.tsx` | Spaltenreihenfolge (Datum/Versuche/Treffer/Quote/Notiz), deutsches Datumsformat, Bindestrich statt leerer Notiz-Zelle |
| `SuccessRateChart.test.tsx` | Kriterium 13: Referenzlinie folgt `masteryRate` statt einer festen Zahl; `aria-hidden` auf dem Container, kein Absturz bei leerem Verlauf |

Ausführen: `pnpm test -- --run`. Eigener Prüflauf zu diesem Eintrag: **31 Testdateien, 251 Tests, alle
grün** (keine Regression gegenüber `T-0201`–`T-0203`); `pnpm lint` (Biome, 128 Dateien) und
`pnpm typecheck` (`tsc --noEmit`) beide mit 0 Fehlern.

## Lernpunkte

1. **`aria-hidden` und Tastaturfokus müssen zusammen geprüft werden.** Ein Element vor Screenreadern zu
   verstecken reicht nicht, wenn ein Kindelement weiterhin per Tab erreichbar bleibt.
   Fundstelle: `src/features/tricks/components/SuccessRateChart.tsx:45` (`aria-hidden="true"` auf
   `<ResponsiveContainer>`) und `:48` (`accessibilityLayer={false}`). Laut [MDN, ARIA
   `aria-hidden`](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Attributes/aria-hidden):
   „`aria-hidden="true"` should not be used on elements that can receive focus. … since this attribute is
   inherited by an element's children, it should not be added onto the parent or ancestor of a focusable
   element." Genau dieser Fall läge vor, hätte man `aria-hidden` nur auf `<LineChart>` gesetzt, ohne
   `accessibilityLayer` abzuschalten. Brücke: Dasselbe Risiko kennst du aus jedem Vue-Overlay/Modal, das
   mit `aria-hidden` versteckt wird, aber noch einen fokussierbaren Button enthält – das Problem betrifft
   die Browser-Fokusreihenfolge, nicht den virtuellen DOM einer bestimmten Bibliothek.
2. **Mehrere `useQuery`-Aufrufe sind unabhängig, ohne besonderen Aufwand.** Fundstelle:
   `src/features/tricks/components/TrickTreeScreen.tsx:56` und `:59-62` – zwei `useQuery`-Hooks
   nebeneinander, jeder mit eigenem Lade-/Fehlerzustand. Laut [TanStack-Query-Doku zu Parallel
   Queries](https://tanstack.com/query/latest/docs/framework/react/guides/parallel-queries): „When the
   number of parallel queries does not change, there is no extra effort to use parallel queries. Just use
   any number of … `useQuery` … hooks side-by-side!" Brücke: TanStack Query gibt es unverändert als
   `@tanstack/vue-query` – dieselbe API, dieselbe Unabhängigkeit zweier `useQuery()`-Aufrufe würde in
   einer Vue-Komponente identisch funktionieren.
3. **`openapi-fetch` wirft nicht automatisch – Fehler sind ein Rückgabewert, kein Sprung in `catch`.**
   Fundstelle: `src/features/tricks/api.ts:52-60`, das `{ data, error, response }`-Tripel wird
   destrukturiert, `error` explizit geprüft. Laut [openapi-fetch-Doku](https://openapi-ts.dev/openapi-fetch/):
   „All methods return an object with data, error, and response" – `data` nur bei 2xx, `error` nur bei
   4xx/5xx, beide nie gleichzeitig gesetzt. Brücke: Nuxts `$fetch`/`ofetch` verhält sich umgekehrt – es
   wirft bei einem Nicht-2xx-Status standardmäßig eine `FetchError`, man bräuchte dort `try`/`catch` für
   denselben Fall, den `openapi-fetch` hier über ein normales `if (error)` löst.
4. **Ein wiederverwendetes Icon mit zwei Bedeutungen verletzt eine benannte Usability-Heuristik.**
   Fundstelle: `src/features/tricks/status.ts:34` (`Target` für Status „Übe ich") gegen
   `src/features/tricks/components/TrickTreeGraph.tsx:66` (`ArrowUpRight` für die Fokus-Markierung).
   Laut [Nielsen Norman Group, „Consistency and
   standards"](https://www.nngroup.com/articles/ten-usability-heuristics/): „Users should not have to
   wonder whether different words, situations, or actions mean the same thing." Zwei `Target`-Icons auf
   demselben Knoten mit unterschiedlicher Bedeutung („ich übe das" vs. „das ist als Nächstes dran")
   verletzen das direkt. Brücke: Dieselbe Abwägung triffst du in jeder Vue-Komponentenbibliothek
   (Heroicons, Lucide-Vue) genauso – die Regel ist unabhängig vom Framework, weil sie die Bedeutung von
   Symbolen betrifft, nicht ihre Implementierung.
5. **Ein Unterstrich-Präfix ist die vom Compiler selbst erkannte Ausnahme für einen bewusst ungenutzten
   Parameter.** Fundstelle: `src/features/tricks/components/FocusCard.tsx:35`
   (`onRetry: _onRetry` – die Prop bleibt Teil des Vertrags aus `design.md` §5.4, aber `ux.md` §3.3
   verlangt bewusst keinen sichtbaren Retry-Button, der sie aufruft). Laut [TypeScript-Doku zu
   `noUnusedParameters`](https://www.typescriptlang.org/tsconfig/#noUnusedParameters): „Parameters
   declaration with names starting with an underscore (`_`) are exempt from the unused parameter
   checking." Ohne das Präfix hätte `tsc --noEmit` hier einen Fehler gemeldet, obwohl das Ungenutzt-Sein
   in diesem Fall Absicht ist, keine Vergesslichkeit. Brücke: Dieselbe Compiler-Option und dieselbe
   Konvention gelten unverändert in jedem strikten Vue-/Nuxt-TS-Projekt – `tsconfig.json` ist hier
   framework-neutral.
