'use strict';
/*
 * B12 stage-only end-to-end module composition in a fake browser: NO HTTP,
 * database, application bootstrap, deployment, permissions or CI execution.
 */
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');

const win={URLSearchParams};
const context={window:win};
const root=path.join(__dirname,'../..');
for (const name of [
    'daily-record-revision-modal.js',
    'daily-record-revision-transport.js',
    'daily-record-revision-editor.js',
    'daily-record-revision-submit.js',
    'daily-record-revision-workspace.js'
]) {
    vm.runInNewContext(
        fs.readFileSync(path.join(root,'assets/js',name),'utf8'),
        context, {filename:name}
    );
}
const keys=['write_intent','record_id','expected_revision',
    'original_cycle_id','original_record_date','original_animal_type'];
class FakeFormData {
    constructor(form) {
        this.map=new Map();
        const put=(key,value)=>this.map.set(key,[String(value)]);
        put('csrf_token','synthetic-csrf');
        put('record_date',form.inputs.recordDate.value);
        put('remarks',form.inputs.remarks.value);
        put('opening_stock',form.inputs.openingStock.value);
        put('mortality',form.inputs.mortality.value);
        put('feed_item_id','5');
        put('feed_consumption','1');
        if (form.type==='ruminant') {
            put('animal_type',form.inputs.animalTypeHidden.value);
        } else {
            put('cycle_id','11');
        }
        for (const k of keys) put(k,form.tokens[k].value);
    }
    getAll(k){return this.map.get(k)||[];}
    set(k,v){this.map.set(k,[String(v)]);}
    *[Symbol.iterator]() {
        for(const [k,vals] of this.map) for(const v of vals) yield [k,v];
    }
}
const input=(value='')=>{
    const listeners={};
    return {value,disabled:false,listeners,
        addEventListener(k,fn){(listeners[k]??=[]).push(fn);},
        dispatch(k){for(const fn of listeners[k]||[])fn();}
    };
};
function form(type) {
    const inputs={
        selectedDate:input('2026-10-13'),
        recordDate:input(),
        animalType:input('goat'),
        animalTypeHidden:input(),
        openingStock:input('100'),
        mortality:input('2'),
        remarks:input('draft before opening'),
        birdsAge:input(),
        eggProduction:input(),
        feedConsumption:input(),
        feedItemId:input(),
        waterConsumption:input()
    };
    const tokens=Object.fromEntries(keys.map(k=>[k,input()]));
    const events={};
    return {
        type,inputs,tokens,
        querySelector(s){return inputs[s.slice(1)]||null;},
        querySelectorAll(s){
            const match=/name="([a-z_]+)"/.exec(s);
            return match && tokens[match[1]]?[tokens[match[1]]]:[];
        },
        addEventListener(k,fn){(events[k]??=[]).push(fn);},
        listeners(k){return (events[k]||[]).length;},
        dispatch(k){
            const event={prevented:false,preventDefault(){this.prevented=true;}};
            for(const fn of events[k]||[])fn(event);
            return event;
        }
    };
}
function json(status,payload,type='application/json; charset=utf-8') {
    return {status,ok:status>=200&&status<300,
        headers:{get(k){return k.toLowerCase()==='content-type'?type:null;}},
        json:async()=>payload};
}
function make(type, scenario={}) {
    const f=form(type),sent=[],pending=[],ready=[],blocked=[],saved=[];
    const fetchImpl=async(url,opts)=>{
        sent.push({url,opts});
        if(opts.method==='GET'){
            const params=new URLSearchParams(url.split('?')[1]);
            if(scenario.readStatus) {
                return json(scenario.readStatus,{success:false,code:'revision_cutover_disabled'});
            }
            if(params.get('mode')==='scope') {
                return json(200,{success:true,mode:'scope',data:scenario.absent
                    ?{status:'absent',record_id:null}
                    :{status:'unique',record_id:'19'}});
            }
            const species=type==='ruminant'?{animal_type:'goat'}:{};
            return json(200,{success:true,mode:'exact',data:{
                record:{id:'19',record_date:params.get('record_date'),
                    cycle_id:'11',opening_stock:120,mortality:2,
                    remarks:'loaded row',...species},
                form_tokens:{
                    write_intent:'edit',record_id:'19',expected_revision:'4',
                    original_cycle_id:'11',
                    original_record_date:params.get('record_date'),
                    original_animal_type:type==='ruminant'?'goat':''
                }
            }});
        }
        if (opts.method==='POST') {
            if(scenario.postImpl) return scenario.postImpl(url,opts);
            return json(200,{success:true,record_id:scenario.absent?'27':'19',
                record_revision:scenario.absent?'1':'5',
                continuity_affected:0});
        }
        throw new Error('Unexpected HTTP method');
    };
    const callbacks={
        onPending:()=>pending.push(true),
        onReady:x=>ready.push(x),
        onBlocked:x=>blocked.push(x),
        onSaved:x=>saved.push(x)
    };
    const options={type,cycleId:'11',form:f,fetchImpl,
        FormDataImpl:FakeFormData,URLSearchParamsImpl:URLSearchParams,
        ...callbacks};
    const workspace=win.ReneeDailyRecordRevisionWorkspace.createWorkspace(options);
    return {workspace,f,sent,pending,ready,blocked,saved};
}
const wait=()=>new Promise(resolve=>setImmediate(resolve));
let tests=0;
async function check(label,callback) {
    await callback();
    console.log('PASS: '+label);
    tests++;
}
(async()=>{
    for(const type of ['layer','broiler','ruminant']) {
        await check(type+' composes exact scoped GET and trusted edit POST',async()=>{
            const v=make(type);
            assert.equal(v.sent.length,0);
            assert.equal(v.f.listeners('submit'),0);
            v.workspace.bind();
            const result=await v.workspace.select();
            assert.equal(result.status,'ready');
            assert.equal(result.mode,'edit');
            assert.equal(v.sent.length,2);
            assert.equal(v.sent[0].opts.method,'GET');
            assert.equal(v.sent[1].opts.method,'GET');
            assert.equal(v.workspace.isReady(),true);
            assert.equal(v.f.inputs.openingStock.value,'120');
            assert.equal(v.f.tokens.expected_revision.value,'4');
            v.f.inputs.remarks.value='unsaved user draft';
            const submitEvent=v.f.dispatch('submit');
            assert.equal(submitEvent.prevented,true);
            await wait();
            assert.equal(v.sent.length,3);
            assert.equal(v.sent[2].opts.method,'POST');
            assert.equal(v.sent[2].url,
                (type==='layer'?'layers':type==='broiler'?'broiler':'ruminant')+
                '_daily_record.php?cycle_id=11');
            const body=new URLSearchParams(v.sent[2].opts.body);
            assert.equal(body.get('remarks'),'unsaved user draft');
            assert.equal(body.get('write_intent'),'edit');
            assert.equal(body.get('expected_revision'),'4');
            assert.equal(body.get('save_record'),'1');
            assert.equal(body.get('animal_type'),type==='ruminant'?'goat':null);
            assert.equal(v.saved.length,1);
            assert.equal(v.blocked.length,0);
            assert.equal(v.f.tokens.expected_revision.value,'');
            assert.equal(v.f.inputs.remarks.value,'unsaved user draft');
        });
        await check(type+' only promotes verified absent scope to CREATE',async()=>{
            const v=make(type,{absent:true});
            v.workspace.bind();
            const result=await v.workspace.select();
            assert.equal(result.status,'ready');
            assert.equal(result.mode,'create');
            assert.equal(v.f.tokens.record_id.value,'');
            assert.equal(v.f.tokens.expected_revision.value,'');
            v.f.inputs.remarks.value='new draft';
            assert.equal(v.f.dispatch('submit').prevented,true);
            await wait();
            assert.equal(v.sent.filter(c=>c.opts.method==='POST').length,1);
            assert.equal(v.saved.length,1);
        });
    }
    await check('409 conflict preserves all unsaved business fields and clears CAS tokens',async()=>{
        const v=make('layer',{postImpl:async()=>json(409,{
            success:false,code:'record_conflict'
        })});
        v.workspace.bind();
        await v.workspace.select();
        v.f.inputs.remarks.value='my original draft';
        v.f.dispatch('submit');
        await wait();
        assert.equal(v.saved.length,0);
        assert.equal(v.blocked.at(-1).reason,'record_conflict');
        assert.equal(v.f.inputs.remarks.value,'my original draft');
        assert.equal(v.f.tokens.expected_revision.value,'');
        assert.equal(v.workspace.isReady(),false);
    });
    await check('503 read leaves draft and never creates or POSTs',async()=>{
        const v=make('broiler',{readStatus:503});
        v.workspace.bind();
        v.f.inputs.remarks.value='keep';
        const selection=await v.workspace.select();
        assert.equal(selection.status,'blocked');
        assert.equal(v.f.inputs.remarks.value,'keep');
        assert.equal(v.f.tokens.write_intent.value,'');
        assert.equal(v.f.dispatch('submit').prevented,true);
        assert.equal(v.sent.filter(x=>x.opts.method==='POST').length,0);
    });
    await check('all-cycles/NULL/non-string/oversized cycles fail before request',async()=>{
        for (const bad of [0,'0',null,'',11,'9223372036854775808']) {
            let count=0;
            const config={type:'layer',cycleId:bad,form:form('layer'),
                fetchImpl:async()=>{count++;return json(500,{})},
                FormDataImpl:FakeFormData,URLSearchParamsImpl:URLSearchParams,
                onPending(){},onReady(){},onBlocked(){},onSaved(){}};
            assert.throws(()=>win.ReneeDailyRecordRevisionWorkspace.createWorkspace(config));
            assert.equal(count,0);
        }
    });
    await check('missing success/error callback blocks unsafe browser activation',async()=>{
        const v=make('layer');
        assert.throws(()=>win.ReneeDailyRecordRevisionWorkspace.createWorkspace({
            type:'layer',cycleId:'11',form:v.f,fetchImpl:async()=>json(500,{}),
            onPending(){},onReady(){},onBlocked(){}
        }));
    });
    await check('missing shared module refuses to initialize',async()=>{
        const transport=win.ReneeDailyRecordRevisionTransport;
        delete win.ReneeDailyRecordRevisionTransport;
        try {
            const v=make('layer');
            assert.fail('unexpected workspace: '+v.workspace);
        }catch(error) {
            assert.match(error.message,/modules are not available/);
        }finally {
            win.ReneeDailyRecordRevisionTransport=transport;
        }
    });
    await check('selection ticket blocks stale POST completion after new date',async()=>{
        let resolvePost;
        const pendingPost=new Promise(resolve=>{resolvePost=resolve;});
        const v=make('layer',{postImpl:()=>pendingPost});
        v.workspace.bind();
        await v.workspace.select();
        v.f.dispatch('submit');
        v.f.inputs.selectedDate.value='2026-10-14';
        await v.workspace.select();
        assert.equal(v.f.tokens.original_record_date.value,'2026-10-14');
        resolvePost(json(200,{success:true,record_id:'19',
            record_revision:'5',continuity_affected:0}));
        await wait();
        await wait();
        assert.equal(v.saved.length,0);
        assert.equal(v.f.tokens.original_record_date.value,'2026-10-14');
    });
    await check('one binder invocation cannot duplicate submit listeners',async()=>{
        const v=make('ruminant');
        v.workspace.bind();v.workspace.bind();
        assert.equal(v.f.listeners('submit'),1);
    });
    console.log('B12_WORKSPACE_COMPOSITION_PURE=PASS');
    console.log('PASS_COUNT='+tests);
})().catch(err=>{
    console.error('B12_WORKSPACE_COMPOSITION_PURE=FAIL',err);
    process.exitCode=1;
});
