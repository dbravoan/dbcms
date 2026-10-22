import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { Script } from 'node:vm';
import { createAiWorkbench } from '../../modules/AiAssistant/Resources/js/workbench.js';

const read = path => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
const workbench = read('modules/AiAssistant/Resources/views/admin/workbench.blade.php');
const copilot = read('modules/AiAssistant/Resources/views/admin/partials/copilot_bridge.blade.php');
const createFill = read('modules/AiAssistant/Resources/views/admin/partials/create_fill.blade.php');
const renderDirectives = source => {
    let start;
    while ((start = source.indexOf('@js(')) !== -1) {
        let depth = 1;
        let quote = null;
        let end = start + 4;
        for (; depth && end < source.length; end++) {
            const character = source[end];
            if (quote) {
                if (character === '\\') end++;
                else if (character === quote) quote = null;
            } else if (character === "'" || character === '"') quote = character;
            else if (character === '(') depth++;
            else if (character === ')') depth--;
        }
        assert.equal(depth, 0, 'Blade directive is balanced');
        source = source.slice(0, start) + '"translation"' + source.slice(end);
    }
    return source.replace(/\{\{[^\n]*?\}\}/g, 'rendered');
};
const body = (template, name) => {
    const match = template.match(new RegExp(`(?:async )?${name}\\([^)]*\\) \\{([\\s\\S]*?)\\n        \\},`));
    assert.ok(match, `${name} is present`);
    return match[1];
};
const invoke = (template, name, state, globals = {}) => {
    if (template === workbench) {
        return (async () => {
            const saved = Object.fromEntries(Object.keys(globals).map(key => [key, globalThis[key]]));
            Object.assign(globalThis, globals);
            try {
                const component = createAiWorkbench({ clarification: 'translation', statusUrl: '/status/__TASK__' });
                return await component[name].call(state, globals.taskId);
            } finally {
                Object.assign(globalThis, saved);
            }
        })();
    }
    const source = renderDirectives(body(template, name));
    return Function(...Object.keys(globals), `return async function() {${source}}`)(...Object.values(globals)).call(state);
};

test('applying an editor result refuses a changed translation or market', async () => {
    const state = {
        generatedResult: { resolved_context: { locale: 'en', market_ids: [2] } },
        editorContext: () => ({ locale: 'es', editor_market_ids: [2] }),
    };
    assert.equal(await invoke(copilot, 'canApplyContext', state), false);
    state.editorContext = () => ({ locale: 'en', editor_market_ids: [3] });
    assert.equal(await invoke(copilot, 'canApplyContext', state), false);
    state.editorContext = () => ({ locale: 'en', editor_market_ids: [2] });
    assert.equal(await invoke(copilot, 'canApplyContext', state), true);
});

test('create-form fill cannot write generated English content into a Spanish tab', async () => {
    let writes = 0;
    await assert.rejects(invoke(createFill, 'fill', {
        config: { locale: 'es', fields: { title: { input: 'translations[{locale}][title]' } } },
        writeInput() { writes++; },
    }, {
        window: { cmsContext: { locale: 'es', marketIds: ['2'] } },
        data: { resolved_context: { locale: 'en', market_ids: [2] }, fields: { title: 'English title' } },
    }));
    assert.equal(writes, 0);
});

test('SEO reads only the active translation instead of combining languages', async () => {
    const state = { detectLocale: () => 'en' };
    const result = await invoke(copilot, 'entityContent', state, {
        document: {
            querySelectorAll() { return [
                { name: 'translations[es][summary]', value: 'Texto español' },
                { name: 'translations[en][summary]', value: 'English text' },
            ]; },
            querySelector() { return null; },
        },
    });
    assert.equal(result, 'English text');
});

test('modified assistant scripts remain syntactically valid after Blade rendering', () => {
    for (const template of [workbench, copilot, createFill]) {
        for (const [, script] of template.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g)) {
            const rendered = renderDirectives(script);
            assert.doesNotThrow(() => new Script(rendered));
        }
    }
});

test('standalone assistant derives context from instructions without duplicated selectors', async () => {
    assert.equal(/<x-admin\.(?:language|market)-select|x-model="orchestratorForm\.(?:locale|market)"/.test(workbench), false);
    let payload;
    const state = {
        pageForm: { type: 'page', prompt: 'Write in English for the UK' },
        async requestGeneration(value) { payload = value; return { resolved_context: { locale: 'en', market: 'uk' } }; },
    };
    await invoke(workbench, 'generatePageElement', state);
    assert.equal(payload.context_source, 'prompt');
    assert.equal(payload.locale, undefined);
    assert.equal(payload.market, undefined);
});

test('draft persistence uses generated context even after the instruction changes', async () => {
    let payload;
    const state = {
        pageForm: { locale: 'es', market: 'es' },
        pageResult: { type: 'page', fields: {}, resolved_context: { locale: 'en', market: 'uk', market_ids: [2] } },
    };
    await invoke(workbench, 'createDraft', state, {
        fetch: async (_, options) => { payload = JSON.parse(options.body); return { json: async () => ({ success: false }) }; },
    });
    assert.equal(payload.locale, 'en');
    assert.equal(payload.market, 'uk');
});

test('editor assistant snapshots the current translation and markets at request time', async () => {
    const window = { cmsContext: { locale: 'en', marketIds: ['2', '3'] } };
    const state = { detectLocale: () => 'es' };
    const first = await invoke(copilot, 'editorContext', state, { window });
    window.cmsContext.locale = 'ca';
    window.cmsContext.marketIds = ['4'];
    const second = await invoke(copilot, 'editorContext', state, { window });
    assert.deepEqual(first, { context_source: 'editor', locale: 'en', editor_market_ids: [2, 3] });
    assert.deepEqual(second, { context_source: 'editor', locale: 'ca', editor_market_ids: [4] });
});

test('a clarification stops polling before any generated content can be applied', async () => {
    for (const [template, method] of [[workbench, 'pollGeneration'], [createFill, 'poll']]) {
        await assert.rejects(invoke(template, method, {}, {
            fetch: async () => ({ json: async () => ({ success: false, status: 'needs_input', question: 'Which market should I use?' }) }),
            setTimeout: callback => callback(),
            taskId: 'qa-task',
        }), /Which market should I use\?/);
    }
});

test('generated content never invents a quality score from the number of blocks', async () => {
    for (const data of [{ content: 'Draft' }, { recommended_blocks: [{ type: 'hero' }, { type: 'text' }] }]) {
        const state = {
            mode: 'seo', promptText: '', detectLocale: () => 'en', entityTitle: () => 'Title', entityContent: () => 'Content',
            postGenerate: async () => ({ success: true, data }),
            auditScore: result => result.anti_ai_audit?.anti_ai_score ?? result.anti_ai_score ?? null,
        };
        await invoke(copilot, 'generateContent', state);
        assert.equal(state.antiAiScore, null);
    }
});

test('resuming content without an audit never awards a perfect score', async () => {
    const state = {
        pollTask: async () => ({ success: true, data: { content: 'Draft' } }),
        forgetTask() {},
        auditScore: result => result.anti_ai_audit?.anti_ai_score ?? result.anti_ai_score ?? null,
    };
    await invoke(copilot, 'resumeFromContext', state, { saved: { id: 'task', mode: 'text' } });
    assert.equal(state.antiAiScore, null);
});

test('quality labels are hidden when the result has no measured score', () => {
    assert.equal(/<span x-show="antiAiScore !== null">/.test(copilot), true);
    assert.equal(/anti_ai_score \?\? 0/.test(workbench), false);
});

test('only an explicitly supplied valid audit score is displayed, including zero', async () => {
    for (const result of [{}, { anti_ai_score: '92' }, { anti_ai_score: 150 }, { anti_ai_score: Number.NaN }]) {
        assert.equal(await invoke(copilot, 'auditScore', {}, { result }), null);
    }
    assert.equal(await invoke(copilot, 'auditScore', {}, { result: { anti_ai_score: 0 } }), 0);
    assert.equal(await invoke(copilot, 'auditScore', {}, { result: { anti_ai_audit: { anti_ai_score: 78 } } }), 78);
});

test('every assistant label has an English and Spanish translation', () => {
    const seo = read('modules/AiAssistant/Resources/views/admin/partials/seo_generate.blade.php');
    const keys = [...new Set([workbench, copilot, createFill, seo].flatMap(template =>
        [...template.matchAll(/admin\.ai\.([a-z_]+)/g)].map(match => match[1])
    ))];
    for (const locale of ['es', 'en']) {
        const section = read(`lang/${locale}/admin.php`).match(/^    'ai' => \[([\s\S]*?)^    \],/m)[1];
        const translated = new Set([...section.matchAll(/^        '([a-z_]+)' =>/gm)].map(match => match[1]));
        assert.deepEqual(keys.filter(key => !translated.has(key)), [], `Missing ${locale} assistant labels`);
    }
});
