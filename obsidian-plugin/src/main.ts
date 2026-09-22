import { App, Notice, Plugin, PluginSettingTab, requestUrl, Setting, TFile, type RequestUrlResponse } from 'obsidian';
import { buildMultipart, preprocess, stripFrontmatter } from './preprocess';

interface Settings {
    serverUrl: string;
    token: string;
}

const DEFAULT_SETTINGS: Settings = {
    serverUrl: '',
    token: '',
};

const IMAGE_TYPES: Record<string, string> = {
    png: 'image/png',
    jpg: 'image/jpeg',
    jpeg: 'image/jpeg',
    gif: 'image/gif',
    webp: 'image/webp',
};

/**
 * Publishes the active note to the web-publish server and keeps its URL in the note's frontmatter.
 */
export default class WebPublishPlugin extends Plugin {
    settings: Settings = DEFAULT_SETTINGS;

    async onload(): Promise<void> {
        this.settings = { ...DEFAULT_SETTINGS, ...(await this.loadData()) };
        this.addSettingTab(new WebPublishSettingTab(this.app, this));

        this.addCommand({
            id: 'publish-note',
            name: 'Publish note',
            checkCallback: (checking) => this.withActiveNote(checking, (file) => this.publish(file)),
        });

        this.addCommand({
            id: 'unpublish-note',
            name: 'Unpublish note',
            checkCallback: (checking) => this.withActiveNote(checking, (file) => this.unpublish(file), 'publish_id'),
        });

        this.addCommand({
            id: 'copy-published-url',
            name: 'Copy published URL',
            checkCallback: (checking) => this.withActiveNote(checking, (file) => this.copyUrl(file), 'publish_url'),
        });

        this.registerEvent(this.app.workspace.on('file-menu', (menu, file) => {
            if (!(file instanceof TFile) || file.extension !== 'md') {
                return;
            }

            menu.addItem((item) => item.setTitle('Publish to web').setIcon('upload-cloud').onClick(() => this.publish(file)));

            if (this.frontmatter(file, 'publish_id')) {
                menu.addItem((item) => item.setTitle('Unpublish from web').setIcon('cloud-off').onClick(() => this.unpublish(file)));
            }
        }));
    }

    async saveSettings(): Promise<void> {
        await this.saveData(this.settings);
    }

    private withActiveNote(checking: boolean, run: (file: TFile) => Promise<void>, requiredKey?: string): boolean {
        const file = this.app.workspace.getActiveFile();

        if (!file || file.extension !== 'md' || (requiredKey && !this.frontmatter(file, requiredKey))) {
            return false;
        }

        if (!checking) {
            void run(file);
        }

        return true;
    }

    private frontmatter(file: TFile, key: string): string | undefined {
        const value: unknown = this.app.metadataCache.getFileCache(file)?.frontmatter?.[key];

        return typeof value === 'string' && value !== '' ? value : undefined;
    }

    private async publish(file: TFile): Promise<void> {
        if (!this.settings.serverUrl || !this.settings.token) {
            new Notice('Set the server URL and API token in the Publish to Web settings first.');
            return;
        }

        const notice = new Notice(`Publishing ${file.basename}…`, 0);

        try {
            const content = stripFrontmatter(await this.app.vault.cachedRead(file));
            const result = preprocess(content, (linkpath) => {
                const target = this.app.metadataCache.getFirstLinkpathDest(linkpath, file.path);

                return target
                    ? { key: target.path, name: target.name, isImage: target.extension.toLowerCase() in IMAGE_TYPES, file: target }
                    : null;
            });

            const files = await Promise.all(result.assets.map(async (asset) => ({
                field: 'assets[]',
                name: asset.name,
                type: IMAGE_TYPES[asset.file.extension.toLowerCase()] ?? 'application/octet-stream',
                data: await this.app.vault.readBinary(asset.file),
            })));

            const multipart = buildMultipart({ title: file.basename, markdown: result.markdown }, files);
            const existing = this.frontmatter(file, 'publish_id');

            let response = await this.request(existing ? `/api/notes/${existing}` : '/api/notes', 'POST', multipart);

            if (existing && response.status === 404) {
                response = await this.request('/api/notes', 'POST', multipart);
            }

            if (response.status !== 200 && response.status !== 201) {
                throw new Error(this.errorMessage(response));
            }

            const { hash, url } = response.json as { hash: string; url: string };

            await this.app.fileManager.processFrontMatter(file, (frontmatter: Record<string, unknown>) => {
                frontmatter.publish_id = hash;
                frontmatter.publish_url = url;
            });

            await navigator.clipboard.writeText(url);

            new Notice(result.skipped.length > 0
                ? `Published — URL copied. Skipped embeds: ${result.skipped.join(', ')}`
                : 'Published — URL copied.', result.skipped.length > 0 ? 10000 : 4000);
        } catch (error) {
            new Notice(`Publish failed: ${error instanceof Error ? error.message : String(error)}`, 10000);
        } finally {
            notice.hide();
        }
    }

    private async unpublish(file: TFile): Promise<void> {
        const id = this.frontmatter(file, 'publish_id');

        if (!id) {
            return;
        }

        try {
            const response = await this.request(`/api/notes/${id}`, 'DELETE');

            if (response.status !== 204 && response.status !== 404) {
                throw new Error(this.errorMessage(response));
            }

            await this.app.fileManager.processFrontMatter(file, (frontmatter: Record<string, unknown>) => {
                delete frontmatter.publish_id;
                delete frontmatter.publish_url;
            });

            new Notice('Unpublished.');
        } catch (error) {
            new Notice(`Unpublish failed: ${error instanceof Error ? error.message : String(error)}`, 10000);
        }
    }

    private async copyUrl(file: TFile): Promise<void> {
        const url = this.frontmatter(file, 'publish_url');

        if (url) {
            await navigator.clipboard.writeText(url);
            new Notice('URL copied.');
        }
    }

    private request(path: string, method: 'POST' | 'DELETE', multipart?: { body: ArrayBuffer; contentType: string }): Promise<RequestUrlResponse> {
        return requestUrl({
            url: this.settings.serverUrl.replace(/\/+$/, '') + path,
            method,
            headers: { Authorization: `Bearer ${this.settings.token}`, Accept: 'application/json' },
            contentType: multipart?.contentType,
            body: multipart?.body,
            throw: false,
        });
    }

    private errorMessage(response: RequestUrlResponse): string {
        if (response.status === 401) {
            return 'unauthorized — check the API token in settings';
        }

        try {
            const json = response.json as { message?: string; errors?: Record<string, string[]> };
            const first = json.errors ? Object.values(json.errors)[0]?.[0] : undefined;

            return `${response.status} ${first ?? json.message ?? ''}`.trim();
        } catch {
            return `HTTP ${response.status}`;
        }
    }
}

class WebPublishSettingTab extends PluginSettingTab {
    constructor(app: App, private readonly plugin: WebPublishPlugin) {
        super(app, plugin);
    }

    display(): void {
        this.containerEl.empty();

        new Setting(this.containerEl)
            .setName('Server URL')
            .addText((text) => text
                .setPlaceholder('https://notes.example.com')
                .setValue(this.plugin.settings.serverUrl)
                .onChange(async (value) => {
                    this.plugin.settings.serverUrl = value.trim();
                    await this.plugin.saveSettings();
                }));

        new Setting(this.containerEl)
            .setName('API token')
            .setDesc('The PUBLISH_TOKEN configured on the server.')
            .addText((text) => {
                text.inputEl.type = 'password';
                text.setValue(this.plugin.settings.token).onChange(async (value) => {
                    this.plugin.settings.token = value.trim();
                    await this.plugin.saveSettings();
                });
            });
    }
}
