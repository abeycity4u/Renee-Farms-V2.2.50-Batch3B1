'use strict';
/*
 * B12 staged Poultry DELETE transport: fake fetch and server-rendered
 * row tokens only. No real HTTP, DB, browser, deployment, or CI.
 */
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const win={URLSearchParams};
vm.runInNewContext(
    fs.readFileSync(path.join(__dirname,'../../assets/js/daily-record-revision-delete.js'),'utf8'),
    {window:win}, {filename:'daily-record-revision-delete.js'}
);

const id='19';
function tokens(overrides={}) {
    return {
        write_intent:'delete',record_id:id,expected_revision:'4',
        original_cycle_id:'11',original_record_date:'2026-10-13',
        original_animal_type:'',...overrides
    };
}
function button(type,token=tokens()) {
    return {dataset:{
        poultryDailyDeleteType:type,poultryDailyDeleteId:id,
        b12DeleteTokens:JSON.stringify(token)
    }};
}
function fake(status,payload,contentType='application/json; charset=utf-8') {
    return {
        status,ok:status>=200&&status<300,
        headers:{get(name){return name.toLowerCase()==='content-type'?contentType:null;}},
        async json(){return payload;}
    };
}
const passed=()=>fake(200,{success:true,record_id:19});
const sender=fetchImpl=>win.ReneeDailyRecordRevisionDelete.createSender({
    fetchImpl,URLSearchParamsImpl:URLSearchParams
});
const args=(type,token=tokens())=>({
    type,recordId:id,button:button(type,token),
    csrfToken:'synthetic-csrf'
});
let count=0;
async function check(name,fn) {
    await fn();count++;console.log('PASS: '+name);
}
(async()=>{
    for(const type of ['layer','broiler']) {
        await check(type+' uses original rendered token and same-origin fixed API',async()=>{
            const calls=[];
            const send=sender(async(url,opts)=>{calls.push({url,opts});return passed();});
            const input=args(type);
            const result=await send(input);
            assert.equal(result.status,'deleted');
            assert.equal(result.record_id,id);
            assert.equal(calls.length,1);
            assert.equal(calls[0].url,'../api/delete_record.php');
            const req=calls[0].opts;
            assert.equal(req.method,'POST');
            assert.equal(req.credentials,'same-origin');
            assert.equal(req.redirect,'error');
            assert.equal(req.cache,'no-store');
            assert.equal(req.headers.Accept,'application/json');
            const body=new URLSearchParams(req.body);
            for(const [name,value] of Object.entries(tokens())) {
                assert.equal(body.get(name),value);
            }
            assert.equal(body.get('type'),type);
            assert.equal(body.get('id'),id);
            assert.equal(body.get('csrf_token'),'synthetic-csrf');
            assert.equal(body.get('farm_id'),null);
            assert.equal(input.button.dataset.b12DeleteTokens,JSON.stringify(tokens()));
        });
        await check(type+' HTTP 409 is conflict, not success or retry',async()=>{
            let calls=0;
            const send=sender(async()=>{
                calls++;
                return fake(409,{success:false,code:'record_conflict'});
            });
            assert.equal((await send(args(type))).status,'conflict');
            assert.equal(calls,1);
        });
        await check(type+' never POSTs if revision is not rendered',async()=>{
            const input=args(type);
            delete input.button.dataset.b12DeleteTokens;
            let calls=0;
            const send=sender(async()=>{calls++;return passed();});
            assert.equal((await send(input)).status,'blocked');
            assert.equal(calls,0);
        });
    }
    await check('incorrect or missing original row ID cannot authorize deletion',async()=>{
        for(const bad of ['20','0','-2','19.0','9223372036854775808','']) {
            let calls=0;
            const send=sender(async()=>{calls++;return passed();});
            const input=args('layer');
            input.recordId=bad;
            assert.equal((await send(input)).status,'blocked');
            assert.equal(calls,0);
        }
    });
    await check('edited row revision, cycle or species is rejected',async()=>{
        const invalid=[
            {write_intent:'edit'},
            {record_id:'20'},
            {expected_revision:'0'},
            {expected_revision:'1e4'},
            {expected_revision:'9223372036854775807'},
            {original_cycle_id:'0'},
            {original_cycle_id:'-1'},
            {original_record_date:'2026-02-30'},
            {original_animal_type:'goat'}
        ];
        let calls=0;
        const send=sender(async()=>{calls++;return passed();});
        for(const raw of invalid){
            assert.equal((await send(args('broiler',tokens(raw)))).status,'blocked');
        }
        assert.equal(calls,0);
    });
    await check('legacy NULL-cycle source is explicit empty rather than wildcard zero',async()=>{
        const a=args('layer',tokens({original_cycle_id:''}));
        const send=sender(async()=>passed());
        assert.equal((await send(a)).status,'deleted');
        a.button.dataset.b12DeleteTokens=JSON.stringify(tokens({original_cycle_id:'0'}));
        assert.equal((await send(a)).status,'blocked');
    });
    await check('ambiguous unexpected or duplicated token fields fail locally',async()=>{
        const cases=[
            {...tokens(),farm_id:'not-allowed'},
            Object.fromEntries(Object.entries(tokens()).filter(([k])=>k!=='expected_revision')),
            {...tokens(),expected_revision:4},
            null,[],{},'oops'
        ];
        let calls=0;
        const send=sender(async()=>{calls++;return passed();});
        for(const token of cases) {
            assert.equal((await send(args('layer',token))).status,'blocked');
        }
        assert.equal(calls,0);
    });
    await check('type and clicked button type must agree; Ruminant is not this endpoint',async()=>{
        let calls=0;
        const send=sender(async()=>{calls++;return passed();});
        const a=args('layer');
        a.button.dataset.poultryDailyDeleteType='broiler';
        assert.equal((await send(a)).status,'blocked');
        assert.equal((await send(args('ruminant'))).status,'blocked');
        assert.equal(calls,0);
    });
    await check('missing csrf blocks before network',async()=>{
        let calls=0;const send=sender(async()=>{calls++;return passed();});
        const a=args('layer');a.csrfToken='';
        assert.equal((await send(a)).status,'blocked');
        assert.equal(calls,0);
    });
    await check('403, 419, 422, 503, network and HTML login fail closed',async()=>{
        for(const status of [403,419,422,503]){
            const send=sender(async()=>fake(status,{success:false,code:'forbidden'}));
            assert.equal((await send(args('layer'))).status,'blocked');
        }
        const html=sender(async()=>fake(200,{success:true,record_id:19},'text/html'));
        assert.equal((await html(args('layer'))).status,'blocked');
        const network=sender(async()=>{throw Error('unavailable');});
        assert.equal((await network(args('layer'))).status,'blocked');
    });
    await check('200 success must echo same exact deleted ID',async()=>{
        for(const raw of [20,'20',0,null,undefined,9007199254740992]){
            const send=sender(async()=>fake(200,{success:true,record_id:raw}));
            assert.equal((await send(args('broiler'))).status,'blocked');
        }
    });
    await check('duplicate in-flight click sends exactly one DELETE',async()=>{
        let release,calls=0;
        const response=new Promise(resolve=>{release=resolve;});
        const send=sender(async()=>{calls++;return response;});
        const a=args('layer');
        const first=send(a);
        const second=await send(a);
        assert.equal(second.status,'blocked');
        assert.equal(second.reason,'delete_pending');
        assert.equal(calls,1);
        release(passed());
        assert.equal((await first).status,'deleted');
    });
    console.log('B12_DELETE_BROWSER_TRANSPORT_PURE=PASS');
    console.log('PASS_COUNT='+count);
})().catch(err=>{
    console.error('B12_DELETE_BROWSER_TRANSPORT_PURE=FAIL',err);
    process.exitCode=1;
});
