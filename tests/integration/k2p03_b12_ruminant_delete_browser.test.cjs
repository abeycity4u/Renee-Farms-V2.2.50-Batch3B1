'use strict';
/*
 * B12 source-only fake browser tests for the ORIGINAL Ruminant row-form
 * tokens and opt-in AJAX binding. No HTTP, DB, application bootstrap, CI.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const win = {URLSearchParams};
const base=path.join(__dirname, '../../assets/js');
for (const filename of ['daily-record-revision-delete.js',
                        'daily-record-revision-ruminant-delete.js']) {
    vm.runInNewContext(
        fs.readFileSync(path.join(base,filename),'utf8'),
        {window:win}, {filename}
    );
}
class FakeFormData {
    constructor(form) {
        this.map = new Map();
        for (const [name,value] of Object.entries(form.fields)) {
            this.map.set(name,Array.isArray(value)?value.slice():[value]);
        }
    }
    getAll(name) { return this.map.get(name)||[]; }
    set(name,value) { this.map.set(name,[value]); }
    *[Symbol.iterator]() {
        for(const [name,values] of this.map) {
            for (const value of values) yield [name,value];
        }
    }
}
function form(overrides={},legacy=false,marked=true) {
    const listeners={};
    const fields={
        csrf_token:'test-csrf',record_id:'19',
        write_intent:'delete',expected_revision:'4',
        original_cycle_id:'11',original_record_date:'2026-10-13',
        original_animal_type:'goat',
        ...overrides
    };
    return {
        fields, dataset:{b12RevisionDelete:marked?'ruminant':''},
        hasAttribute(name){return legacy&&name==='data-confirm';},
        addEventListener(name,fn){(listeners[name]??=[]).push(fn);},
        dispatch(name){
            const evt={prevented:false,preventDefault(){this.prevented=true;}};
            for(const fn of listeners[name]||[])fn(evt);
            return evt;
        },
        listenerCount(name){return (listeners[name]||[]).length;}
    };
}
function response(status,payload,contentType='application/json; charset=UTF-8') {
    return {
        ok:status>=200&&status<300,status,
        headers:{get(name){return name==='content-type'?contentType:null;}},
        async json(){return payload;}
    };
}
const success=()=>response(200,{success:true,code:'deleted',record_id:19});
const conflict=()=>response(409,{success:false,code:'record_conflict'});
const client=fetchImpl=>win.ReneeDailyRecordRevisionDelete.createRuminantFormSender({
    fetchImpl,FormDataImpl:FakeFormData,URLSearchParamsImpl:URLSearchParams
});
const microtasks=()=>new Promise(resolve=>setImmediate(resolve));
let count=0;
async function check(name,fn) {
    await fn();count++;console.log('PASS: '+name);
}
(async()=>{
    await check('original Ruminant form posts exact tokens and delete trigger',async()=>{
        const f=form(),calls=[];
        const send=client(async(url,opts)=>{calls.push([url,opts]);return success();});
        const result=await send({form:f});
        assert.equal(result.status,'deleted');
        assert.equal(result.record_id,'19');
        assert.equal(calls.length,1);
        assert.equal(calls[0][0],'ruminant_daily_record.php');
        const options=calls[0][1];
        assert.equal(options.method,'POST');
        assert.equal(options.credentials,'same-origin');
        assert.equal(options.redirect,'error');
        assert.equal(options.cache,'no-store');
        assert.equal(options.headers.Accept,'application/json');
        assert.equal(options.headers['X-Requested-With'],'XMLHttpRequest');
        const fields=new URLSearchParams(options.body);
        for(const [name,value] of Object.entries(f.fields)) {
            assert.equal(fields.get(name),value);
        }
        assert.equal(fields.get('delete_record'),'1');
        assert.equal(fields.get('id'),null);
        assert.equal(fields.get('farm_id'),null);
        assert.equal(f.fields.delete_record,undefined);
    });
    await check('Ruminant 409 returns conflict with no fallback or retry',async()=>{
        let calls=0;
        const send=client(async()=>{calls++;return conflict();});
        assert.equal((await send({form:form()})).status,'conflict');
        assert.equal(calls,1);
    });
    await check('409 revision_client_required is not mistaken for source conflict',async()=>{
        const send=client(async()=>response(409,{
            success:false,code:'revision_client_required'
        }));
        assert.equal((await send({form:form()})).status,'blocked');
    });
    await check('incomplete, duplicated or invented revision prevents DELETE',async()=>{
        const cases=[
            {write_intent:'edit'},{write_intent:''},
            {record_id:'0'},{record_id:'20.0'},
            {expected_revision:'0'},{expected_revision:['4','4']},
            {original_cycle_id:'0'},{original_cycle_id:'-1'},
            {original_record_date:'2026-02-30'},
            {original_animal_type:'elephant'},
            {original_animal_type:'Goat'},
            {csrf_token:''},{csrf_token:['a','b']},
            {farm_id:'7'},{type:'ruminant'},{id:'19'},{save_record:'1'}
        ];
        let calls=0;
        const send=client(async()=>{calls++;return success();});
        for(const data of cases) {
            assert.equal((await send({form:form(data)})).status,'blocked');
        }
        assert.equal(calls,0);
    });
    await check('legacy NULL cycle remains explicit empty, not all-cycles zero',async()=>{
        const send=client(async()=>success());
        assert.equal((await send({form:form({original_cycle_id:''})})).status,'deleted');
        assert.equal((await send({form:form({original_cycle_id:'0'})})).status,'blocked');
    });
    await check('HTTP 403/419/422/503/500 and HTML fail closed',async()=>{
        for(const status of [403,419,422,503,500]){
            const send=client(async()=>response(status,{success:false,code:'forbidden'}));
            assert.equal((await send({form:form()})).status,'blocked');
        }
        const send=client(async()=>response(200,{success:true,record_id:19},'text/html'));
        assert.equal((await send({form:form()})).status,'blocked');
    });
    await check('DELETE success must echo source ID and deleted code',async()=>{
        for(const payload of [
            {success:true,code:'deleted',record_id:20},
            {success:true,code:'deleted',record_id:null},
            {success:true,code:'save',record_id:19},
            {success:false,code:'deleted',record_id:19},
            {success:true,code:'deleted'}
        ]){
            const send=client(async()=>response(200,payload));
            assert.equal((await send({form:form()})).status,'blocked');
        }
    });
    await check('same Ruminant form cannot issue overlapping deletes',async()=>{
        let release,calls=0;
        const delayed=new Promise(resolve=>{release=resolve;});
        const send=client(async()=>{calls++;return delayed;});
        const f=form();
        const first=send({form:f});
        const second=await send({form:f});
        assert.equal(second.status,'blocked');
        assert.equal(second.reason,'delete_pending');
        assert.equal(calls,1);
        release(success());
        assert.equal((await first).status,'deleted');
    });
    await check('network failure does not retry native delete',async()=>{
        const f=form();
        const send=client(async()=>{throw new Error('offline');});
        assert.equal((await send({form:f})).status,'blocked');
        assert.equal(f.fields.expected_revision,'4');
    });
    await check('binder refuses current legacy data-confirm form',async()=>{
        assert.throws(()=>win.ReneeDailyRecordRevisionRuminantDelete.createBindings({
            forms:[form({},true)],fetchImpl:async()=>success(),
            confirm:async()=>true,onDeleted(){},onConflict(){},onBlocked(){},
            FormDataImpl:FakeFormData
        }),/not safely cut over/);
    });
    await check('binder refuses forms lacking explicit B12 cutover marker',async()=>{
        assert.throws(()=>win.ReneeDailyRecordRevisionRuminantDelete.createBindings({
            forms:[form({},false,false)],fetchImpl:async()=>success(),
            confirm:async()=>true,onDeleted(){},onConflict(){},onBlocked(){},
            FormDataImpl:FakeFormData
        }),/not safely cut over/);
    });
    await check('binder requires confirm success and conflict callbacks',async()=>{
        assert.throws(()=>win.ReneeDailyRecordRevisionRuminantDelete.createBindings({
            forms:[form()],fetchImpl:async()=>success(),
            confirm:async()=>true,onDeleted(){},onBlocked(){}
        }),/callback/);
    });
    await check('binder intercepts POST and confirms before network request',async()=>{
        const f=form(),events=[],calls=[];
        const binder=win.ReneeDailyRecordRevisionRuminantDelete.createBindings({
            forms:[f],
            fetchImpl:async(url,opts)=>{calls.push([url,opts]);return success();},
            FormDataImpl:FakeFormData,URLSearchParamsImpl:URLSearchParams,
            confirm:async()=>{events.push('confirm');return true;},
            onDeleted:x=>events.push('deleted:'+x.record_id),
            onConflict:()=>events.push('unexpected-conflict'),
            onBlocked:()=>events.push('unexpected-block')
        });
        assert.equal(f.listenerCount('submit'),0);
        binder.bind();
        assert.equal(f.listenerCount('submit'),1);
        assert.equal(f.dispatch('submit').prevented,true);
        await microtasks();
        assert.deepEqual(events,['confirm','deleted:19']);
        assert.equal(calls.length,1);
        assert.equal(f.fields.write_intent,'delete');
        assert.equal(f.dispatch('submit').prevented,true);
        assert.equal(calls.length,1);
    });
    await check('cancelled confirmation keeps form usable and issues no DELETE',async()=>{
        const f=form(),calls=[];
        let next=false;
        const binder=win.ReneeDailyRecordRevisionRuminantDelete.createBindings({
            forms:[f],fetchImpl:async()=>{calls.push(1);return success();},
            FormDataImpl:FakeFormData,
            confirm:async()=>next,onDeleted(){},onConflict(){},onBlocked(){}
        });
        binder.bind();
        assert.equal(f.dispatch('submit').prevented,true);
        await microtasks();
        assert.equal(calls.length,0);
        next=true;
        f.dispatch('submit');
        await microtasks();
        assert.equal(calls.length,1);
    });
    await check('stale DELETE locks form until reload and reports conflict',async()=>{
        const f=form(),report=[],calls=[];
        const binder=win.ReneeDailyRecordRevisionRuminantDelete.createBindings({
            forms:[f],fetchImpl:async()=>{calls.push(1);return conflict();},
            FormDataImpl:FakeFormData,
            confirm:async()=>true,
            onDeleted:()=>report.push('deleted'),
            onConflict:x=>report.push(x.reason),
            onBlocked:x=>report.push(x.reason)
        });
        binder.bind();
        assert.equal(f.dispatch('submit').prevented,true);
        await microtasks();
        assert.equal(calls.length,1);
        assert.deepEqual(report,['record_conflict']);
        assert.equal(f.dispatch('submit').prevented,true);
        assert.equal(calls.length,1);
        assert.deepEqual(report,['record_conflict','reload_required']);
        assert.equal(f.fields.expected_revision,'4');
    });
    await check('duplicate user submits while confirmation pending do not double-confirm',async()=>{
        let release,confirmCalls=0,deleted=0;
        const confirmation=new Promise(resolve=>{release=resolve;});
        const f=form(),binder=win.ReneeDailyRecordRevisionRuminantDelete.createBindings({
            forms:[f],fetchImpl:async()=>{deleted++;return success();},
            FormDataImpl:FakeFormData,
            confirm:()=>{confirmCalls++;return confirmation;},
            onDeleted(){},onConflict(){},onBlocked(){}
        });
        binder.bind();
        f.dispatch('submit');f.dispatch('submit');
        await microtasks();
        assert.equal(confirmCalls,1);
        release(true);
        await microtasks();
        assert.equal(deleted,1);
    });
    await check('binder cannot bind same form twice from separate controllers',async()=>{
        const f=form(),config={
            forms:[f],fetchImpl:async()=>success(),FormDataImpl:FakeFormData,
            confirm:async()=>false,onDeleted(){},onConflict(){},onBlocked(){}
        };
        const binder=win.ReneeDailyRecordRevisionRuminantDelete.createBindings(config);
        binder.bind();
        assert.throws(()=>binder.bind(),/already bound/);
        assert.throws(()=>win.ReneeDailyRecordRevisionRuminantDelete.createBindings(config),/not safely cut over/);
    });
    console.log('B12_RUMINANT_DELETE_BROWSER_PURE=PASS');
    console.log('PASS_COUNT='+count);
})().catch(error=>{
    console.error('B12_RUMINANT_DELETE_BROWSER_PURE=FAIL',error);
    process.exitCode=1;
});
