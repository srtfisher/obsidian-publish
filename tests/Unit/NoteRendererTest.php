<?php

declare(strict_types=1);

use App\Markdown\NoteRenderer;

function render(string $markdown): string
{
    return (new NoteRenderer)->render($markdown, 'https://n.test/storage/notes/abc');
}

it('renders GitHub flavored markdown', function (): void {
    $html = render("| a | b |\n|---|---|\n| 1 | 2 |\n\n- [x] done\n- [ ] todo\n\n~~gone~~");

    expect($html)
        ->toContain('<table>')
        ->toContain('<input checked="" disabled="" type="checkbox">')
        ->toContain('<del>gone</del>');
});

it('renders a callout with a title', function (): void {
    $html = render("> [!info] Wrong color name stored.\n> More detail.");

    expect($html)
        ->toContain('<div class="callout" data-callout="info">')
        ->toContain('<div class="callout-title">Wrong color name stored.</div>')
        ->toContain('<div class="callout-content">')
        ->toContain('More detail.')
        ->not->toContain('<blockquote>')
        ->not->toContain('[!info]');
});

it('renders a callout without a title using the type name', function (): void {
    $html = render("> [!WARNING]\n> Careful.");

    expect($html)
        ->toContain('data-callout="warning"')
        ->toContain('<div class="callout-title">Warning</div>')
        ->toContain('Careful.');
});

it('ignores callout fold markers', function (): void {
    expect(render("> [!tip]- Folded\n> Body"))
        ->toContain('<div class="callout-title">Folded</div>')
        ->toContain('Body');
});

it('keeps nested blocks inside a callout', function (): void {
    $html = render("> [!note]\n> - one\n> - two\n>\n> ```\n> code\n> ```");

    expect($html)->toContain('<ul>')->toContain('code');
});

it('leaves ordinary blockquotes alone', function (): void {
    expect(render('> just a quote'))->toContain('<blockquote>')->not->toContain('callout');
});

it('escapes the callout title', function (): void {
    expect(render('> [!info] <b>bold</b>'))->not->toContain('<b>');
});

it('renders highlights', function (): void {
    expect(render('some ==marked **text**== here'))
        ->toContain('<mark>marked <strong>text</strong></mark>');
});

it('does not treat a single equals sign as a highlight', function (): void {
    expect(render('a = b = c'))->not->toContain('<mark>');
});

it('rewrites relative image paths to the asset base url and links them', function (): void {
    $html = render('![shot](<1-CleanShot 2026.png>)');

    expect($html)
        ->toContain('src="https://n.test/storage/notes/abc/1-CleanShot%202026.png"')
        ->toContain('alt="shot"')
        ->toContain('href="https://n.test/storage/notes/abc/1-CleanShot%202026.png"')
        ->toContain('target="_blank"');
});

it('leaves absolute image urls alone', function (): void {
    expect(render('![x](https://example.com/a.png)'))
        ->toContain('src="https://example.com/a.png"');
});

it('turns a w=N title into a width', function (): void {
    expect(render('![x](<a.png> "w=300")'))
        ->toContain('width="300"')
        ->not->toContain('title=');
});

it('escapes raw html', function (): void {
    expect(render('<script>alert(1)</script>'))->not->toContain('<script>');
});

it('drops unsafe links', function (): void {
    expect(render('[x](javascript:alert(1))'))->not->toContain('javascript:');
});

it('highlights fenced code and escapes it', function (): void {
    $html = render("```php\n<?php echo '<b>'; ?>\n```\n\n```unknownlang\n<b>x</b>\n```");

    expect($html)
        ->toContain('<pre')
        ->not->toContain("'<b>'")
        ->not->toContain('<b>x</b>');
});
