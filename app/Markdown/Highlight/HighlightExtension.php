<?php

declare(strict_types=1);

namespace App\Markdown\Highlight;

use League\CommonMark\Delimiter\DelimiterInterface;
use League\CommonMark\Delimiter\Processor\DelimiterProcessorInterface;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Inline\AbstractStringContainer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Renders Obsidian's `==highlight==` syntax as `<mark>`.
 */
final class HighlightExtension implements DelimiterProcessorInterface, ExtensionInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addDelimiterProcessor($this);
        $environment->addRenderer(Mark::class, $this);
    }

    public function getOpeningCharacter(): string
    {
        return '=';
    }

    public function getClosingCharacter(): string
    {
        return '=';
    }

    public function getMinLength(): int
    {
        return 2;
    }

    public function getDelimiterUse(DelimiterInterface $opener, DelimiterInterface $closer): int
    {
        return $opener->getLength() >= 2 && $closer->getLength() >= 2 ? 2 : 0;
    }

    public function process(AbstractStringContainer $opener, AbstractStringContainer $closer, int $delimiterUse): void
    {
        $mark = new Mark;

        $node = $opener->next();
        while ($node !== null && $node !== $closer) {
            $next = $node->next();
            $mark->appendChild($node);
            $node = $next;
        }

        $opener->insertAfter($mark);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        return new HtmlElement('mark', [], $childRenderer->renderNodes($node->children()));
    }
}
