<?php

declare(strict_types=1);

namespace App\Markdown\Callout;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerInterface;

/**
 * Turns Obsidian callouts (`> [!type] Title`) into styled callout blocks.
 */
final class CalloutExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, $this->onDocumentParsed(...));
        $environment->addRenderer(Callout::class, new CalloutRenderer);
    }

    private function onDocumentParsed(DocumentParsedEvent $event): void
    {
        $quotes = [];

        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof BlockQuote) {
                $quotes[] = $node;
            }
        }

        foreach ($quotes as $quote) {
            $this->convert($quote);
        }
    }

    private function convert(BlockQuote $quote): void
    {
        $paragraph = $quote->firstChild();

        if (! $paragraph instanceof Paragraph) {
            return;
        }

        $firstLine = [];

        foreach ($paragraph->children() as $child) {
            $firstLine[] = $child;

            if ($child instanceof Newline) {
                break;
            }
        }

        $text = implode('', array_map($this->plainText(...), $firstLine));

        if (! preg_match('/^\[!([A-Za-z][\w-]*)\][+-]?\s*(.*)$/s', $text, $matches)) {
            return;
        }

        $type = strtolower($matches[1]);
        $title = trim($matches[2]);

        foreach ($firstLine as $node) {
            $node->detach();
        }

        if ($paragraph->firstChild() === null) {
            $paragraph->detach();
        }

        $callout = new Callout($type, $title !== '' ? $title : ucfirst($type));

        foreach ($quote->children() as $child) {
            $callout->appendChild($child);
        }

        $quote->replaceWith($callout);
    }

    private function plainText(Node $node): string
    {
        if ($node instanceof Newline) {
            return '';
        }

        if ($node instanceof StringContainerInterface) {
            return $node->getLiteral();
        }

        return implode('', array_map($this->plainText(...), $node->children()));
    }
}
