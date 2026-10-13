'use strict';
/*
 * B12 scope-to-exact browser API loader contract. Source-only Node tests.
 * No browser, PHP bootstrap, external HTTP endpoint, database or CI approval.
 * The controller remains dormant until coordinated schema/writer cutover.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(
    path.join(__dirname, '../../assets/js/daily-record-revision-modal.js'), 'utf8'
);
const context = { window: {} };
vm.runInNewContext(source, context, { filename: 'daily-record-revision-modal.js' });
const makeController = context.window.ReneeDailyRecordRevisionModal.createController;
const names = [
    'write_intent', 'record_id', 'expected_revision',
    'original_cycle_id', 'original_record_date', 'original_animal_type'
];
let checks = 0;
const pass = async (name, test) => {
    await test();
    ++checks;
    console.log('PASS: ' + name);
};
function make(type = 'layer') {
    const controls = Object.fromEntries(names.map(name => [name, {value:''}]));
    const form = {
        querySelectorAll(selector) {
            const match = /^input\[type="hidden"\]\[name="([a-z_]+)"\]$/.exec(selector);
            return match && controls[match[1]] ? [controls[match[1]]] : [];
        }
    };
    const scope = {
        record_date:'2026-10-13', cycle_id:'11',
        animal_type:type === 'ruminant' ? 'goat' : ''
    };
    const controller = makeController({form,type,readScope:()=>({...scope})});
    const values = () => Object.fromEntries(names.map(n=>[n,controls[n].value]));
    return {scope,controller,values,controls};
}
function tokens(scope, changes={}) {
    return {
        write_intent:'edit',record_id:'19',expected_revision:'4',
        original_cycle_id:scope.cycle_id,
        original_record_date:scope.record_date,
        original_animal_type:scope.animal_type,
        ...changes
    };
}
function response(mode, data) { return {success:true,mode,data}; }
function absent() { return response('scope',{status:'absent',record_id:null}); }
function unique(id=19) { return response('scope',{status:'unique',record_id:id}); }
function exact(scope,changes={}) {
    return response('exact',{
        record:{
            id:'19',cycle_id:scope.cycle_id,record_date:scope.record_date,
            ...(scope.animal_type ? {animal_type:scope.animal_type}:{}),
            mortality:2,...(changes.record || {})
        },
        form_tokens:tokens(scope,changes.tokens)
    });
}

(async()=>{
    for (const type of ['layer','broiler','ruminant']) {
        await pass(type+' absent scope enters CREATE only after scope response',async()=>{
            const {controller,values}=make(type);
            const queries=[];
            const result=await controller.loadSelection(async q=>{
                queries.push(q);
                return absent();
            });
            assert.equal(result.status,'ready');
            assert.equal(result.mode,'create');
            assert.equal(result.record,null);
            assert.equal(queries.length,1);
            assert.equal(queries[0].mode,'scope');
            assert.equal(queries[0].type,type);
            assert.equal(queries[0].record_date,'2026-10-13');
            assert.equal(Object.hasOwn(queries[0],'record_id'),false);
            assert.equal(Object.hasOwn(queries[0],'animal_type'),type==='ruminant');
            assert.equal(values().write_intent,'create');
            assert.equal(values().expected_revision,'');
            assert.equal(controller.isReady(),true);
        });
        await pass(type+' existing scope loads exact immutable row and original revision',async()=>{
            const {controller,scope,values}=make(type);
            const queries=[];
            const result=await controller.loadSelection(async q=>{
                queries.push(q);
                return q.mode==='scope' ? unique(19) : exact(scope);
            });
            assert.equal(result.status,'ready');
            assert.equal(result.mode,'edit');
            assert.equal(result.record.mortality,2);
            assert.equal(queries.length,2);
            assert.equal(queries[1].mode,'exact');
            assert.equal(queries[1].record_id,'19');
            assert.equal(queries[1].type,type);
            assert.equal(values().record_id,'19');
            assert.equal(values().expected_revision,'4');
            assert.equal(controller.isCurrent(result.ticket),true);
            assert.equal(controller.isReady(),true);
        });
        await pass(type+' ambiguous scope never becomes CREATE',async()=>{
            const {controller,values}=make(type);
            let calls=0;
            const result=await controller.loadSelection(async()=>{calls++;return response('scope',{status:'ambiguous',record_id:null});});
            assert.equal(result.status,'blocked');
            assert.equal(calls,1);
            assert.equal(values().write_intent,'');
            assert.equal(controller.isReady(),false);
        });
        await pass(type+' malformed absent scope with record ID is rejected',async()=>{
            const {controller}=make(type);
            const result=await controller.loadSelection(async()=>response('scope',{status:'absent',record_id:19}));
            assert.equal(result.status,'blocked');
            assert.equal(controller.isReady(),false);
        });
        await pass(type+' scope unique but missing exact read fails closed',async()=>{
            const {controller}=make(type);
            const result=await controller.loadSelection(async q=>{
                if(q.mode==='scope') return unique();
                throw Object.assign(new Error('not found'),{status:404});
            });
            assert.equal(result.status,'blocked');
            assert.equal(controller.isReady(),false);
        });
        await pass(type+' exact wrong ID blocks edit',async()=>{
            const {controller,scope,values}=make(type);
            const result=await controller.loadSelection(async q=>
                q.mode==='scope' ? unique() : exact(scope,{record:{id:'20'}})
            );
            assert.equal(result.status,'blocked');
            assert.equal(values().write_intent,'');
            assert.equal(controller.isReady(),false);
        });
        await pass(type+' malformed original revision blocks edit',async()=>{
            const {controller,scope}=make(type);
            const result=await controller.loadSelection(async q=>
                q.mode==='scope' ? unique() : exact(scope,{tokens:{expected_revision:'0'}})
            );
            assert.equal(result.status,'blocked');
            assert.equal(controller.isReady(),false);
        });
        await pass(type+' transport error cannot grant CREATE',async()=>{
            const {controller,values}=make(type);
            const result=await controller.loadSelection(async()=>{throw new Error('transport');});
            assert.equal(result.status,'blocked');
            assert.equal(values().write_intent,'');
        });
        await pass(type+' exact-only response cannot masquerade as scope',async()=>{
            const {controller}=make(type);
            const result=await controller.loadSelection(async()=>exact({cycle_id:'11',record_date:'2026-10-13',animal_type:type==='ruminant'?'goat':''}));
            assert.equal(result.status,'blocked');
            assert.equal(controller.isReady(),false);
        });
        await pass(type+' delayed older scope response never changes later CREATE intent',async()=>{
            const {controller,scope,values}=make(type);
            let resolveOlder;
            const olderPromise=new Promise(resolve=>{resolveOlder=resolve;});
            const first=controller.loadSelection(async()=>olderPromise);
            scope.record_date='2026-10-14';
            const second=await controller.loadSelection(async()=>absent());
            assert.equal(second.mode,'create');
            resolveOlder(unique());
            const outdated=await first;
            assert.equal(outdated.status,'stale');
            assert.equal(values().write_intent,'create');
            assert.equal(controller.isReady(),true);
        });
        await pass(type+' delayed exact reply after new selection cannot populate earlier EDIT',async()=>{
            const {controller,scope,values}=make(type);
            let resolveExact;
            const exactPromise=new Promise(resolve=>{resolveExact=resolve;});
            const snapshot={...scope};
            const first=controller.loadSelection(async q=>q.mode==='scope'?unique():exactPromise);
            // Allow first async function to reach the second request.
            await Promise.resolve();
            await Promise.resolve();
            scope.record_date='2026-10-15';
            const second=await controller.loadSelection(async()=>absent());
            assert.equal(second.mode,'create');
            resolveExact(exact(snapshot));
            const outdated=await first;
            assert.equal(outdated.status,'stale');
            assert.equal(values().write_intent,'create');
            assert.equal(values().record_id,'');
        });
        await pass(type+' invalid submitted hidden revision clears tokens but preserves operational fields',async()=>{
            const {controller,scope,controls,values}=make(type);
            const result=await controller.loadSelection(async q=>q.mode==='scope'?unique():exact(scope));
            assert.equal(result.mode,'edit');
            controls.expected_revision.value='999';
            assert.equal(controller.isReady(),false);
            assert.equal(values().record_id,'');
            assert.equal(values().write_intent,'');
        });
        await pass(type+' synthetic display-only population row is never editable',async()=>{
            const {controller,scope}=make(type);
            const result=await controller.loadSelection(async q=>
                q.mode==='scope'?unique():exact(scope,{record:{is_population_activity_only:true}})
            );
            assert.equal(result.status,'blocked');
            assert.equal(controller.isReady(),false);
        });
    }
    await pass('NULL cycle exact request stays NULL-scope, not all-cycles or zero',async()=>{
        const {controller,scope}=make();
        scope.cycle_id='';
        const reads=[];
        const result=await controller.loadSelection(async q=>{
            reads.push(q);
            return q.mode==='scope'?unique():exact(scope);
        });
        assert.equal(result.mode,'edit');
        assert.equal(reads[0].cycle_id,'');
        assert.equal(reads[1].cycle_id,'');
    });
    await pass('changing Ruminant species after load makes previous edit stale',async()=>{
        const {controller,scope}=make('ruminant');
        const result=await controller.loadSelection(async q=>q.mode==='scope'?unique():exact(scope));
        assert.equal(result.mode,'edit');
        scope.animal_type='sheep';
        assert.equal(controller.isReady(),false);
    });
    console.log('B12_SCOPE_EXACT_MODAL_PURE=PASS');
    console.log('PASS_COUNT='+checks);
})().catch(error=>{
    console.error('B12_SCOPE_EXACT_MODAL_PURE=FAIL',error);
    process.exitCode=1;
});
