import { copyFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const vault = process.env.OBSIDIAN_VAULT;

if (!vault) {
    console.error('Set OBSIDIAN_VAULT to the path of your vault, e.g. OBSIDIAN_VAULT=~/Documents/MyVault npm run install-vault');
    process.exit(1);
}

const target = join(vault, '.obsidian/plugins/web-publish');

mkdirSync(target, { recursive: true });

for (const file of ['main.js', 'manifest.json']) {
    copyFileSync(file, join(target, file));
}

console.log(`Installed to ${target}`);
