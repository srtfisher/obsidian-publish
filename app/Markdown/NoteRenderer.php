<?php

declare(strict_types=1);

namespace App\Markdown;

use App\Markdown\Callout\CalloutExtension;
use App\Markdown\Highlight\HighlightExtension;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Tempest\Highlight\CommonMark\HighlightExtension as CodeHighlightExtension;

/**
 * Converts a published note's Markdown into the HTML shown on its public page.
 */
final class NoteRenderer
{
    public function render(string $markdown, string $assetBaseUrl): string
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new CodeHighlightExtension);
        $environment->addExtension(new CalloutExtension);
        $environment->addExtension(new HighlightExtension);
        $environment->addEventListener(DocumentParsedEvent::class, new ImageProcessor($assetBaseUrl));

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }
}
