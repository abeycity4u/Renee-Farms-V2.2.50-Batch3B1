'use strict';
/*
 * B12 source-only thin page owner verification.
 * All browser and lifecycle dependencies are fake. No real HTTP, PHP app
 * bootstrap, database connection, script activation or schema mutations.
 */
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const root=path.join(__dirname,'../../assets/js');
const names=[
    'daily-record-revision-page-adapters.js',
    'daily-record-revision-layer-page.js',
    'daily-record-revision-broiler-page.js',
    'daily-record-revision-ruminant-page.js'
];
const win={};const staged=[];
win.ReneeDailyRecordRevisionPageLifecycle={
    createLifecycle(opts) {
        staged.push(opts);
        let bound=false,valid=false;
        let selected=0;
        const obj={
            bind(){bound=true;},
            select(){
                if(!bound)throw Error('not bound');
                selected++;
                valid=true;
                opts.clearPopulation();
                opts.onPending();
                const mode=opts.form.nextMode||'edit';
                const record=mode==='edit'?{mortality:3,id:'19'}:null;
                const guard=callback=>{
                    if(!valid)return false;
                    callback();
                    return true;
                };
                opts.onReady({mode,record,guard});
                opts.onPopulation({
                    closing_stock:120,
                    movement_totals:{sale:-1}
                },{mode,record,record_date:opts.form.controls.selectedDate.value});
                opts.onPopulationReady({mode});
                return Promise.resolve({status:'ready',mode});
            },
            invalidate(){valid=false;opts.clearPopulation();},
            isReady(){return valid;},
            mode(){return valid?(opts.form.nextMode||'edit'):null;},
            populationSettled(){return Promise.resolve({status:'applied'});}
        };
        return obj;
    }
};
const ctx={window:win};
for(const name of names) {
    vm.runInNewContext(fs.readFileSync(path.join(root,name),'utf8'),ctx,{filename:name});
}
const eventObject=()=>({
    handlers:{},
    addEventListener(name,fn){(this.handlers[name]??=[]).push(fn);},
    dispatch(name){for(const fn of this.handlers[name]||[])fn({});},
    listenerCount(name){return (this.handlers[name]||[]).length;}
});
function makeForm() {
    const controls={
        selectedDate:{value:''}, recordDate:{value:''},
        animalType:{value:''},animalTypeHidden:{value:''},
        openingStock:{value:'',readOnly:false},
        save:{disabled:false}
    };
    for(const field of Object.values(controls)) {
        Object.assign(field,eventObject());
    }
    const form={
        ...eventObject(),controls,nextMode:'edit',resets:0,
        querySelector(selector){
            if(selector==='[name="save_record"]')return controls.save;
            return controls[selector.slice(1)]||null;
        },
        getAttribute(){return null;},
        reset(){
            this.resets++;
            this.dispatch('reset');
            for(const key of ['selectedDate','recordDate','animalType',
                'animalTypeHidden','openingStock']) controls[key].value='';
        }
    };
    return form;
}
function button(date,species='goat') {
    const b={...eventObject(),dataset:{
        recordDate:date,animalType:species,cycleAnimalType:species
    }};
    b.click=()=>b.dispatch('click');
    return b;
}
function make(type='layer', overrides={}) {
    const form=makeForm(),add=button('2026-10-13','goat'),
        edit=button('2026-10-14','cattle');
    const scripts=(overrides.scripts||[]).map(src=>({
        src,getAttribute(name){return name==='src'?src:null;}
    }));
    const markup={b12RevisionPage:type};
    const document={
        querySelectorAll(selector) {
            if(selector==='script[src]')return scripts;
            if(selector==='.add-record-btn')return [add];
            if(selector==='.edit-record-btn')return [edit];
            if(selector==='form[data-confirm][data-b12-revision-delete]')return [];
            return [];
        }
    };
    const events={errors:[],saved:[],shows:0,clears:0,
        renders:[],confirmations:0,ready:[]};
    const opts={
        cycleId:'11',form,modalElement:eventObject(),document,
        pageMarker:{dataset:markup},
        modalTitle:{textContent:''},
        display:{
            clear(){events.clears++;},
            render(...args){events.renders.push(args);}
        },
        fetchImpl:()=>{throw Error('PAGE MUST NOT INITIATE FETCH');},
        legacyHandlersRetired:true,
        deleteOwnership:'replacement',
        canEditOpeningStock:false,
        confirmReplace:async()=>{events.confirmations++;return true;},
        showModal:()=>{events.shows++;},
        notify:d=>events.errors.push(d),
        onSaved:d=>events.saved.push(d),
        onReady:d=>events.ready.push(d),
        ...overrides
    };
    const api={
        layer:'ReneeDailyRecordRevisionLayerPage',
        broiler:'ReneeDailyRecordRevisionBroilerPage',
        ruminant:'ReneeDailyRecordRevisionRuminantPage'
    };
    const adapter=win[api[type]].create(opts);
    return {adapter,opts,form,add,edit,events,markup,document};
}
const tick=()=>new Promise(r=>setImmediate(r));
let count=0;
async function test(label,fn) {
    await fn();count++;console.log('PASS: '+label);
}
(async()=>{
    for(const type of ['layer','broiler','ruminant']) {
        await test(type+' thin module has no automatic binding or GET',async()=>{
            const v=make(type);
            assert.equal(v.events.shows,0);
            assert.equal(v.events.clears,0);
            assert.equal(v.add.listenerCount('click'),0);
            assert.equal(v.edit.listenerCount('click'),0);
            assert.equal(v.form.controls.save.disabled,false);
            v.adapter.bind();
            assert.equal(v.add.listenerCount('click'),1);
            assert.equal(v.edit.listenerCount('click'),1);
            assert.equal(v.form.controls.save.disabled,true);
            v.adapter.bind();
            assert.equal(v.add.listenerCount('click'),1);
        });
        await test(type+' calendar action delegates to exact shared workspace',async()=>{
            const v=make(type);v.adapter.bind();
            v.add.click();await tick();
            assert.equal(v.events.shows,1);
            assert.equal(v.events.confirmations,1);
            assert.equal(v.form.controls.selectedDate.value,'2026-10-13');
            assert.equal(v.form.controls.recordDate.value,'2026-10-13');
            assert.equal(v.form.resets,1);
            assert.equal(v.events.ready.length,1);
            assert.equal(v.events.renders.length,1);
            assert.equal(v.form.controls.save.disabled,false);
            assert.equal(v.adapter.isReady(),true);
            assert.equal(v.opts.modalTitle.textContent,'Edit Record');
            if(type==='ruminant') {
                assert.equal(v.form.controls.animalType.value,'goat');
                assert.equal(v.form.controls.animalTypeHidden.value,'goat');
                assert.equal(v.events.renders[0][1],3);
            }else{
                assert.equal(v.events.renders[0].length,1);
            }
        });
        await test(type+' edit button uses only selected date/species, never row fields',async()=>{
            const v=make(type);v.adapter.bind();
            v.edit.dataset.openingStock='fake-row-value';
            v.edit.dataset.recordId='999';
            v.edit.click();await tick();
            assert.equal(v.form.controls.selectedDate.value,'2026-10-14');
            assert.equal(v.form.controls.openingStock.value,'');
            assert.equal(v.events.shows,1);
            assert.equal(v.events.ready.length,1);
            if(type==='ruminant')assert.equal(v.form.controls.animalType.value,'cattle');
        });
        await test(type+' CREATE opening-stock policy is read-only for delegated user',async()=>{
            const v=make(type);v.form.nextMode='create';v.adapter.bind();
            await v.adapter.open({recordDate:'2026-10-13',animalType:'goat'});
            assert.equal(v.form.controls.openingStock.readOnly,true);
            assert.equal(v.form.controls.save.disabled,false);
            assert.equal(v.opts.modalTitle.textContent,'Add Daily Record');
        });
    }
    await test('farm admin may correct retrieved CREATE opening stock',async()=>{
        const v=make('layer',{canEditOpeningStock:true});
        v.form.nextMode='create';v.adapter.bind();
        await v.adapter.open({recordDate:'2026-10-13'});
        assert.equal(v.form.controls.openingStock.readOnly,false);
    });
    await test('cancelling a selection does not clear a draft or fetch',async()=>{
        const v=make('layer',{confirmReplace:async()=>false});
        v.form.controls.openingStock.value='my draft';
        v.adapter.bind();
        assert.equal((await v.adapter.open({recordDate:'2026-10-13'})).status,'cancelled');
        assert.equal(v.form.resets,0);
        assert.equal(v.form.controls.openingStock.value,'my draft');
        assert.equal(v.events.ready.length,0);
    });
    await test('uncertain selection is refused before modal or request',async()=>{
        const v=make('broiler');v.adapter.bind();
        assert.equal((await v.adapter.open({recordDate:'bad'})).status,'blocked');
        assert.equal(v.form.resets,0);
        assert.equal(v.events.shows,0);
        assert.equal(v.events.errors.at(-1).reason,'invalid_selection');
    });
    await test('Ruminant missing or unsupported species is rejected',async()=>{
        const v=make('ruminant');v.adapter.bind();
        for(const animalType of ['', 'elephant', '0']) {
            assert.equal((await v.adapter.open({
                recordDate:'2026-10-13',animalType
            })).status,'blocked');
        }
        assert.equal(v.form.resets,0);
        assert.equal(v.events.ready.length,0);
    });
    await test('legacy loaded Layer script prevents any new listener binding',async()=>{
        const v=make('layer',{scripts:['/assets/js/layers-daily-record.js?v=7']});
        assert.throws(()=>v.adapter.bind(),/Legacy Daily Record/);
        assert.equal(v.add.listenerCount('click'),0);
        assert.equal(v.form.controls.save.disabled,false);
    });
    await test('missing ownership marker prevents listener binding',async()=>{
        const v=make('broiler');v.markup.b12RevisionPage='';
        assert.throws(()=>v.adapter.bind(),/ownership/);
        assert.equal(v.edit.listenerCount('click'),0);
    });
    await test('missing coordinated DELETE ownership prevents partial takeover',async()=>{
        const v=make('layer',{deleteOwnership:'legacy'});
        assert.throws(()=>v.adapter.bind(),/ownership/);
        assert.equal(v.add.listenerCount('click'),0);
    });
    await test('rejects shared generic submit handler on same form',async()=>{
        const v=make('ruminant');v.form.getAttribute=()=> 'true';
        assert.throws(()=>v.adapter.bind(),/ownership/);
    });
    await test('requires styled UI action callbacks before initialization',async()=>{
        assert.throws(()=>make('layer',{notify:null}),/Missing B12 page action/);
    });
    await test('change event invalidates Save and original revision authority',async()=>{
        const v=make('broiler');v.adapter.bind();
        await v.adapter.open({recordDate:'2026-10-13'});
        assert.equal(v.adapter.isReady(),true);
        v.form.controls.selectedDate.value='2026-10-14';
        v.form.controls.selectedDate.dispatch('change');
        assert.equal(v.form.controls.save.disabled,true);
        // This source-only stub does not simulate the real workspace's
        // date-change token invalidation; its own tests cover that contract.
    });
    await test('shared page owner never activates a live script or endpoint',async()=>{
        for(const type of ['layer','broiler','ruminant']) {
            const v=make(type);
            assert.equal(v.events.shows,0);
            assert.equal(v.form.resets,0);
            assert.equal(v.events.clears,0);
        }
    });
    console.log('B12_THIN_PAGE_ADAPTERS_PURE=PASS');
    console.log('PASS_COUNT='+count);
})().catch(error=>{
    console.error('B12_THIN_PAGE_ADAPTERS_PURE=FAIL',error);
    process.exitCode=1;
});
