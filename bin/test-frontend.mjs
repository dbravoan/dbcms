#!/usr/bin/env node
import { existsSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';

const root = fileURLToPath(new URL('../', import.meta.url));
// These suites exercise optional package source. Unknown suites always run, so
// adding a dependency cannot silently remove a new test from the quality gate.
const requires = {
    'analytics-consent.test.mjs': ['Analytics'],
    'ai-provider-probe-boundary.test.mjs': ['AiAssistant'],
    'ai-media-entrypoints.test.mjs': ['AiAssistant'],
    'ai-editor-context.test.mjs': ['AiAssistant'],
    'ai-entity-translation.test.mjs': ['AiAssistant'],
};
const files = readdirSync(join(root, 'tests/Frontend'))
    .filter(file => file.endsWith('.test.mjs'))
    .sort()
    .filter(file => {
        const absent = (requires[file] ?? []).filter(module => !existsSync(join(root, 'modules', module, 'module.json')));
        if (absent.length) console.log(`Outside this artifact: ${file} requires ${absent.join(', ')}`);
        return absent.length === 0;
    })
    .map(file => join(root, 'tests/Frontend', file));
if (files.length === 0) throw new Error('No frontend tests selected.');
const result = spawnSync(process.execPath, ['--test', ...files], { cwd: root, stdio: 'inherit' });
if (result.error) throw result.error;
process.exit(result.status ?? 1);
