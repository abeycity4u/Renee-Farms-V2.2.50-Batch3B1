'use strict';
/*
 * B12 inert lifecycle pure tests. Synthetic editor ticket and fake GET.
 * No HTTP, DB, app bootstrap, schema, real browser, or deployment.
 * Existing modal/editor/workspace tests independently certify their contracts.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const win={URLSearchParams};
const state={config:null};
win.ReneeDailyRecordRevisionWorkspace={
    createWorkspace(options){
        // Boundary mock models original immutable editor ticket invalidation
        // without reproducing the full editor/request module fixtures.
        if(!/^[1-9][0-9]*$/.test(options.cycleId)) {
            throw new Error('Invalid exact cycle.');
        }
        let generation=0, valid=false, binding=false;
        let snapshot=null;
        state.config=options;
        const date=options.form.querySelector('#selectedDate');
        const animal=options.form.querySelector('#animalType');
        function invalidate(){generation++;valid=false;}
        function boundGuard(ticket,cb){
            if(!valid||ticket!==generation||
                date.value!==snapshot.date ||
                (options.type==='ruminant'&&animal.value!==snapshot.animal)) {
                return false;
            }
            if(typeof cb!=='function')return false;
            cb();return true;
        }
        return {
            bind(){
                if(binding)return;
                binding=true;
                date.addEventListener('change',invalidate);
                if(options.type==='ruminant')animal.addEventListener('change',invalidate);
                options.form.addEventListener('reset',invalidate);
                options.modalElement?.addEventListener('hidden.bs.modal',invalidate);
            },
            select(){
                invalidate();
                options.onPending();
                valid=true;
                const ticket=generation;
                snapshot={date:date.value,animal:animal?.value};
                const mode=options.form.nextMode||'edit';
                const record=mode==='edit'?{id:'19',mortality:2}:null;
                options.onReady({
                    mode,record,ticket,
                    guard:cb=>boundGuard(ticket,cb)
                });
                return Promise.resolve({status:'ready',mode,ticket});
            },
            invalidate,
            isReady:()=>valid&&boundGuard(generation,()=>{}),
            mode:()=>valid?options.form.nextMode||'edit':null
        };
    }
};
vm.runInNewContext(
    fs.readFileSync(path.join(__dirname,
        '../../assets/js/daily-record-revision-page-lifecycle.js'),'utf8'),
    {window:win}, {filename:'daily-record-revision-page-lifecycle.js'}
);
function input(value='') {
    const events={};
    return {value,events,
        addEventListener(name,fn){(events[name]??=[]).push(fn);},
        dispatch(name){for(const fn of events[name]||[])fn({});},
        listeners(name){return (events[name]||[]).length;}
    };
}
function target() {
    const events={};
    return {
        addEventListener(name,fn){(events[name]??=[]).push(fn);},
        dispatch(name){for(const fn of events[name]||[])fn({});},
        listeners(name){return (events[name]||[]).length;}
    };
}
function form(type='layer') {
    const inputs={
        selectedDate:input('2026-10-13'),
        animalType:input('goat'),
        openingStock:input(''),
    };
    return {
        type,inputs,nextMode:'edit',
        ...target(),
        querySelector(s){return inputs[s.slice(1)]||null;}
    };
}
function response(status=200,payload={
    closing_stock:117,
    movement_totals:{sale:-2,slaughter:-1}
},contentType='application/json; charset=utf-8') {
    return {status,ok:status>=200&&status<300,
        headers:{get(k){return k==='content-type'?contentType:null;}},
        async json(){return payload;}
    };
}
function later(){
    let resolve;
    const promise=new Promise(done=>{resolve=done;});
    return {promise,resolve};
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
function make(type='layer',behavior=async()=>response()) {
    const f=form(type),modal=target(),calls=[];
    const events={pending:0,clears:0,ready:[],population:[],
        populationReady:[],blocked:[],saved:[]};
    const config={
        type,cycleId:'11',form:f,modalElement:modal,
        fetchImpl:(url,options)=>{
            calls.push({url,options});
            return behavior(url,options,calls.length);
        },
        URLSearchParamsImpl:URLSearchParams,
        onPending(){events.pending++;},
        onReady(info){events.ready.push(info);},
        onPopulation(payload,scope){events.population.push({payload,scope});},
        onPopulationReady(info){events.populationReady.push(info);},
        clearPopulation(){events.clears++;},
        onBlocked(info){events.blocked.push(info);},
        onSaved(info){events.saved.push(info);}
    };
    const client=win.ReneeDailyRecordRevisionPageLifecycle.createLifecycle(config);
    return {client,events,f,modal,calls,config};
}
let n=0;
async function test(label,fn) {
    await fn();n++;console.log('PASS: '+label);
}
(async()=>{
    for(const type of ['layer','broiler','ruminant']) {
        await test(type+' loads read-only population under exact selected ticket',async()=>{
            const v=make(type);
            assert.equal(v.calls.length,0);
            assert.equal(v.events.clears,0);
            assert.equal(v.f.inputs.selectedDate.listeners('change'),0);
            v.client.bind();
            assert.equal(v.client.isReady(),false);
            const loaded=await v.client.select();
            assert.equal(loaded.mode,'edit');
            const settled=await v.client.populationSettled();
            assert.equal(settled.status,'applied');
            assert.equal(v.calls.length,1);
            const req=v.calls[0],url=req.url.split('?');
            assert.equal(url[0],'../api/get_previous_stock.php');
            const params=new URLSearchParams(url[1]);
            assert.equal(params.get('type'),type);
            assert.equal(params.get('date'),'2026-10-13');
            assert.equal(params.get('cycle_id'),'11');
            assert.equal(params.get('animal_type'),type==='ruminant'?'goat':null);
            assert.equal(req.options.method,'GET');
            assert.equal(req.options.credentials,'same-origin');
            assert.equal(req.options.redirect,'error');
            assert.equal(req.options.cache,'no-store');
            assert.equal(req.options.headers.Accept,'application/json');
            assert.equal(v.events.population.length,1);
            assert.equal(v.events.populationReady.length,1);
            assert.equal(v.events.population[0].scope.mode,'edit');
            assert.equal(v.events.blocked.length,0);
            assert.equal(v.events.pending,1);
        });
    }
    await test('EDIT never overwrites originally loaded opening stock',async()=>{
        const v=make('layer');
        v.f.inputs.openingStock.value='123';
        v.client.bind();
        await v.client.select();
        await v.client.populationSettled();
        assert.equal(v.f.inputs.openingStock.value,'123');
    });
    await test('CREATE fills verified earlier closing stock if untouched',async()=>{
        const v=make('broiler');
        v.f.nextMode='create';
        v.client.bind();
        await v.client.select();
        await v.client.populationSettled();
        assert.equal(v.f.inputs.openingStock.value,'117');
        assert.equal(v.events.population[0].scope.mode,'create');
    });
    await test('CREATE cannot overwrite opening stock typed during slow GET',async()=>{
        const deferred=later(),v=make('layer',()=>deferred.promise);
        v.f.nextMode='create';
        v.client.bind();
        await v.client.select();
        await tick();
        v.f.inputs.openingStock.value='101';
        v.f.inputs.openingStock.dispatch('input');
        deferred.resolve(response());
        const result=await v.client.populationSettled();
        assert.equal(result.status,'applied');
        assert.equal(v.f.inputs.openingStock.value,'101');
        assert.equal(v.events.population.length,1);
    });
    await test('CREATE cannot overwrite changed opening stock even without input event',async()=>{
        const deferred=later(),v=make('broiler',()=>deferred.promise);
        v.f.nextMode='create';v.client.bind();
        await v.client.select();await tick();
        v.f.inputs.openingStock.value='88';
        deferred.resolve(response());
        await v.client.populationSettled();
        assert.equal(v.f.inputs.openingStock.value,'88');
    });
    await test('stale earlier different-date GET never renders panel or opening stock',async()=>{
        const first=later(),v=make('layer',(url,opts,count)=>
            count===1?first.promise:Promise.resolve(response(200,{
                closing_stock:80,movement_totals:{sale:-1}
            }))
        );
        v.f.nextMode='create';
        v.client.bind();
        await v.client.select();await tick();
        v.f.inputs.selectedDate.value='2026-10-14';
        v.f.inputs.selectedDate.dispatch('change');
        await v.client.select();
        await v.client.populationSettled();
        assert.equal(v.f.inputs.openingStock.value,'80');
        first.resolve(response(200,{
            closing_stock:999,movement_totals:{sale:-99}
        }));
        await tick();
        assert.equal(v.f.inputs.openingStock.value,'80');
        assert.equal(v.events.population.length,1);
        assert.equal(v.events.population[0].scope.record_date,'2026-10-14');
    });
    await test('same-date revisits invalidate older request ticket',async()=>{
        const first=later(),v=make('layer',(url,opts,count)=>
            count===1?first.promise:Promise.resolve(response(200,{
                closing_stock:66
            }))
        );
        v.f.nextMode='create';v.client.bind();
        await v.client.select();await tick();
        await v.client.select();
        await v.client.populationSettled();
        first.resolve(response(200,{closing_stock:999}));
        await tick();
        assert.equal(v.f.inputs.openingStock.value,'66');
        assert.equal(v.events.population.length,1);
    });
    await test('Ruminant species change clears panel and blocks stale same-date stock',async()=>{
        const first=later(),v=make('ruminant',(url,opts,count)=>
            count===1?first.promise:Promise.resolve(response()));
        v.client.bind();
        await v.client.select();await tick();
        v.f.inputs.animalType.value='cattle';
        v.f.inputs.animalType.dispatch('change');
        await v.client.select();await v.client.populationSettled();
        first.resolve(response(200,{closing_stock:999,movement_totals:{sale:-9}}));
        await tick();
        assert.equal(v.events.population.length,1);
        assert.equal(v.events.population[0].scope.animal_type,'cattle');
    });
    await test('hidden modal invalidates authority and drops delayed side panel',async()=>{
        const waiting=later(),v=make('ruminant',()=>waiting.promise);
        v.client.bind();await v.client.select();await tick();
        v.modal.dispatch('hidden.bs.modal');
        assert.equal(v.client.isReady(),false);
        waiting.resolve(response());
        await tick();
        assert.equal(v.events.population.length,0);
        assert.equal(v.events.populationReady.length,0);
    });
    await test('manual invalidation clears panel and cancels pending selection ticket',async()=>{
        const waiting=later(),v=make('broiler',()=>waiting.promise);
        v.client.bind();await v.client.select();await tick();
        const prior=v.events.clears;
        v.client.invalidate();
        assert.equal(v.events.clears,prior+1);
        waiting.resolve(response());
        await tick();
        assert.equal(v.events.population.length,0);
        assert.equal(v.client.isReady(),false);
    });
    await test('GET 503 invalidates revision authority and preserves typed draft',async()=>{
        const v=make('layer',async()=>response(503,{error:'unavailable'}));
        v.f.inputs.openingStock.value='my unsubmitted value';
        v.client.bind();
        await v.client.select();
        const result=await v.client.populationSettled();
        assert.equal(result.status,'blocked');
        assert.equal(v.client.isReady(),false);
        assert.equal(v.f.inputs.openingStock.value,'my unsubmitted value');
        assert.equal(v.events.blocked.at(-1).reason,'population_unavailable');
        assert.equal(v.events.population.length,0);
    });
    await test('malformed/HTML/login/null/negative stock or movement is blocked',async()=>{
        const conditions=[
            response(200,{closing_stock:-1}),
            response(200,{closing_stock:'invalid'}),
            response(200,{closing_stock:Infinity}),
            response(200,{closing_stock:0,movement_totals:null}),
            response(200,{success:false}),
            response(200,{error:'raw error'}),
            response(200,{},'text/html'),
            response(302,{closing_stock:17}),
        ];
        for(const http of conditions) {
            const v=make('layer',async()=>http);
            v.client.bind();await v.client.select();
            assert.equal((await v.client.populationSettled()).status,'blocked');
            assert.equal(v.events.population.length,0);
            assert.equal(v.client.isReady(),false);
        }
    });
    await test('network errors revoke tokens without clearing entered business fields',async()=>{
        const v=make('broiler',async()=>{throw Error('network unavailable');});
        v.f.inputs.openingStock.value='42';
        v.client.bind();await v.client.select();
        assert.equal((await v.client.populationSettled()).status,'blocked');
        assert.equal(v.f.inputs.openingStock.value,'42');
        assert.equal(v.events.populationReady.length,0);
    });
    await test('display callback error invalidates selection instead of leaving partially ready',async()=>{
        const v=make('ruminant');
        v.config.onPopulation=()=>{throw Error('bad DOM');};
        v.client.bind();await v.client.select();
        assert.equal((await v.client.populationSettled()).status,'blocked');
        assert.equal(v.client.isReady(),false);
        assert.equal(v.events.populationReady.length,0);
        assert.equal(v.events.blocked.at(-1).reason,'population_unavailable');
    });
    await test('a new selection never lets stale failure revoke fresh tokens',async()=>{
        const first=later(),v=make('layer',(url,opts,count)=>
            count===1?first.promise:Promise.resolve(response()));
        v.client.bind();await v.client.select();await tick();
        await v.client.select();await v.client.populationSettled();
        first.resolve(response(500,{}));
        await tick();
        assert.equal(v.client.isReady(),true);
        assert.equal(v.events.blocked.length,0);
        assert.equal(v.events.population.length,1);
    });
    await test('duplicate bind does not register duplicate population listeners',async()=>{
        const v=make('layer');
        v.client.bind();v.client.bind();
        assert.equal(v.f.inputs.openingStock.listeners('input'),1);
        assert.equal(v.f.inputs.selectedDate.listeners('change'),2);
        assert.equal(v.modal.listeners('hidden.bs.modal'),2);
    });
    await test('rejects missing callbacks before B12 source can be initialized',async()=>{
        const v=make('layer');
        assert.throws(()=>win.ReneeDailyRecordRevisionPageLifecycle.createLifecycle({
            type:'layer',cycleId:'11',form:v.f,fetchImpl:async()=>response()
        }),/callback/);
    });
    await test('all-cycle wildcard and incorrect cycle types are fail-closed',async()=>{
        const v=make('layer');
        for(const value of ['0','',null,0,11,'xyz']) {
            assert.throws(()=>win.ReneeDailyRecordRevisionPageLifecycle.createLifecycle({
                ...v.config,cycleId:value
            }));
        }
    });
    await test('no implicit fetch, automatic form listener or live page activation',async()=>{
        const v=make('layer');
        assert.equal(v.calls.length,0);
        assert.equal(v.f.inputs.selectedDate.listeners('change'),0);
        assert.equal(v.f.listeners('reset'),0);
    });
    console.log('B12_PAGE_LIFECYCLE_PURE=PASS');
    console.log('PASS_COUNT='+n);
})().catch(error=>{
    console.error('B12_PAGE_LIFECYCLE_PURE=FAIL',error);
    process.exitCode=1;
});
