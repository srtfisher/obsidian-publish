<?php

declare(strict_types=1);

namespace App\Markdown\Callout;

use InvalidArgumentException;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

final class CalloutRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        if (! $node instanceof Callout) {
            throw new InvalidArgumentException('Expected '.Callout::class);
        }

        return new HtmlElement('div', ['class' => 'callout', 'data-callout' => $node->type], [
            new HtmlElement('div', ['class' => 'callout-title'], Xml::escape($node->title)),
            new HtmlElement('div', ['class' => 'callout-content'], $childRenderer->renderNodes($node->children())),
        ]);
    }
}
