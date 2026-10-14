'use strict';
/**
 * B12 future browser bridge — pure Node DOM stubs. Never opens HTTP,
 * app config, MariaDB, prod/staging, or starts CI.
 * This does not replace real E2E, seven-writer CAS, or schema evidence.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.join(__dirname, '../..');
const win = {};
const context = {window:win};
for (const file of ['assets/js/daily-record-revision-modal.js',
                    'assets/js/daily-record-revision-editor.js']) {
    vm.runInNewContext(fs.readFileSync(path.join(root,file),'utf8'), context,
        {filename:file});
}
const names = ['write_intent','record_id','expected_revision',
               'original_cycle_id','original_record_date','original_animal_type'];
let count=0;
async function check(label, run) {
    await run();
    count += 1;
    console.log('PASS: ' + label);
}
function input(value='') {
    const listeners={};
    return {
        value, disabled:false, listeners,
        addEventListener(name,fn){(listeners[name]??=[]).push(fn);},
        dispatch(name){for(const fn of listeners[name]??[])fn();}
    };
}
function build(type, overrides={}) {
    const inputs={
        selectedDate:input('2026-10-13'),recordDate:input(),
        animalType:input('goat'),animalTypeHidden:input(),
        openingStock:input(),mortality:input(),eggProduction:input(),
        birdsAge:input(),feedConsumption:input(),feedConsumptionUnit:input(),
        layingRate:input(),feedItemId:input(),waterConsumption:input()
    };
    const tokens=Object.fromEntries(names.map(n=>[n,input()]));
    const events={};
    const form={
        querySelector(selector){return inputs[selector.slice(1)]||null;},
        querySelectorAll(selector){
            const m=/name="([a-z_]+)"/.exec(selector);
            return m&&tokens[m[1]]?[tokens[m[1]]]:[];
        },
        addEventListener(name,fn){(events[name]??=[]).push(fn);},
        listenerCount(name){return (events[name]||[]).length;},
        dispatch(name,event={preventDefault(){this.prevented=true;},prevented:false}){
            for(const fn of events[name]||[])fn(event);
            return event;
        }
    };
    const ready=[];
    const blocked=[];
    const saved=[];
    const reader=overrides.request || (async query=>{
        if(query.mode==='scope')return {success:true,mode:'scope',data:{
            status:'unique',record_id:19
        }};
        return exact(query);
    });
    const editor=win.ReneeDailyRecordRevisionEditor.createEditor({
        type,form,cycleId:overrides.cycleId??'11',request:reader,
        onReady:r=>{
            ready.push(r);
            if (overrides.failReady) throw new Error('Injected rendering failure');
        },onBlocked:e=>blocked.push(e),
        onSaved:e=>saved.push(e),
        onSubmit:overrides.onSubmit,
    });
    return {editor,inputs,tokens,form,ready,blocked,saved};
}
function exact(query, custom={}) {
    return {
        success:true,mode:'exact',data:{
            record:{
                id:'19',cycle_id:query.cycle_id,record_date:query.record_date,
                ...(query.type==='ruminant'?{animal_type:query.animal_type}:{}),
                opening_stock:0,mortality:2,birds_age:40,
                ...custom.record
            },
            form_tokens:{
                write_intent:'edit',record_id:'19',expected_revision:'4',
                original_cycle_id:query.cycle_id,
                original_record_date:query.record_date,
                original_animal_type:query.type==='ruminant'?query.animal_type:'',
                ...custom.tokens
            }
        }
    };
}
const absent=()=>({success:true,mode:'scope',
                    data:{status:'absent',record_id:null}});
(async()=>{
    for(const type of ['layer','broiler','ruminant']) {
        await check(type+' edit renders exact fields including 0 and immutable token',async()=>{
            const v=build(type);
            const outcome=await v.editor.select();
            assert.equal(outcome.status,'ready');
            assert.equal(outcome.mode,'edit');
            assert.equal(v.inputs.openingStock.value,'0');
            assert.equal(v.inputs.mortality.value,'2');
            assert.equal(v.tokens.record_id.value,'19');
            assert.equal(v.tokens.expected_revision.value,'4');
            assert.equal(v.editor.isReady(),true);
            assert.equal(v.inputs.animalType.disabled,type==='ruminant');
        });
        await check(type+' CREATE has no row ID or revision',async()=>{
            const v=build(type,{request:async()=>absent()});
            const out=await v.editor.select();
            assert.equal(out.mode,'create');
            assert.equal(v.tokens.write_intent.value,'create');
            assert.equal(v.tokens.record_id.value,'');
            assert.equal(v.tokens.expected_revision.value,'');
            assert.equal(v.editor.isReady(),true);
        });
        await check(type+' scope ambiguity disables the form without field overwrite',async()=>{
            const v=build(type,{request:async()=>({success:true,mode:'scope',data:{
                status:'ambiguous',record_id:null
            }})});
            v.inputs.openingStock.value='123';
            const outcome=await v.editor.select();
            assert.equal(outcome.status,'blocked');
            assert.equal(v.editor.isReady(),false);
            assert.equal(v.inputs.openingStock.value,'123');
            assert.equal(v.tokens.write_intent.value,'');
            assert.equal(v.blocked.length,1);
        });
        await check(type+' mismatched exact ID never populates business fields',async()=>{
            const v=build(type,{request:async q=>q.mode==='scope'?{
                success:true,mode:'scope',data:{status:'unique',record_id:19}
            }:exact(q,{record:{id:'20'}})});
            v.inputs.openingStock.value='321';
            const r=await v.editor.select();
            assert.equal(r.status,'blocked');
            assert.equal(v.inputs.openingStock.value,'321');
            assert.equal(v.editor.isReady(),false);
        });
        await check(type+' reset clears tokens and blocks subsequent submit',async()=>{
            const v=build(type);
            v.editor.bind();
            await v.editor.select();
            const before=v.form.dispatch('submit');
            assert.equal(before.prevented,true);
            assert.equal(v.blocked.at(-1).reason,'submit_transport_missing');
            v.form.dispatch('reset');
            const after=v.form.dispatch('submit');
            assert.equal(after.prevented,true);
            assert.equal(v.editor.isReady(),false);
            assert.equal(v.tokens.expected_revision.value,'');
        });
        await check(type+' changing selected date revokes old sidepanel guard',async()=>{
            const v=build(type);
            await v.editor.select();
            const latest=v.ready.at(-1);
            let called=0;
            assert.equal(latest.guard(()=>called++),true);
            v.inputs.selectedDate.value='2026-10-14';
            assert.equal(latest.guard(()=>called++),false);
            assert.equal(called,1);
            assert.equal(v.editor.isReady(),false);
        });
        await check(type+' delayed old fetch cannot replace new CREATE values',async()=>{
            let resolveOld;
            const old=new Promise(resolve=>{resolveOld=resolve;});
            const v=build(type,{
                request:q=>q.record_date==='2026-10-13'?old:Promise.resolve(absent())
            });
            const first=v.editor.select();
            v.inputs.selectedDate.value='2026-10-14';
            const second=await v.editor.select();
            assert.equal(second.status,'ready');
            assert.equal(second.mode,'create');
            resolveOld(absent());
            assert.equal((await first).status,'stale');
            assert.equal(v.tokens.write_intent.value,'create');
            assert.equal(v.editor.isReady(),true);
        });
    }
    await check('ruminant species change revokes EDIT after load',async()=>{
        const v=build('ruminant');
        await v.editor.select();
        v.inputs.animalType.value='sheep';
        assert.equal(v.editor.isReady(),false);
        assert.equal(v.tokens.record_id.value,'');
    });
    await check('schema/transport failure does not silently create',async()=>{
        const v=build('layer',{request:async()=>{throw new Error('schema unavailable');}});
        const r=await v.editor.select();
        assert.equal(r.status,'blocked');
        assert.equal(v.editor.isReady(),false);
        assert.equal(v.tokens.write_intent.value,'');
    });
    await check('a rendering callback exception revokes loaded revision',async()=>{
        const v=build('layer',{failReady:true});
        const result=await v.editor.select();
        assert.equal(result.status,'blocked');
        assert.equal(v.tokens.record_id.value,'');
        assert.equal(v.tokens.expected_revision.value,'');
        assert.equal(v.editor.isReady(),false);
    });
    await check('binding submit guard twice does not duplicate listener',async()=>{
        const v=build('broiler');
        v.editor.bind();
        v.editor.bind();
        assert.equal(v.form.listenerCount('submit'),1);
        assert.equal(v.form.listenerCount('reset'),1);
    });
    await check('invalid cycle selector is rejected at setup',async()=>{
        assert.throws(()=>build('layer',{cycleId:'0'}));
    });
    await check('ready EDIT never performs native POST without B12 sender',async()=>{
        const v=build('layer');
        v.editor.bind();
        await v.editor.select();
        const event=v.form.dispatch('submit');
        assert.equal(event.prevented,true);
        assert.equal(v.blocked.at(-1).reason,'submit_transport_missing');
        assert.equal(v.tokens.expected_revision.value,'4');
    });
    await check('successful shared submit receives immutable mode and cycle; preserves draft',async()=>{
        let sent=null;
        const v=build('layer',{onSubmit:input=>{
            sent=input;
            return Promise.resolve({
                status:'saved', record_id:'19', record_revision:'5',
                continuity_affected:0
            });
        }});
        v.editor.bind();
        await v.editor.select();
        v.inputs.openingStock.value='777';
        const event=v.form.dispatch('submit');
        assert.equal(event.prevented,true);
        await new Promise(resolve=>setImmediate(resolve));
        assert.equal(sent.type,'layer');
        assert.equal(sent.mode,'edit');
        assert.equal(sent.cycleId,'11');
        assert.equal(sent.guard(()=>{}),false);
        assert.equal(v.saved.length,1);
        assert.equal(v.inputs.openingStock.value,'777');
        assert.equal(v.tokens.expected_revision.value,'');
    });
    await check('409 conflict invalidates identity without erasing user values',async()=>{
        const v=build('ruminant',{onSubmit:()=>Promise.resolve({status:'conflict'})});
        v.editor.bind();
        await v.editor.select();
        v.inputs.openingStock.value='123';
        const event=v.form.dispatch('submit');
        assert.equal(event.prevented,true);
        await new Promise(resolve=>setImmediate(resolve));
        assert.equal(v.blocked.at(-1).reason,'record_conflict');
        assert.equal(v.inputs.openingStock.value,'123');
        assert.equal(v.tokens.expected_revision.value,'');
        assert.equal(v.saved.length,0);
    });
    await check('duplicate modal submit cannot issue two in-flight operations',async()=>{
        let release;
        const pending=new Promise(resolve=>{release=resolve;});
        let calls=0;
        const v=build('broiler',{onSubmit:()=>{calls++;return pending;}});
        v.editor.bind();
        await v.editor.select();
        assert.equal(v.form.dispatch('submit').prevented,true);
        assert.equal(v.form.dispatch('submit').prevented,true);
        assert.equal(calls,1);
        assert.equal(v.blocked.at(-1).reason,'submit_pending');
        release({status:'blocked'});
        await new Promise(resolve=>setImmediate(resolve));
        assert.equal(v.tokens.record_id.value,'');
    });
    await check('stale response never invalidates newer selection tokens',async()=>{
        let release;
        const pending=new Promise(resolve=>{release=resolve;});
        const v=build('layer',{onSubmit:()=>pending});
        v.editor.bind();
        await v.editor.select();
        v.form.dispatch('submit');
        v.inputs.selectedDate.value='2026-10-14';
        await v.editor.select();
        assert.equal(v.tokens.original_record_date.value,'2026-10-14');
        release({status:'saved'});
        await new Promise(resolve=>setImmediate(resolve));
        assert.equal(v.tokens.original_record_date.value,'2026-10-14');
        assert.equal(v.saved.length,0);
    });
    console.log('B12_EDITOR_BRIDGE_PURE=PASS');
    console.log('PASS_COUNT='+count);
})().catch(error=>{
    console.error('B12_EDITOR_BRIDGE_PURE=FAIL',error);
    process.exitCode=1;
});
