export interface ResolvedEmbed<F> {
    key: string;
    name: string;
    isImage: boolean;
    file: F;
}

export interface Asset<F> {
    name: string;
    file: F;
}

export interface Preprocessed<F> {
    markdown: string;
    assets: Asset<F>[];
    skipped: string[];
}

export interface MultipartFile {
    field: string;
    name: string;
    type: string;
    data: ArrayBuffer;
}

const FENCE_OPEN = /^([ \t]*)(`{3,}|~{3,})/;
const FENCE_CLOSE = /^[ \t]*(`{3,}|~{3,})[ \t]*$/;
const INLINE_CODE = /(`+)[\s\S]*?\1/g;
const MARKDOWN_IMAGE = /!\[([^\]]*)\]\((<[^>]*>|[^\s)]+)(?:\s+"[^"]*")?\)/g;
const EMBED = /!\[\[([^\]]+?)\]\]/g;
const WIKILINK = /\[\[([^\]]+?)\]\]/g;
const ABSOLUTE_URL = /^([a-z][a-z0-9+.-]*:|\/\/)/i;

export function stripFrontmatter(markdown: string): string {
    return markdown.replace(/^---\r?\n(?:[\s\S]*?\r?\n)?---[ \t]*(?:\r?\n|$)/, '');
}

/**
 * Rewrites Obsidian-specific syntax into standard Markdown and collects the images the note embeds.
 */
export function preprocess<F>(markdown: string, resolve: (linkpath: string) => ResolvedEmbed<F> | null): Preprocessed<F> {
    const assets: Asset<F>[] = [];
    const assetNames = new Map<string, string>();
    const skipped: string[] = [];

    const assetFor = (linkpath: string): string | null => {
        const resolved = resolve(linkpath);

        if (!resolved || !resolved.isImage) {
            skipped.push(linkpath);
            return null;
        }

        let name = assetNames.get(resolved.key);

        if (!name) {
            name = assetName(assets.length + 1, resolved.name);
            assetNames.set(resolved.key, name);
            assets.push({ name, file: resolved.file });
        }

        return name;
    };

    const transform = (text: string): string => text
        .replace(/%%[\s\S]*?%%/g, '')
        .replace(MARKDOWN_IMAGE, (match, rawAlt: string, destination: string) => {
            let url = destination.startsWith('<') ? destination.slice(1, -1) : destination;

            if (ABSOLUTE_URL.test(url)) {
                return match;
            }

            try {
                url = decodeURIComponent(url);
            } catch {
                // Not percent-encoded; use the path as written.
            }

            const size = rawAlt.match(/^(.*?)\|(\d+)(?:x\d+)?$/);
            const name = assetFor(url);

            return name ? image(size ? size[1]! : rawAlt, name, size?.[2]) : url;
        })
        .replace(EMBED, (_match, inner: string) => {
            const [target, alias] = splitPipe(inner);
            const linkpath = target.split('#')[0]!.trim();
            const name = assetFor(linkpath);

            if (!name) {
                return linkpath;
            }

            const size = alias?.match(/^(\d+)(?:x\d+)?$/);

            return image(alias && !size ? alias : withoutExtension(linkpath), name, size?.[1]);
        })
        .replace(WIKILINK, (_match, inner: string) => {
            const [target, alias] = splitPipe(inner);

            if (alias) {
                return alias;
            }

            const [page, heading] = target.split('#');

            return page!.trim() || (heading ?? '').trim();
        });

    const transformProse = (text: string): string => {
        let result = '';
        let last = 0;

        for (const match of text.matchAll(INLINE_CODE)) {
            result += transform(text.slice(last, match.index)) + match[0];
            last = match.index + match[0].length;
        }

        return result + transform(text.slice(last));
    };

    const lines = markdown.split('\n');
    const output: string[] = [];
    let prose: string[] = [];

    const flushProse = () => {
        if (prose.length > 0) {
            output.push(transformProse(prose.join('\n')));
            prose = [];
        }
    };

    for (let i = 0; i < lines.length; i++) {
        const open = lines[i]!.match(FENCE_OPEN);

        if (!open) {
            prose.push(lines[i]!);
            continue;
        }

        flushProse();

        const indent = open[1]!;
        const fence = open[2]!;
        const content: string[] = [];
        let close: string | null = null;

        for (i++; i < lines.length; i++) {
            const closing = lines[i]!.match(FENCE_CLOSE);

            if (closing && closing[1]![0] === fence[0] && closing[1]!.length >= fence.length) {
                close = lines[i]!;
                break;
            }

            content.push(lines[i]!);
        }

        const misindented = indent !== '' && content.some((line) => line.trim() !== '' && !line.startsWith(indent));

        output.push(lines[i - content.length - 1]!);
        output.push(...(misindented ? content.map((line) => (line === '' ? line : indent + line)) : content));

        if (close !== null) {
            output.push(misindented ? indent + close.trim() : close);
        }
    }

    flushProse();

    return { markdown: output.join('\n'), assets, skipped };
}

export function buildMultipart(
    fields: Record<string, string>,
    files: MultipartFile[],
    boundary = `----web-publish-${Math.random().toString(36).slice(2)}${Date.now().toString(36)}`,
): { body: ArrayBuffer; contentType: string } {
    const encoder = new TextEncoder();
    const parts: Uint8Array[] = [];

    for (const [name, value] of Object.entries(fields)) {
        parts.push(encoder.encode(`--${boundary}\r\nContent-Disposition: form-data; name="${name}"\r\n\r\n${value}\r\n`));
    }

    for (const file of files) {
        parts.push(encoder.encode(
            `--${boundary}\r\nContent-Disposition: form-data; name="${file.field}"; filename="${file.name}"\r\nContent-Type: ${file.type}\r\n\r\n`,
        ));
        parts.push(new Uint8Array(file.data));
        parts.push(encoder.encode('\r\n'));
    }

    parts.push(encoder.encode(`--${boundary}--\r\n`));

    const body = new Uint8Array(parts.reduce((total, part) => total + part.length, 0));
    let offset = 0;

    for (const part of parts) {
        body.set(part, offset);
        offset += part.length;
    }

    return { body: body.buffer, contentType: `multipart/form-data; boundary=${boundary}` };
}

function splitPipe(inner: string): [string, string | undefined] {
    const index = inner.indexOf('|');

    return index === -1 ? [inner, undefined] : [inner.slice(0, index), inner.slice(index + 1).trim()];
}

function image(alt: string, name: string, width?: string): string {
    return `![${alt.replace(/[[\]]/g, '\\$&')}](<${name}>${width ? ` "w=${width}"` : ''})`;
}

function withoutExtension(path: string): string {
    return (path.split('/').pop() ?? path).replace(/\.[^.]+$/, '');
}

function assetName(index: number, filename: string): string {
    const name = `${index}-${filename.replace(/[^A-Za-z0-9._-]/g, '-')}`;

    if (name.length <= 120) {
        return name;
    }

    const extension = name.match(/\.[A-Za-z0-9]{1,10}$/)?.[0] ?? '';

    return name.slice(0, 120 - extension.length) + extension;
}
