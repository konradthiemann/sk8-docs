<?php

declare(strict_types=1);

namespace App\Content;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Markdown → HTML for content files (GFM tables, task lists, strikethrough, autolinks,
 * heading permalinks, Mermaid fences, internal link rewriting).
 *
 * The content is written by the project itself, so raw HTML is allowed and nothing is sanitised.
 */
final class MarkdownRenderer
{
    private const string LEARNING_SECTION = 'lernpunkte';

    private readonly MarkdownConverter $converter;

    public function __construct(InternalLinkRewriter $linkRewriter)
    {
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => true,
            'heading_permalink' => [
                'html_class' => 'heading-permalink',
                'id_prefix' => '',
                'fragment_prefix' => '',
                'apply_id_to_heading' => true,
                'insert' => 'after',
                'min_heading_level' => 2,
                'max_heading_level' => 4,
                'symbol' => '#',
                'title' => 'Link zu diesem Abschnitt',
                'aria_hidden' => true,
            ],
            'table' => [
                'wrap' => ['enabled' => true, 'tag' => 'div', 'attributes' => ['class' => 'table-wrap']],
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TaskListExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new HeadingPermalinkExtension());

        // Higher priority than the core renderer; returns null for non-Mermaid fences.
        $environment->addRenderer(FencedCode::class, new MermaidFencedCodeRenderer(), 10);

        $environment->addEventListener(DocumentParsedEvent::class, static function (DocumentParsedEvent $event) use ($linkRewriter): void {
            foreach ($event->getDocument()->iterator() as $node) {
                if ($node instanceof Link) {
                    $node->setUrl($linkRewriter->rewrite($node->getUrl()));
                }
            }
        });

        $this->converter = new MarkdownConverter($environment);
    }

    public function render(string $markdown): string
    {
        $html = $this->converter->convert($markdown)->getContent();

        return $this->wrapLearningSection($html);
    }

    /**
     * Wraps "## Lernpunkte" and everything up to the next h2 into an admonition box.
     */
    private function wrapLearningSection(string $html): string
    {
        $pattern = \sprintf('#(<h2[^>]*\bid="%s"[^>]*>.*?</h2>\n?)(.*?)(?=<h2\b|\z)#s', self::LEARNING_SECTION);

        return preg_replace(
            $pattern,
            "<section class=\"admonition admonition--lernpunkte\">\n$1$2</section>\n",
            $html,
            1,
        ) ?? $html;
    }
}
