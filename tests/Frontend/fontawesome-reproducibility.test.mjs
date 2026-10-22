import assert from 'node:assert/strict';
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import test from 'node:test';

test('Font Awesome includes icons declared only by a visual package', () => {
    const root = mkdtempSync(join(tmpdir(), 'dbcms-package-icons-'));
    try {
        for (const directory of ['bin', 'block-packages/Example', 'node_modules/@fortawesome/fontawesome-free/metadata']) {
            mkdirSync(join(root, directory), { recursive: true });
        }
        copyFileSync(new URL('../../bin/generate-fontawesome-css.mjs', import.meta.url), join(root, 'bin/generate-fontawesome-css.mjs'));
        writeFileSync(join(root, 'block-packages/Example/Block.php'), '<?php return "fa-solid fa-vial";');
        writeFileSync(join(root, 'node_modules/@fortawesome/fontawesome-free/metadata/icon-families.json'), JSON.stringify({
            vial: { unicode: 'f492', svgs: { classic: { solid: {} } } },
        }));
        const result = spawnSync(process.execPath, [join(root, 'bin/generate-fontawesome-css.mjs')], { encoding: 'utf8' });
        assert.equal(result.status, 0, result.stderr);
        assert.match(readFileSync(join(root, 'resources/css/generated/fontawesome.css'), 'utf8'), /\.fa-solid\.fa-vial::before/);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});

test('Font Awesome generation is byte identical regardless of filesystem scan order', () => {
    const root = mkdtempSync(join(tmpdir(), 'dbcms-fontawesome-'));
    try {
        for (const directory of ['bin', 'node_modules/@fortawesome/fontawesome-free/metadata']) {
            mkdirSync(join(root, directory), { recursive: true });
        }
        copyFileSync(new URL('../../bin/generate-fontawesome-css.mjs', import.meta.url), join(root, 'bin/generate-fontawesome-css.mjs'));
        writeFileSync(join(root, 'bin/grep'), '#!/bin/sh\nprintf "%s\\n" "$FONT_AWESOME_TEST_SCAN"\n', { mode: 0o755 });
        writeFileSync(join(root, 'node_modules/@fortawesome/fontawesome-free/metadata/icon-families.json'), JSON.stringify({
            'circle-check': { unicode: 'f058', svgs: { classic: { solid: {}, regular: {} } } },
            bolt: { unicode: 'f0e7', svgs: { classic: { solid: {} } } },
            github: { unicode: 'f09b', svgs: { classic: { brands: {} } } },
        }));
        const icons = ['fa-solid fa-check-circle', 'fa-brands fa-github', 'fa-regular fa-bolt', 'fa-regular fa-circle-check', 'fa-solid fa-circle-check'];
        const generate = (scan) => {
            const result = spawnSync(process.execPath, [join(root, 'bin/generate-fontawesome-css.mjs')], {
                encoding: 'utf8',
                env: { ...process.env, PATH: `${join(root, 'bin')}:${process.env.PATH}`, FONT_AWESOME_TEST_SCAN: scan.join('\n') },
            });
            assert.equal(result.status, 0, result.stderr);
            return readFileSync(join(root, 'resources/css/generated/fontawesome.css'), 'utf8');
        };
        const forward = generate(icons);
        const reversed = generate([...icons].reverse());
        assert.match(forward, /\.fa-brands\.fa-github::before/);
        assert.match(forward, /\.fa-regular\.fa-circle-check::before/);
        assert.match(forward, /\.fa-solid\.fa-bolt::before/);
        assert.equal((forward.match(/\.fa-solid\.fa-circle-check::before/g) ?? []).length, 1);
        assert.equal(forward, reversed);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});
