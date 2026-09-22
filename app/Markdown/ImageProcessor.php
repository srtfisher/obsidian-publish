<?php

declare(strict_types=1);

namespace App\Markdown;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;

/**
 * Points relative images at the note's uploaded assets, applies size hints and links each image to its full size.
 */
final readonly class ImageProcessor
{
    public function __construct(private string $assetBaseUrl) {}

    public function __invoke(DocumentParsedEvent $event): void
    {
        $images = [];

        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Image) {
                $images[] = $node;
            }
        }

        foreach ($images as $image) {
            if (! preg_match('#^([a-z][a-z0-9+.-]*:|/)#i', $image->getUrl())) {
                $image->setUrl(rtrim($this->assetBaseUrl, '/').'/'.rawurlencode(rawurldecode($image->getUrl())));
            }

            if (preg_match('/^w=(\d{1,5})$/', (string) $image->getTitle(), $matches)) {
                $image->setTitle(null);
                $image->data->set('attributes/width', $matches[1]);
            }

            if (! $image->parent() instanceof Link) {
                $link = new Link($image->getUrl());
                $link->data->set('attributes/target', '_blank');
                $image->replaceWith($link);
                $link->appendChild($image);
            }
        }
    }
}
