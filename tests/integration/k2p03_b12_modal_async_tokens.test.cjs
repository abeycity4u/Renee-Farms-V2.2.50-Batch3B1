'use strict';
// Offline Node-only browser-shim tests. No network, PHP, DB, or app runtime.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../../assets/js/daily-record-revision-modal.js'), 'utf8');
const sandbox = { window: {} };
vm.runInNewContext(source, sandbox, { filename: 'daily-record-revision-modal.js' });
const createController = sandbox.window.ReneeDailyRecordRevisionModal.createController;
const names = [
    'write_intent', 'record_id', 'expected_revision',
    'original_cycle_id', 'original_record_date', 'original_animal_type'
];
let checks = 0;
function pass(label, fn) {
    fn();
    checks += 1;
    console.log('PASS: ' + label);
}
function makeForm() {
    const fields = Object.fromEntries(names.map(name => [name, [{ value: '', type: 'hidden' }]]));
    return {
        fields,
        querySelectorAll(selector) {
            const match = /^input\[type="hidden"\]\[name="([a-z_]+)"\]$/.exec(selector);
            return match ? (fields[match[1]] || []) : [];
        },
        values() { return Object.fromEntries(names.map(name => [name, fields[name]?.[0]?.value])); }
    };
}
function make(type='layer') {
    const form = makeForm();
    const scope = { record_date: '2026-10-12', cycle_id: '42', animal_type: type === 'ruminant' ? 'goat' : '' };
    const controller = createController({ form, type, readScope: () => ({ ...scope }) });
    return { form, scope, controller };
}
function edit(scope, overrides={}) {
    return {
        write_intent: 'edit', record_id: '101', expected_revision: '4',
        original_cycle_id: scope.cycle_id || '',
        original_record_date: scope.record_date,
        original_animal_type: scope.animal_type,
        ...overrides
    };
}
pass('no writable status without explicit selection', () => {
    const {controller} = make();
    assert.equal(controller.isReady(), false);
});
pass('reject incomplete form or duplicate token inputs', () => {
    const form = makeForm();
    delete form.fields.record_id;
    assert.throws(() => createController({form,type:'layer',readScope:()=>({})}));
    form.fields.record_id = [{value:''},{value:''}];
    assert.throws(() => createController({form,type:'layer',readScope:()=>({})}));
});
pass('reject unsupported type and invalid setup', () => {
    assert.throws(() => createController({form:makeForm(),type:'name-from-post',readScope:()=>({})}));
});
for (const type of ['layer', 'broiler', 'ruminant']) {
    pass(type+' starts pending and clears old edit token', () => {
        const {form,controller,scope} = make(type);
        const a = controller.begin();
        assert.equal(controller.loadEdit(a,edit(scope)),true);
        assert.equal(controller.isReady(),true);
        assert.equal(form.values().expected_revision,'4');
        const b=controller.begin();
        assert.equal(controller.isReady(),false);
        assert.equal(form.values().expected_revision,'');
        assert.equal(form.values().write_intent,'');
        assert.equal(controller.isCurrent(a),false);
        assert.equal(controller.isCurrent(b),true);
    });
    pass(type+' CREATE only after explicit current allowCreate', () => {
        const {form,controller} = make(type);
        const a=controller.begin();
        assert.equal(controller.allowCreate(a),true);
        assert.equal(form.values().write_intent,'create');
        assert.equal(form.values().record_id,'');
        assert.equal(form.values().expected_revision,'');
        assert.equal(controller.isReady(),true);
        assert.equal(controller.loadEdit(a,{}),false); // no CREATE-to-EDIT promotion
    });
    pass(type+' validates edit identity and original revision snapshot', () => {
        const {form,controller,scope} = make(type);
        const a=controller.begin();
        assert.equal(controller.loadEdit(a,edit(scope)),true);
        assert.equal(form.values().original_record_date,scope.record_date);
        assert.equal(controller.isReady(),true);
        assert.equal(controller.allowCreate(a),false);
    });
    pass(type+' rejects stale AJAX response for previous date', () => {
        const {form,controller,scope} = make(type);
        const a=controller.begin();
        scope.record_date='2026-10-13';
        const b=controller.begin();
        assert.equal(controller.loadEdit(a,edit({...scope,record_date:'2026-10-12'})),false);
        assert.equal(controller.allowCreate(a),false);
        assert.equal(controller.reject(a),false);
        assert.equal(controller.loadEdit(b,edit(scope)),true);
        assert.equal(form.values().original_record_date,'2026-10-13');
    });
    pass(type+' rejects old same-scope ticket after selecting away/back', () => {
        const {form,controller,scope} = make(type);
        const a=controller.begin();
        scope.record_date='2026-10-13'; controller.begin();
        scope.record_date='2026-10-12'; const b=controller.begin();
        assert.equal(controller.loadEdit(a,edit(scope)),false);
        assert.equal(controller.loadEdit(b,edit(scope)),true);
        assert.equal(form.values().record_id,'101');
    });
    pass(type+' direct scope mutation invalidates submit readiness', () => {
        const {form,controller,scope} = make(type);
        let a=controller.begin(); controller.loadEdit(a,edit(scope));
        scope.cycle_id='44';
        assert.equal(controller.isReady(),false);
        assert.equal(form.values().expected_revision,'');
    });
    pass(type+' rejects tampered hidden field before submit', () => {
        const {form,controller,scope} = make(type);
        const a=controller.begin(); controller.loadEdit(a,edit(scope));
        form.fields.expected_revision[0].value='999';
        assert.equal(controller.isReady(),false);
        assert.equal(form.values().expected_revision,'');
    });
    pass(type+' rejected AJAX response must not become CREATE', () => {
        const {form,controller,scope} = make(type);
        let a=controller.begin();
        assert.equal(controller.loadEdit(a,edit(scope,{expected_revision:'0'})),false);
        assert.equal(controller.isReady(),false);
        assert.equal(form.values().write_intent,'');
    });
    pass(type+' original date/cycle/species mismatch fails closed', () => {
        for (const overrides of [
            {original_record_date:'2026-10-13'},
            {original_cycle_id:'99'},
            {original_animal_type:type==='ruminant'?'sheep':'goat'}
        ]) {
            const {controller,scope} = make(type);
            const a=controller.begin();
            assert.equal(controller.loadEdit(a,edit(scope,overrides)),false);
            assert.equal(controller.isReady(),false);
        }
    });
    pass(type+' rejects missing token field, duplicate id and population placeholder', () => {
        const {controller,scope} = make(type);
        for (const variant of [
            (()=>{const t=edit(scope);delete t.expected_revision;return t;})(),
            edit(scope,{record_id:'0001'}),
            edit(scope,{record_id:'9223372036854775808'}),
            edit(scope,{is_population_activity_only:true})
        ]) {
            const a=controller.begin();
            assert.equal(controller.loadEdit(a,variant),false);
        }
    });
    pass(type+' cancel/reset invalidates all edit tokens', () => {
        const {form,controller,scope} = make(type);
        const a=controller.begin();controller.loadEdit(a,edit(scope));
        controller.invalidate();
        assert.equal(controller.isReady(),false);
        assert.equal(form.values().record_id,'');
        assert.equal(controller.loadEdit(a,edit(scope)),false);
    });
}
pass('Ruminant normalizes selected species but refuses stale type change', () => {
    const {controller,scope}=make('ruminant');scope.animal_type=' Goat ';
    const a=controller.begin();
    assert.equal(controller.loadEdit(a,edit({...scope,animal_type:'goat'})),true);
    scope.animal_type='cattle';
    assert.equal(controller.isReady(),false);
});
pass('nullable-cycle legacy selection is distinct from explicit cycle',()=>{
    const {controller,scope}=make();scope.cycle_id='';
    const a=controller.begin();assert.equal(controller.loadEdit(a,edit(scope)),true);
    scope.cycle_id='42';assert.equal(controller.isReady(),false);
});
pass('unexpected calendar date or invalid cycle fails closed',()=>{
    const {controller,scope,form}=make();scope.record_date='2026-02-30';
    assert.throws(()=>controller.begin());
    assert.equal(form.values().write_intent,'');
    scope.record_date='2026-10-12';scope.cycle_id='0';
    assert.throws(()=>controller.begin());
});
(async()=>{
    const {controller,scope,form}=make();
    let resolve;
    const older=new Promise(r=>{resolve=r;});
    const ticket1=controller.begin();
    const handle=older.then(tokens=>controller.loadEdit(ticket1,tokens));
    scope.record_date='2026-10-13';
    const ticket2=controller.begin();
    assert.equal(controller.allowCreate(ticket2),true);
    resolve(edit({record_date:'2026-10-12',cycle_id:'42',animal_type:''}));
    assert.equal(await handle,false);
    assert.equal(form.values().write_intent,'create');
    assert.equal(form.values().record_id,'');
    assert.equal(controller.isReady(),true);
    checks += 1;
    console.log('PASS: old deferred Promise cannot promote newer CREATE to EDIT');
    console.log('B12_MODAL_ASYNC_TOKEN_PURE=PASS');
    console.log('PASS_COUNT='+checks);
})().catch(e=>{console.error(e);process.exitCode=1;});
