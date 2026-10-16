import test from 'node:test';
import assert from 'node:assert/strict';
import { createBlockManager } from '../../resources/js/block-manager.js';

const types = [{ type: 'hero', name: 'Hero', defaultPayload: { title: 'Configured' }, schema: { title: { type: 'text', label: 'Title' } } }];
test('editor insertion uses registered definitions and cannot insert unavailable types', () => {
    const editor = createBlockManager({ es: [] }, 'es', types);
    editor.blocks = editor.sectionsByLocale.es;
    editor.addBlock('removed');
    assert.equal(editor.blocks.length, 0);
    editor.addBlock('hero');
    assert.equal(editor.blocks[0].title, 'Configured');
    assert.equal(editor.schemaFields(editor.blocks[0])[0].key, 'title');
});
test('editor serialization preserves unavailable payloads', () => {
    const stored = { id: 1, type: 'unavailable', payload: { important: ['kept'] } };
    const editor = createBlockManager({ es: [stored] }, 'es', types);
    editor.blocks = editor.sectionsByLocale.es;
    assert.deepEqual(JSON.parse(editor.getCleanSerializedSections()).es, [stored]);
    editor.cloneBlock(0);
    assert.equal(editor.blocks.length, 1);
});

for (const placement of ['section', 'column']) {
    test(`empty ${placement} insertion initializes the schema without demo content`, () => {
        const definition = {
            type: 'custom_panel',
            schema: {
                heading: { type: 'text' },
                body: { type: 'textarea' },
                link: { type: 'url' },
                entries: { type: 'repeater', fields: { label: { type: 'text' } } },
                tags: { type: 'list' },
                metadata: { type: 'json' },
                enabled: { type: 'boolean' },
                limit: { type: 'number', default: 4 },
                format: { type: 'select', options: ['markdown', 'html'] },
                selection: { type: 'select', options: { '': 'All', chosen: 'Chosen' } },
                settings: { type: 'json', default: { colors: ['blue'] } },
            },
            defaultPayload: {
                heading: 'Demo heading', body: 'Demo body', link: '/demo',
                entries: [{ label: 'Demo entry' }], tags: ['Demo tag'],
                metadata: { example: 'Demo' }, enabled: true, limit: 6,
                format: 'html', selection: 'chosen', settings: { colors: ['red'] },
            },
        };
        const editor = createBlockManager({ es: [] }, 'es', [definition]);
        editor.blocks = editor.sectionsByLocale.es;
        if (placement === 'column') {
            editor.blocks.push({ id: 'container', columns: [{ id: 'column', blocks: [] }] });
            editor.columnTarget = { blockIndex: 0, colIndex: 0 };
        }
        const insert = (mode) => {
            if (placement === 'column') editor.columnTarget = { blockIndex: 0, colIndex: 0 };
            editor.insertType('custom_panel', mode);
            return placement === 'column' ? editor.blocks[0].columns[0].blocks.at(-1) : editor.blocks.at(-1);
        };
        const empty = insert('empty');
        const values = (block) => Object.fromEntries(Object.keys(definition.schema).map(key => [key, block[key]]));
        assert.deepEqual(values(empty), {
            heading: '', body: '', link: '', entries: [], tags: [], metadata: {},
            enabled: false, limit: 4, format: 'markdown', selection: '', settings: { colors: ['blue'] },
        });
        assert.equal('title' in empty, false);
        assert.equal('form_slug' in empty, false);
        const demo = insert('demo');
        assert.deepEqual(values(demo), definition.defaultPayload);
        empty.settings.colors.push('green');
        demo.entries[0].label = 'Edited';
        assert.deepEqual(definition.schema.settings.default, { colors: ['blue'] });
        assert.equal(definition.defaultPayload.entries[0].label, 'Demo entry');
        assert.deepEqual(insert('empty').settings, { colors: ['blue'] });
        assert.equal(editor.sectionsByLocale.es, editor.blocks);
    });
}
