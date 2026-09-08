<?php

declare(strict_types=1);

namespace App\Content;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

/**
 * Renders ```mermaid fences as `<pre class="mermaid">` for the client-side Mermaid runtime.
 * Every other fence returns null so the default renderer produces `<pre><code class="language-x">`.
 */
final class MermaidFencedCodeRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?HtmlElement
    {
        if (!$node instanceof FencedCode) {
            throw new \InvalidArgumentException(\sprintf('Erwartet wurde ein FencedCode-Knoten, erhalten: %s.', get_debug_type($node)));
        }

        $infoWords = $node->getInfoWords();
        if (!isset($infoWords[0]) || 'mermaid' !== strtolower($infoWords[0])) {
            return null;
        }

        return new HtmlElement('pre', ['class' => 'mermaid'], Xml::escape($node->getLiteral()));
    }
}
