import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../modules/AiAssistant/Resources/views/admin/settings.blade.php', import.meta.url), 'utf8');
function hub(fetch) {
    const methods = ['saveProvider', 'testProvider'].map(name => {
        const method = source.match(new RegExp(`async ${name}\\([^)]*\\) \\{([\\s\\S]*?)\\n        \\},`));
        assert.ok(method, `${name} is present`);
        return `async ${name}${method[0].slice(`async ${name}`.length).replace(/\{\{[\s\S]*?\}\}/g, '/stub')}`;
    }).join('\n');
    return vm.runInNewContext(`({ connectionEpochs: {}, testResults: { 'higgsfield:text': {success:true}, 'higgsfield:image': {success:true} }, forms: {higgsfield: {model:'video-model',api_key:'legacy',api_key_id:'id-fixture',api_key_secret:'secret-fixture'}}, catalog: {higgsfield:{}}, showToast() {}, loadModels() {}, ${methods} })`, {fetch});
}

test('saving credentials invalidates prior checks and clears entered secrets', async () => {
    const state = hub(async () => ({json: async () => ({success:true,model:'video-model',has_key:true})}));
    await state.saveProvider('higgsfield');
    assert.equal(state.connectionEpochs.higgsfield, 1);
    assert.deepEqual(Object.keys(state.testResults), []);
    for (const key of ['api_key', 'api_key_id', 'api_key_secret']) assert.equal(state.forms.higgsfield[key], '');
});

test('an old pending probe cannot certify credentials saved after it started', async () => {
    let release;
    const state = hub(async (_, options) => JSON.parse(options.body).modality
        ? new Promise(resolve => { release = () => resolve({json: async () => ({status:'connected',message:'obsolete',success:true})}); })
        : {json: async () => ({success:true,model:'video-model',has_key:true})});
    const probe = state.testProvider('higgsfield', 'video');
    await state.saveProvider('higgsfield');
    release();
    await probe;
    assert.equal(state.testResults['higgsfield:video'], undefined);
});
