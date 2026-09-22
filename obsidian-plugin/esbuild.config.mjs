import esbuild from 'esbuild';

await esbuild.build({
    entryPoints: ['src/main.ts'],
    bundle: true,
    external: ['obsidian', 'electron'],
    format: 'cjs',
    target: 'es2022',
    outfile: 'main.js',
    logLevel: 'info',
});
