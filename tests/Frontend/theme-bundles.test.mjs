import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { themeBuildInputs } from '../../bin/vite-theme-inputs.mjs';

function fixture(callback) {
    const root = mkdtempSync(join(tmpdir(), 'dbcms-theme-build-'));
    try {
        for (const path of ['themes/alpha/theme.css', 'themes/beta/theme.css', 'resources/views/themes/admin/client/theme.css', 'resources/views/themes/admin/other/theme.css']) {
            mkdirSync(join(root, path, '..'), { recursive: true });
            writeFileSync(join(root, path), '/* theme */');
        }
        callback(root);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
}

test('the source catalogue builds separate entries for every available theme', () => fixture((root) => {
    assert.deepEqual(themeBuildInputs(root), [
        'themes/alpha/theme.css', 'themes/beta/theme.css',
        'resources/views/themes/admin/client/theme.css', 'resources/views/themes/admin/other/theme.css',
    ]);
}));

test('derived builds include only the installed selection and its brand', () => fixture((root) => {
    for (const deployment of ['github-actions', 'ansistrano', 'rsync', 'docker', 'none']) {
        writeFileSync(join(root, 'cms-project.json'), JSON.stringify({ schema: 1, deployment, themes: ['beta'], default_theme: 'beta', brand: 'client' }));
        assert.deepEqual(themeBuildInputs(root), ['themes/beta/theme.css', 'resources/views/themes/admin/client/theme.css']);
    }
}));

test('invalid or missing selected themes fail before generating a deployment build', () => fixture((root) => {
    for (const theme of ['missing', '../beta']) {
        writeFileSync(join(root, 'cms-project.json'), JSON.stringify({ schema: 1, deployment: 'none', themes: [theme], default_theme: theme, brand: 'client' }));
        assert.throws(() => themeBuildInputs(root), /theme|identifier/i);
    }
}));
