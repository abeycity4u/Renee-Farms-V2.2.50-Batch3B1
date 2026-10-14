'use strict';
/*
 * B12 inert POST transport contract with fake FormData and fetch. No HTTP,
 * DB, session, app bootstrap, browser, load test, deployment or CI.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const win = {URLSearchParams};
vm.runInNewContext(
    fs.readFileSync(path.join(__dirname, '../../assets/js/daily-record-revision-submit.js'), 'utf8'),
    {window: win}, {filename: 'daily-record-revision-submit.js'}
);

class FakeFormData {
    constructor(form) {
        this.data = new Map();
        for (const [name, value] of Object.entries(form.fields)) {
            this.data.set(name, Array.isArray(value) ? [...value] : [value]);
        }
    }
    getAll(name) { return this.data.get(name) || []; }
    set(name, value) { this.data.set(name, [value]); }
    *[Symbol.iterator]() {
        for (const [name, values] of this.data) {
            for (const value of values) yield [name, value];
        }
    }
}

const routes = {
    layer: 'layers_daily_record.php',
    broiler: 'broiler_daily_record.php',
    ruminant: 'ruminant_daily_record.php'
};
const noMessage = 'draft stays here';
function form(type, mode='edit') {
    const f = {
        csrf_token: 'csrf-synthetic',
        record_date: '2026-10-13',
        write_intent: mode,
        record_id: mode === 'edit' ? '19' : '',
        expected_revision: mode === 'edit' ? '4' : '',
        original_cycle_id: mode === 'edit' ? '11' : '',
        original_record_date: mode === 'edit' ? '2026-10-13' : '',
        original_animal_type: mode === 'edit' && type === 'ruminant' ? 'goat' : '',
        remarks: noMessage,
        opening_stock: '120',
        mortality: '2',
        feed_consumption: '1.25'
    };
    if (type === 'ruminant') {
        f.animal_type = 'goat';
    } else {
        f.cycle_id = '11';
    }
    return {fields:f};
}
function response(status, payload, contentType='application/json; charset=utf-8') {
    return {
        status, ok:status>=200&&status<300,
        headers:{get(k){return k.toLowerCase()==='content-type'?contentType:null;}},
        async json(){return payload;}
    };
}
function success(mode='edit', overrides={}) {
    return response(200,{
        success:true, record_id:mode==='edit'?19:27,
        record_revision:mode==='edit'?5:1,
        continuity_affected:0, ...overrides
    });
}
function client(fetchImpl) {
    return win.ReneeDailyRecordRevisionSubmit.createSender({
        fetchImpl, FormDataImpl:FakeFormData, URLSearchParamsImpl:URLSearchParams
    });
}
const accepted = () => true;
let n=0;
async function check(name, fn) {
    await fn();
    console.log('PASS: '+name);
    n += 1;
}
(async () => {
    for (const type of Object.keys(routes)) {
        await check(type+' EDIT sends same-origin form body and original CAS revision',async()=>{
            const calls=[];
            const f=form(type);
            const send=client(async(url,opts)=>{calls.push([url,opts]);return success();});
            const result=await send({type,form:f,cycleId:'11',mode:'edit',guard:accepted});
            assert.equal(result.status,'saved');
            assert.equal(result.record_id,'19');
            assert.equal(result.record_revision,'5');
            assert.equal(calls.length,1);
            assert.equal(calls[0][0],routes[type]+'?cycle_id=11');
            assert.equal(calls[0][1].method,'POST');
            assert.equal(calls[0][1].credentials,'same-origin');
            assert.equal(calls[0][1].redirect,'error');
            assert.equal(calls[0][1].cache,'no-store');
            assert.equal(calls[0][1].headers.Accept,'application/json');
            const body=new URLSearchParams(calls[0][1].body);
            assert.equal(body.get('save_record'),'1');
            assert.equal(body.get('expected_revision'),'4');
            assert.equal(body.get('original_record_date'),'2026-10-13');
            assert.equal(body.get('csrf_token'),'csrf-synthetic');
            assert.equal(body.get('remarks'),noMessage);
            assert.equal(body.get('animal_type'),type==='ruminant'?'goat':null);
            assert.equal(f.fields.remarks,noMessage);
            assert.equal(Object.hasOwn(f.fields,'save_record'),false);
        });
        await check(type+' CREATE has empty edit identity and POST save trigger',async()=>{
            const f=form(type,'create');
            const result=await client(async()=>success('create'))({
                type,form:f,cycleId:'11',mode:'create',guard:accepted
            });
            assert.equal(result.status,'saved');
            assert.equal(result.record_revision,'1');
            assert.equal(f.fields.record_id,'');
            assert.equal(f.fields.expected_revision,'');
        });
        await check(type+' 409 conflict keeps original draft, never retries as CREATE',async()=>{
            let calls=0;
            const f=form(type);
            const send=client(async()=>{
                calls++;
                return response(409,{success:false,code:'record_conflict'});
            });
            const result=await send({type,form:f,cycleId:'11',mode:'edit',guard:accepted});
            assert.equal(result.status,'conflict');
            assert.equal(calls,1);
            assert.equal(f.fields.remarks,noMessage);
            assert.equal(f.fields.expected_revision,'4');
        });
    }
    await check('failed 401/403/419/422/500/503 statuses never imply CREATE',async()=>{
        for(const status of [401,403,419,422,500,503]){
            const send=client(async()=>response(status,{success:false,code:'forbidden'}));
            assert.equal((await send({type:'layer',form:form('layer'),cycleId:'11',mode:'edit',guard:accepted})).status,'blocked');
        }
    });
    await check('unrecognized conflict JSON is blocked rather than trusted',async()=>{
        const send=client(async()=>response(409,{success:false,code:'unknown'}));
        assert.equal((await send({type:'layer',form:form('layer'),cycleId:'11',mode:'edit',guard:accepted})).status,'blocked');
    });
    await check('login HTML and redirects cannot be mistaken for saved JSON',async()=>{
        const send=client(async()=>response(200,{success:true},'text/html'));
        assert.equal((await send({type:'layer',form:form('layer'),cycleId:'11',mode:'edit',guard:accepted})).status,'blocked');
    });
    await check('network failure blocks and preserves fields',async()=>{
        const f=form('layer');
        const send=client(async()=>{throw new Error('network down');});
        assert.equal((await send({type:'layer',form:f,cycleId:'11',mode:'edit',guard:accepted})).status,'blocked');
        assert.equal(f.fields.remarks,noMessage);
    });
    await check('duplicate original revision tokens fail before network',async()=>{
        const f=form('layer');f.fields.expected_revision=['4','4'];
        let called=0;
        const send=client(async()=>{called++;return success();});
        assert.equal((await send({type:'layer',form:f,cycleId:'11',mode:'edit',guard:accepted})).status,'blocked');
        assert.equal(called,0);
    });
    await check('missing CSRF fails before network',async()=>{
        const f=form('layer');delete f.fields.csrf_token;
        let called=0;
        const send=client(async()=>{called++;return success();});
        assert.equal((await send({type:'layer',form:f,cycleId:'11',mode:'edit',guard:accepted})).status,'blocked');
        assert.equal(called,0);
    });
    await check('cycle=0 all-cycles scope never aliases a persisted cycle',async()=>{
        let called=0;const send=client(async()=>{called++;return success();});
        assert.equal((await send({type:'layer',form:form('layer'),cycleId:'0',mode:'edit',guard:accepted})).status,'blocked');
        assert.equal(called,0);
    });
    await check('changed original record date prevents POST',async()=>{
        const f=form('broiler');f.fields.original_record_date='2026-10-14';
        let called=0;const send=client(async()=>{called++;return success();});
        assert.equal((await send({type:'broiler',form:f,cycleId:'11',mode:'edit',guard:accepted})).status,'blocked');
        assert.equal(called,0);
    });
    await check('cross-species Ruminant identity cannot be submitted',async()=>{
        const f=form('ruminant');f.fields.animal_type='cattle';
        let called=0;const send=client(async()=>{called++;return success();});
        assert.equal((await send({type:'ruminant',form:f,cycleId:'11',mode:'edit',guard:accepted})).status,'blocked');
        assert.equal(called,0);
    });
    await check('CREATE with preexisting ID is rejected',async()=>{
        const f=form('layer','create');f.fields.record_id='19';
        let called=0;const send=client(async()=>{called++;return success('create');});
        assert.equal((await send({type:'layer',form:f,cycleId:'11',mode:'create',guard:accepted})).status,'blocked');
        assert.equal(called,0);
    });
    await check('invalid server record ID or revision is fail-closed',async()=>{
        for(const payload of [
            {record_id:20},{record_revision:6},{record_revision:0},
            {record_revision:9007199254740992}
        ]){
            const send=client(async()=>success('edit',payload));
            assert.equal((await send({type:'layer',form:form('layer'),cycleId:'11',mode:'edit',guard:accepted})).status,'blocked');
        }
    });
    await check('stale selection after network response never applies result',async()=>{
        let current=true;
        const send=client(async()=>{current=false;return success();});
        const out=await send({type:'layer',form:form('layer'),cycleId:'11',mode:'edit',
            guard:()=>current});
        assert.equal(out.status,'stale');
    });
    await check('stale pre-send ticket never contacts endpoint',async()=>{
        let count=0;
        const send=client(async()=>{count++;return success();});
        const out=await send({type:'layer',form:form('layer'),cycleId:'11',mode:'edit',
            guard:()=>false});
        assert.equal(out.status,'stale');
        assert.equal(count,0);
    });
    await check('same form has a single in-flight POST',async()=>{
        let resolve;
        let count=0;
        const pending=new Promise(r=>{resolve=r;});
        const send=client(async()=>{count++;return pending;});
        const f=form('layer'),args={type:'layer',form:f,cycleId:'11',mode:'edit',guard:accepted};
        const first=send(args);
        const second=await send(args);
        assert.equal(second.status,'blocked');
        assert.equal(second.reason,'submit_pending');
        assert.equal(count,1);
        resolve(success());
        assert.equal((await first).status,'saved');
    });
    await check('closed type map forbids caller-controlled endpoint',async()=>{
        let count=0;
        const send=client(async()=>{count++;return success();});
        const result=await send({type:'../admin',form:form('layer'),cycleId:'11',mode:'edit',guard:accepted});
        assert.equal(result.status,'blocked');
        assert.equal(count,0);
    });
    console.log('B12_SUBMIT_TRANSPORT_PURE=PASS');
    console.log('PASS_COUNT='+n);
})().catch(err=>{
    console.error('B12_SUBMIT_TRANSPORT_PURE=FAIL',err);
    process.exitCode=1;
});
