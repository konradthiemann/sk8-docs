<?php

declare(strict_types=1);

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'highlight.js' => [
        'version' => '11.12.0',
    ],
    'chart.js' => [
        'version' => '4.5.1',
    ],
    '@kurkle/color' => [
        'version' => '0.3.4',
    ],
    'mermaid' => [
        'version' => '11.4.1',
    ],
    'dayjs' => [
        'version' => '1.11.13',
    ],
    'khroma' => [
        'version' => '2.1.0',
    ],
    'dompurify' => [
        'version' => '3.2.1',
    ],
    '@iconify/utils' => [
        'version' => '2.1.33',
    ],
    '@braintree/sanitize-url' => [
        'version' => '7.1.0',
    ],
    'd3' => [
        'version' => '7.9.0',
    ],
    'lodash-es/memoize.js' => [
        'version' => '4.17.21',
    ],
    'lodash-es/merge.js' => [
        'version' => '4.17.21',
    ],
    'marked' => [
        'version' => '13.0.3',
    ],
    'ts-dedent' => [
        'version' => '2.2.0',
    ],
    'roughjs' => [
        'version' => '4.6.6',
    ],
    'stylis' => [
        'version' => '4.3.4',
    ],
    'lodash-es/isEmpty.js' => [
        'version' => '4.17.21',
    ],
    'katex' => [
        'version' => '0.16.11',
    ],
    'mermaid/dist/chunks/mermaid.core/dagre-4EVJKHTY.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/c4Diagram-6F5ED5ID.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/flowDiagram-7ASYPVHJ.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/erDiagram-6RL3IURR.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/gitGraphDiagram-NRZ2UAAF.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/ganttDiagram-NTVNEXSI.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/infoDiagram-A4XQUW5V.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/pieDiagram-YF2LJOPJ.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/quadrantDiagram-OS5C2QUG.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/xychartDiagram-6QU3TZC5.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/requirementDiagram-MIRIMTAZ.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/sequenceDiagram-G6AWOVSC.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/classDiagram-LNE6IOMH.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/classDiagram-v2-MQ7JQ4JX.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/stateDiagram-MAYHULR4.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/stateDiagram-v2-4JROLMXI.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/journeyDiagram-G5WM74LC.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/timeline-definition-U7ZMHBDA.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/mindmap-definition-GWI6TPTV.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/kanban-definition-QRCXZQQD.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/sankeyDiagram-Y46BX6SQ.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/diagram-QW4FP2JN.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/blockDiagram-ZHA2E4KO.mjs' => [
        'version' => '11.4.1',
    ],
    'mermaid/dist/chunks/mermaid.core/architectureDiagram-UYN6MBPD.mjs' => [
        'version' => '11.4.1',
    ],
    'debug' => [
        'version' => '4.3.7',
    ],
    'd3-array' => [
        'version' => '3.2.4',
    ],
    'd3-axis' => [
        'version' => '3.0.0',
    ],
    'd3-brush' => [
        'version' => '3.0.0',
    ],
    'd3-chord' => [
        'version' => '3.0.1',
    ],
    'd3-color' => [
        'version' => '3.1.0',
    ],
    'd3-contour' => [
        'version' => '4.0.2',
    ],
    'd3-delaunay' => [
        'version' => '6.0.4',
    ],
    'd3-dispatch' => [
        'version' => '3.0.1',
    ],
    'd3-drag' => [
        'version' => '3.0.0',
    ],
    'd3-dsv' => [
        'version' => '3.0.1',
    ],
    'd3-ease' => [
        'version' => '3.0.1',
    ],
    'd3-fetch' => [
        'version' => '3.0.1',
    ],
    'd3-force' => [
        'version' => '3.0.0',
    ],
    'd3-format' => [
        'version' => '3.1.0',
    ],
    'd3-geo' => [
        'version' => '3.1.1',
    ],
    'd3-hierarchy' => [
        'version' => '3.1.2',
    ],
    'd3-interpolate' => [
        'version' => '3.0.1',
    ],
    'd3-path' => [
        'version' => '3.1.0',
    ],
    'd3-polygon' => [
        'version' => '3.0.1',
    ],
    'd3-quadtree' => [
        'version' => '3.0.1',
    ],
    'd3-random' => [
        'version' => '3.0.1',
    ],
    'd3-scale' => [
        'version' => '4.0.2',
    ],
    'd3-scale-chromatic' => [
        'version' => '3.1.0',
    ],
    'd3-selection' => [
        'version' => '3.0.0',
    ],
    'd3-shape' => [
        'version' => '3.2.0',
    ],
    'd3-time' => [
        'version' => '3.1.0',
    ],
    'd3-time-format' => [
        'version' => '4.1.0',
    ],
    'd3-timer' => [
        'version' => '3.0.1',
    ],
    'd3-transition' => [
        'version' => '3.0.1',
    ],
    'd3-zoom' => [
        'version' => '3.0.0',
    ],
    'dagre-d3-es/src/dagre/index.js' => [
        'version' => '7.0.11',
    ],
    'dagre-d3-es/src/graphlib/json.js' => [
        'version' => '7.0.11',
    ],
    'dagre-d3-es/src/graphlib/index.js' => [
        'version' => '7.0.11',
    ],
    'uuid' => [
        'version' => '9.0.1',
    ],
    '@mermaid-js/parser' => [
        'version' => '0.3.0',
    ],
    'dayjs/plugin/isoWeek.js' => [
        'version' => '1.11.13',
    ],
    'dayjs/plugin/customParseFormat.js' => [
        'version' => '1.11.13',
    ],
    'dayjs/plugin/advancedFormat.js' => [
        'version' => '1.11.13',
    ],
    'cytoscape' => [
        'version' => '3.30.4',
    ],
    'cytoscape-cose-bilkent' => [
        'version' => '4.1.0',
    ],
    'd3-sankey' => [
        'version' => '0.12.3',
    ],
    'lodash-es/clone.js' => [
        'version' => '4.17.21',
    ],
    'cytoscape-fcose' => [
        'version' => '2.2.0',
    ],
    'ms' => [
        'version' => '2.1.3',
    ],
    'internmap' => [
        'version' => '2.0.3',
    ],
    'delaunator' => [
        'version' => '5.0.0',
    ],
    'lodash-es' => [
        'version' => '4.17.21',
    ],
    'langium' => [
        'version' => '3.0.0',
    ],
    '@mermaid-js/parser/dist/chunks/mermaid-parser.core/info-46DW6VJ7.mjs' => [
        'version' => '0.3.0',
    ],
    '@mermaid-js/parser/dist/chunks/mermaid-parser.core/packet-W2GHVCYJ.mjs' => [
        'version' => '0.3.0',
    ],
    '@mermaid-js/parser/dist/chunks/mermaid-parser.core/pie-BEWT4RHE.mjs' => [
        'version' => '0.3.0',
    ],
    '@mermaid-js/parser/dist/chunks/mermaid-parser.core/architecture-I3QFYML2.mjs' => [
        'version' => '0.3.0',
    ],
    '@mermaid-js/parser/dist/chunks/mermaid-parser.core/gitGraph-YCYPL57B.mjs' => [
        'version' => '0.3.0',
    ],
    'cose-base' => [
        'version' => '2.2.0',
    ],
    'robust-predicates' => [
        'version' => '3.0.0',
    ],
    '@chevrotain/regexp-to-ast' => [
        'version' => '13.2.0',
    ],
    'chevrotain' => [
        'version' => '11.0.3',
    ],
    'chevrotain-allstar' => [
        'version' => '0.3.1',
    ],
    'vscode-languageserver-types' => [
        'version' => '3.18.3',
    ],
    'vscode-jsonrpc/lib/common/cancellation.js' => [
        'version' => '9.0.2',
    ],
    'vscode-languageserver-textdocument' => [
        'version' => '1.0.11',
    ],
    'vscode-uri' => [
        'version' => '3.0.8',
    ],
    'vscode-jsonrpc/lib/common/events.js' => [
        'version' => '9.0.2',
    ],
    'layout-base' => [
        'version' => '2.0.1',
    ],
    '@chevrotain/utils' => [
        'version' => '11.0.3',
    ],
    '@chevrotain/gast' => [
        'version' => '11.0.3',
    ],
    '@chevrotain/cst-dts-gen' => [
        'version' => '11.0.3',
    ],
    'lodash-es/map.js' => [
        'version' => '4.17.21',
    ],
    'lodash-es/filter.js' => [
        'version' => '4.17.21',
    ],
    'lodash-es/min.js' => [
        'version' => '4.17.21',
    ],
    'lodash-es/flatMap.js' => [
        'version' => '4.17.21',
    ],
    'lodash-es/uniqBy.js' => [
        'version' => '4.17.21',
    ],
    'lodash-es/flatten.js' => [
        'version' => '4.17.21',
    ],
    'lodash-es/forEach.js' => [
        'version' => '4.17.21',
    ],
    'lodash-es/reduce.js' => [
        'version' => '4.17.21',
    ],
];
