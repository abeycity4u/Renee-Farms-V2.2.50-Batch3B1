'use strict';
/**
 * B12 dormant HTTP reader & token-editor integration tests.
 * Pure JS DOM/fetch simulations only; NO network, PHP server, DB, CI or web.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.join(__dirname, '../..');
const win = {};
const context = {window: win};
for (const file of [
    'assets/js/daily-record-revision-modal.js',
    'assets/js/daily-record-revision-editor.js',
    'assets/js/daily-record-revision-transport.js'
]) {
    vm.runInNewContext(fs.readFileSync(path.join(root, file), 'utf8'), context,
        {filename: file});
}
const factory = win.ReneeDailyRecordRevisionTransport;
const tokens = [
    'write_intent','record_id','expected_revision','original_cycle_id',
    'original_record_date','original_animal_type'
];
let total = 0;
async function check(name,fn) { await fn(); total++; console.log('PASS: '+name); }
function field(value='') { return {value,disabled:false,addEventListener(){}}; }
function response(payload,status=200,contentType='application/json; charset=utf-8') {
    return {ok:status>=200&&status<300,status,
        headers:{get:key=>key==='content-type'?contentType:null},
        json:async()=>payload};
}
function mock(type,fetchImpl) {
    const nodes={
        selectedDate:field('2026-10-13'),recordDate:field(),
        openingStock:field('DRAFT'),mortality:field(),
        animalType:field('goat'),animalTypeHidden:field(),birdsAge:field(),
        feedConsumption:field(),eggProduction:field()
    };
    const hidden=Object.fromEntries(tokens.map(key=>[key,field()]));
    const form={
        querySelector:query=>nodes[query.slice(1)]||null,
        querySelectorAll:query=>{
            const match=/name="([a-z_]+)"/.exec(query);
            return match&&hidden[match[1]]?[hidden[match[1]]]:[];
        },
        addEventListener(){}
    };
    const request=factory.createRequest({fetchImpl});
    const editor=win.ReneeDailyRecordRevisionEditor.createEditor({
        form,type,cycleId:'11',request
    });
    return {nodes,hidden,request,editor};
}
function query(url) {
    const values={};
    for(const part of url.slice(url.indexOf('?')+1).split('&')) {
        const [key,val] = part.split('=');
        values[decodeURIComponent(key)] = decodeURIComponent(val);
    }
    return values;
}
function exact(q) {
    return {
        success:true,mode:'exact',
        data:{
            record:{
                id:'19',cycle_id:q.cycle_id,record_date:q.record_date,
                ...(q.type==='ruminant'?{animal_type:q.animal_type}:{}),
                opening_stock:120,mortality:2
            },
            form_tokens:{
                write_intent:'edit',record_id:'19',expected_revision:'4',
                original_cycle_id:q.cycle_id,
                original_record_date:q.record_date,
                original_animal_type:q.type==='ruminant'?q.animal_type:''
            }
        }
    };
}
const scopeUnique={success:true,mode:'scope',
    data:{status:'unique',record_id:19}};
const scopeAbsent={success:true,mode:'scope',
    data:{status:'absent',record_id:null}};
function happy(calls) {
    return async(url,init)=>{
        const q=query(url);
        calls.push({url,init,q});
        return response(q.mode==='scope'?scopeUnique:exact(q));
    };
}
async function rejected(task,needle) {
    let failed=false;
    try { await task(); } catch(e) {
        failed=true;
        assert.ok(String(e.message).includes(needle),String(e));
    }
    assert.equal(failed,true,'Expected rejection');
}

(async()=>{
    for(const type of ['layer','broiler','ruminant']) {
        await check(type+' authentic two-step scope/exact editor tokens',async()=>{
            const calls=[];
            const ui=mock(type,happy(calls));
            const result=await ui.editor.select();
            assert.equal(result.status,'ready');
            assert.equal(result.mode,'edit');
            assert.equal(ui.editor.isReady(),true);
            assert.equal(ui.hidden.record_id.value,'19');
            assert.equal(ui.hidden.expected_revision.value,'4');
            assert.equal(ui.nodes.openingStock.value,'120');
            assert.equal(calls.length,2);
            assert.equal(calls[0].q.mode,'scope');
            assert.equal(calls[1].q.mode,'exact');
            assert.equal(calls[1].q.record_id,'19');
            assert.equal(calls[0].q.animal_type,type==='ruminant'?'goat':undefined);
            assert.equal(calls[0].q.cycle_id,'11');
            assert.equal(calls[0].init.credentials,'same-origin');
            assert.equal(calls[0].init.redirect,'error');
            assert.equal(calls[0].init.cache,'no-store');
            assert.equal(calls[0].init.method,'GET');
            assert.equal(calls[0].url.startsWith('../api/daily_record_revision_read.php?'),true);
        });
        await check(type+' absent scope makes explicit CREATE without invented ID',async()=>{
            const calls=[];
            const ui=mock(type,async(url,init)=>{
                calls.push(url);
                return response(scopeAbsent);
            });
            const result=await ui.editor.select();
            assert.equal(result.status,'ready');
            assert.equal(result.mode,'create');
            assert.equal(ui.hidden.write_intent.value,'create');
            assert.equal(ui.hidden.record_id.value,'');
            assert.equal(ui.hidden.expected_revision.value,'');
            assert.equal(ui.nodes.openingStock.value,'DRAFT');
            assert.equal(calls.length,1);
        });
        for(const status of [401,403,404,409,503]) {
            await check(type+' '+status+' never promotes failed edit to CREATE',async()=>{
                const ui=mock(type,async()=>response({
                    success:false,code:'record_conflict'
                },status));
                const result=await ui.editor.select();
                assert.equal(result.status,'blocked');
                assert.equal(ui.editor.isReady(),false);
                assert.equal(ui.hidden.write_intent.value,'');
                assert.equal(ui.hidden.expected_revision.value,'');
                assert.equal(ui.nodes.openingStock.value,'DRAFT');
            });
        }
        await check(type+' scope ambiguity fails closed',async()=>{
            const ui=mock(type,async()=>response({
                success:true,mode:'scope',
                data:{status:'ambiguous',record_id:null}
            }));
            assert.equal((await ui.editor.select()).status,'blocked');
            assert.equal(ui.hidden.write_intent.value,'');
        });
        await check(type+' exact read ID mismatch never populates',async()=>{
            const ui=mock(type,async(url)=>{
                const q=query(url);
                const e=exact(q);
                e.data.record.id='20';
                return response(q.mode==='scope'?scopeUnique:e);
            });
            const result=await ui.editor.select();
            assert.equal(result.status,'blocked');
            assert.equal(ui.nodes.openingStock.value,'DRAFT');
            assert.equal(ui.hidden.expected_revision.value,'');
        });
        await check(type+' HTML login response never starts CREATE',async()=>{
            const ui=mock(type,async()=>response({},200,'text/html'));
            assert.equal((await ui.editor.select()).status,'blocked');
            assert.equal(ui.hidden.write_intent.value,'');
        });
        await check(type+' network failure does not alter entered fields',async()=>{
            const ui=mock(type,async()=>{throw new Error('Offline');});
            assert.equal((await ui.editor.select()).status,'blocked');
            assert.equal(ui.nodes.openingStock.value,'DRAFT');
        });
    }
    await check('unknown farm_id and extra query keys are rejected',async()=>{
        assert.throws(()=>factory.requestParameters({
            mode:'scope',type:'layer',cycle_id:'11',record_date:'2026-10-13',
            farm_id:'7'
        }));
    });
    await check('ambiguous all-cycles id zero never aliases legacy null',async()=>{
        assert.throws(()=>factory.requestParameters({
            mode:'scope',type:'layer',cycle_id:'0',record_date:'2026-10-13'
        }));
    });
    await check('legacy NULL cycle encoded as explicit empty string',async()=>{
        const p=factory.requestParameters({
            mode:'scope',type:'layer',cycle_id:'',record_date:'2026-10-13'
        });
        assert.ok(p.includes('cycle_id=&'));
    });
    await check('a scope request cannot carry immutable record ID',async()=>{
        assert.throws(()=>factory.requestParameters({
            mode:'scope',type:'layer',cycle_id:'11',
            record_date:'2026-10-13',record_id:'19'
        }));
    });
    await check('an exact request requires numeric immutable ID',async()=>{
        assert.throws(()=>factory.requestParameters({
            mode:'exact',type:'layer',cycle_id:'11',
            record_date:'2026-10-13',record_id:'0'
        }));
    });
    await check('ruminant query cannot omit species',async()=>{
        assert.throws(()=>factory.requestParameters({
            mode:'scope',type:'ruminant',cycle_id:'11',
            record_date:'2026-10-13'
        }));
    });
    await check('same-date earlier response cannot overwrite fresh selection',async()=>{
        const promises=[];
        const ui=mock('layer',(url)=>{
            const q=query(url);
            if(q.mode==='scope'){
                return new Promise(resolve=>promises.push(resolve));
            }
            return Promise.resolve(response(exact(q)));
        });
        const older=ui.editor.select();
        const current=ui.editor.select();
        assert.equal(promises.length,2);
        promises[1](response(scopeUnique));
        assert.equal((await current).status,'ready');
        promises[0](response(scopeAbsent));
        assert.equal((await older).status,'stale');
        assert.equal(ui.hidden.write_intent.value,'edit');
        assert.equal(ui.hidden.expected_revision.value,'4');
        assert.equal(ui.nodes.openingStock.value,'120');
    });
    console.log('B12_READER_TRANSPORT_PURE=PASS');
    console.log('PASS_COUNT='+total);
})().catch(error=>{
    console.error('B12_READER_TRANSPORT_PURE=FAIL',error);
    process.exitCode=1;
});
