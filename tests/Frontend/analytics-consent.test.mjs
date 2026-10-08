import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../modules/Analytics/Services/AnalyticsManagerService.php', import.meta.url), 'utf8');
const script = source.match(/<script nonce="\{\$nonce\}">([\s\S]*?)<\/script>/)?.[1]
    .replaceAll('{$encodedId}', JSON.stringify('G-ABC1234567'));

function visitor(cookie = '', stored = null) {
    const loaded = [];
    const listeners = new Map();
    const context = { document: { cookie, createElement: () => ({}), head: { appendChild: element => loaded.push(element) } }, localStorage: { getItem: () => stored } };
    context.window = context;
    context.addEventListener = (name, callback) => listeners.set(name, callback);
    assert.ok(script, 'The emitted GA4 script must be executable.');
    vm.runInNewContext(script, context);
    return { loaded, context, consent: analytics => listeners.get('cookie-consent-updated')({ detail: { analytics } }) };
}

test('absent, malformed, denied and unrelated consent never loads tracking', () => {
    for (const cookie of ['', 'cms_cookie_consent=invalid', 'cms_cookie_consent=%7B%22analytics%22%3Afalse%7D', 'cms_cookie_consent=%7B%22marketing%22%3Atrue%7D']) {
        const view = visitor(cookie);
        assert.equal(view.loaded.length, 0);
    }
});

test('granting consent loads GA4 exactly once', () => {
    const view = visitor();
    view.consent(true);
    view.consent(true);
    assert.equal(view.loaded.length, 1);
    assert.equal(view.loaded[0].src, 'https://www.googletagmanager.com/gtag/js?id=G-ABC1234567');
});

test('revoking consent disables an already loaded property and regranting reuses it', () => {
    const view = visitor('cms_cookie_consent=%7B%22analytics%22%3Atrue%7D');
    view.consent(false);
    assert.equal(view.context['ga-disable-G-ABC1234567'], true);
    view.consent(true);
    assert.equal(view.context['ga-disable-G-ABC1234567'], false);
    assert.equal(view.loaded.length, 1);
});
