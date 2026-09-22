import { describe, expect, it } from 'vitest';
import { buildMultipart, preprocess, stripFrontmatter, type ResolvedEmbed } from '../src/preprocess';

const vault: Record<string, ResolvedEmbed<string>> = {
    'CleanShot 2026-09-22 at 2.29.59 PM@2x.png': {
        key: 'CleanShot 2026-09-22 at 2.29.59 PM@2x.png',
        name: 'CleanShot 2026-09-22 at 2.29.59 PM@2x.png',
        isImage: true,
        file: 'shot-a',
    },
    'attachments/diagram.jpg': { key: 'attachments/diagram.jpg', name: 'diagram.jpg', isImage: true, file: 'diagram' },
    'Other Note': { key: 'Other Note.md', name: 'Other Note.md', isImage: false, file: 'note' },
};

const resolve = (linkpath: string) => vault[linkpath] ?? null;

describe('preprocess', () => {
    it('rewrites image embeds to uploaded asset names', () => {
        const result = preprocess('Selections: ![[CleanShot 2026-09-22 at 2.29.59 PM@2x.png]]', resolve);

        expect(result.markdown).toBe('Selections: ![CleanShot 2026-09-22 at 2.29.59 PM@2x](<1-CleanShot-2026-09-22-at-2.29.59-PM-2x.png>)');
        expect(result.assets).toEqual([{ name: '1-CleanShot-2026-09-22-at-2.29.59-PM-2x.png', file: 'shot-a' }]);
        expect(result.skipped).toEqual([]);
    });

    it('uploads an image embedded twice only once', () => {
        const result = preprocess('![[attachments/diagram.jpg]] and ![[attachments/diagram.jpg|alt]]', resolve);

        expect(result.assets).toHaveLength(1);
        expect(result.markdown).toBe('![diagram](<1-diagram.jpg>) and ![alt](<1-diagram.jpg>)');
    });

    it('turns size hints into a width title', () => {
        expect(preprocess('![[attachments/diagram.jpg|300]]', resolve).markdown).toBe('![diagram](<1-diagram.jpg> "w=300")');
        expect(preprocess('![[attachments/diagram.jpg|300x200]]', resolve).markdown).toBe('![diagram](<1-diagram.jpg> "w=300")');
    });

    it('handles standard markdown images with vault paths', () => {
        const result = preprocess('![A diagram|250](attachments/diagram.jpg) ![x](<attachments/diagram.jpg>)', resolve);

        expect(result.markdown).toBe('![A diagram](<1-diagram.jpg> "w=250") ![x](<1-diagram.jpg>)');
    });

    it('decodes percent-encoded markdown image paths', () => {
        const result = preprocess('![](CleanShot%202026-09-22%20at%202.29.59%20PM@2x.png)', resolve);

        expect(result.assets).toHaveLength(1);
    });

    it('leaves absolute image urls alone', () => {
        const markdown = '![remote](https://example.com/a.png)';

        expect(preprocess(markdown, resolve)).toEqual({ markdown, assets: [], skipped: [] });
    });

    it('reports embeds that are missing or not images', () => {
        const result = preprocess('![[missing.png]] ![[Other Note]]', resolve);

        expect(result.markdown).toBe('missing.png Other Note');
        expect(result.skipped).toEqual(['missing.png', 'Other Note']);
    });

    it('turns wikilinks into plain text', () => {
        expect(preprocess('See [[Other Note]], [[Other Note|the other one]] and [[Other Note#Setup]] or [[#Local]].', resolve).markdown)
            .toBe('See Other Note, the other one and Other Note or Local.');
    });

    it('removes comments', () => {
        expect(preprocess('Keep %%drop this%% this.\n%%\nmultiline\n%%\nEnd', resolve).markdown).toBe('Keep  this.\n\nEnd');
    });

    it('leaves code untouched', () => {
        const markdown = 'Use `[[not a link]]` here.\n\n```\n![[CleanShot 2026-09-22 at 2.29.59 PM@2x.png]] %%keep%%\n```';

        expect(preprocess(markdown, resolve)).toEqual({ markdown, assets: [], skipped: [] });
    });

    it('keeps callouts and other markdown as is', () => {
        const markdown = '> [!info] Wrong color name stored.\n\n### Heading\n\n* item\n\t* nested';

        expect(preprocess(markdown, resolve).markdown).toBe(markdown);
    });

    it('re-indents fenced code under a list item whose content is not indented', () => {
        const markdown = '* Profile PDS is now\n  ```\n{\n  "a": 1\n}\n  ```\n\nAfter';

        expect(preprocess(markdown, resolve).markdown)
            .toBe('* Profile PDS is now\n  ```\n  {\n    "a": 1\n  }\n  ```\n\nAfter');
    });

    it('leaves correctly indented fenced code alone', () => {
        const markdown = '* item\n  ```\n  code\n    more\n  ```';

        expect(preprocess(markdown, resolve).markdown).toBe(markdown);
    });
});

describe('stripFrontmatter', () => {
    it('removes yaml frontmatter', () => {
        expect(stripFrontmatter('---\npublish_id: abc\n---\n# Title\n')).toBe('# Title\n');
    });

    it('leaves notes without frontmatter alone', () => {
        expect(stripFrontmatter('# Title\n---\n')).toBe('# Title\n---\n');
    });
});

describe('buildMultipart', () => {
    it('encodes fields and files', () => {
        const { body, contentType } = buildMultipart(
            { title: 'Hi' },
            [{ field: 'assets[]', name: 'a.png', type: 'image/png', data: new Uint8Array([1, 2, 3]).buffer }],
            'BOUNDARY',
        );

        expect(contentType).toBe('multipart/form-data; boundary=BOUNDARY');

        const text = new TextDecoder().decode(body);
        expect(text).toContain('--BOUNDARY\r\nContent-Disposition: form-data; name="title"\r\n\r\nHi\r\n');
        expect(text).toContain('Content-Disposition: form-data; name="assets[]"; filename="a.png"\r\nContent-Type: image/png\r\n\r\n\u0001\u0002\u0003\r\n');
        expect(text.endsWith('--BOUNDARY--\r\n')).toBe(true);
    });
});
