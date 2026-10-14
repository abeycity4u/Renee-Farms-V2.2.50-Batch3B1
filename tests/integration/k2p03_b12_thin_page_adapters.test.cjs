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
    'daily-record-revision-delete.js',
    'daily-record-revision-ruminant-delete.js',
    'daily-record-revision-page-delete.js',
    'daily-record-revision-page-fields.js',
    'daily-record-revision-page-adapters.js',
    'daily-record-revision-layer-page.js',
    'daily-record-revision-broiler-page.js',
    'daily-record-revision-ruminant-page.js'
];
const win={URLSearchParams};const staged=[];
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
                const record=mode==='edit'
                    ?{mortality:3,id:'19',feed_consumption_unit:'kg'}:null;
                const guard=callback=>{
                    if(!valid)return false;
                    callback();
                    return true;
                };
                if (opts.form.fakeAuthoritativeRecord && mode==='edit') {
                    opts.form.controls.openingStock.value='3000';
                    opts.form.controls.eggProduction.value='2400';
                    opts.form.controls.feedItemId.value='5';
                }
                opts.onReady({mode,record,guard});
                if (mode==='create' && opts.form.fakeCreateStock) {
                    opts.form.controls.openingStock.value='120';
                    opts.form.controls.eggProduction.value='90';
                }
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
        eggProduction:{value:''}, cratesCount:{value:''},
        layingRate:{value:''}, feedConsumptionUnit:{value:'kg'},
        feedItemId:{value:'',selectedIndex:0,options:[
            {dataset:{}},{dataset:{unit:'kg'}},{dataset:{unit:'bags'}}
        ]},
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
                'animalTypeHidden','openingStock','eggProduction',
                'cratesCount','layingRate','feedItemId']) controls[key].value='';
        }
    };
    // Native browser input/change events bubble from the control to its
    // parent form. Model that here: the shared B12 draft guard listens on
    // the form, not on every individual field.
    for (const control of Object.values(controls)) {
        const dispatchControl = control.dispatch.bind(control);
        control.dispatch = name => {
            dispatchControl(name);
            if (name === 'input' || name === 'change') {
                form.dispatch(name);
            }
        };
    }
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
        edit=button('2026-10-14','cattle'),header=button('','');
    const scripts=(overrides.scripts||[]).map(src=>({
        src,getAttribute(name){return name==='src'?src:null;}
    }));
    const markup={b12RevisionPage:type};
    const feedLabel={textContent:'Feed Consumption (kg)'};
    const document={
        getElementById(id){return id==='feedConsumptionLabel'?feedLabel:null;},
        querySelectorAll(selector) {
            if(selector==='script[src]')return scripts;
            if(selector==='form[data-confirm]')return overrides.legacyConfirm?[{}]:[];
            if(selector==='[data-poultry-daily-delete-id]')return overrides.missingDeleteTokens
                ?[{dataset:{poultryDailyDeleteId:'19'}}]:[];
            if(selector==='[data-open-record-modal]')return overrides.headerButtons??[header];
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
        currentDate:()=> '2026-10-14',
        selectedCycleAnimalType:'goat',
        csrfToken:'test-csrf',
        confirmDelete:async()=>true,
        onDeleted:d=>events.saved.push(d),
        onDeleteConflict:d=>events.errors.push(d),
        onDeleteBlocked:d=>events.errors.push(d),
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
    return {adapter,opts,form,add,edit,header,events,markup,document,feedLabel};
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
            assert.equal(v.header.listenerCount('click'),0);
            assert.equal(v.form.controls.save.disabled,false);
            v.adapter.bind();
            assert.equal(v.add.listenerCount('click'),1);
            assert.equal(v.edit.listenerCount('click'),1);
            assert.equal(v.header.listenerCount('click'),1);
            assert.equal(v.form.controls.save.disabled,true);
            v.adapter.bind();
            assert.equal(v.add.listenerCount('click'),1);
            assert.equal(v.header.listenerCount('click'),1);
        });
        await test(type+' top Add Today uses same exact-scope authoritative selection',async()=>{
            const v=make(type);v.adapter.bind();
            v.header.click();await tick();
            assert.equal(v.form.controls.selectedDate.value,'2026-10-14');
            assert.equal(v.form.controls.recordDate.value,'2026-10-14');
            assert.equal(v.events.confirmations,1);
            assert.equal(v.events.shows,1);
            assert.equal(v.events.ready.length,1);
            assert.equal(v.form.resets,1);
            assert.equal(v.events.errors.length,0);
            if(type==='ruminant'){
                assert.equal(v.form.controls.animalType.value,'goat');
                assert.equal(v.form.controls.animalTypeHidden.value,'goat');
            }
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
    await test('top header fails closed without explicit current-date provider',async()=>{
        const v=make('layer',{currentDate:null});
        assert.throws(()=>v.adapter.bind(),/top Add Record ownership/);
        assert.equal(v.header.listenerCount('click'),0);
        assert.equal(v.add.listenerCount('click'),0);
        assert.equal(v.form.controls.openingStock.listenerCount('input'),0);
    });
    await test('duplicate or missing top header button rejects partial takeover',async()=>{
        for(const headerButtons of [[],[button(''),button('')]]){
            const v=make('broiler',{headerButtons});
            assert.throws(()=>v.adapter.bind(),/top Add Record ownership/);
            assert.equal(v.add.listenerCount('click'),0);
            assert.equal(v.edit.listenerCount('click'),0);
        }
    });
    await test('calendar action matching both Add and Edit selectors blocks takeover',async()=>{
        for(const type of ['layer','broiler','ruminant']){
            const v=make(type);
            const original=v.document.querySelectorAll.bind(v.document);
            v.document.querySelectorAll=selector=>
                selector==='.edit-record-btn'?[v.add]:original(selector);
            assert.throws(()=>v.adapter.bind(),/Conflicting B12 calendar handler ownership/);
            assert.equal(v.add.listenerCount('click'),0);
            assert.equal(v.edit.listenerCount('click'),0);
            assert.equal(v.header.listenerCount('click'),0);
            assert.equal(v.form.controls.openingStock.listenerCount('input'),0);
        }
    });
    await test('inline legacy calendar onclick blocks all future listeners',async()=>{
        for(const type of ['layer','broiler','ruminant']){
            const v=make(type);
            v.edit.getAttribute=name=>
                name==='onclick'?'openLegacyEdit()':null;
            assert.throws(()=>v.adapter.bind(),/Conflicting B12 calendar handler ownership/);
            assert.equal(v.add.listenerCount('click'),0);
            assert.equal(v.edit.listenerCount('click'),0);
            assert.equal(v.header.listenerCount('click'),0);
            assert.equal(v.form.controls.openingStock.listenerCount('input'),0);
        }
    });
    await test('header reused as calendar action rejects duplicate owner',async()=>{
        for(const type of ['layer','broiler','ruminant']){
            const v=make(type);
            const original=v.document.querySelectorAll.bind(v.document);
            v.document.querySelectorAll=selector=>
                selector==='.add-record-btn'?[v.header]:original(selector);
            assert.throws(()=>v.adapter.bind(),/Conflicting B12 calendar handler ownership/);
            assert.equal(v.header.listenerCount('click'),0);
            assert.equal(v.edit.listenerCount('click'),0);
            assert.equal(v.form.controls.openingStock.listenerCount('input'),0);
        }
    });
    await test('invalid top date never opens modal or clears unsaved draft',async()=>{
        const v=make('broiler',{currentDate:()=>''});
        v.adapter.bind();
        v.form.controls.openingStock.value='unsaved';
        v.header.click();await tick();
        assert.equal(v.events.errors.at(-1).reason,'invalid_selection');
        assert.equal(v.events.shows,0);
        assert.equal(v.form.resets,0);
        assert.equal(v.form.controls.openingStock.value,'unsaved');
    });
    await test('top button rejects impossible calendar date before draft reset',async()=>{
        for(const value of ['2026-02-30','2025-02-29','2026-13-01']){
            const v=make('layer',{currentDate:()=>value});
            v.adapter.bind();
            v.form.controls.openingStock.value='keep draft';
            v.header.click();await tick();
            assert.equal(v.events.errors.at(-1).reason,'invalid_selection');
            assert.equal(v.form.resets,0);
            assert.equal(v.events.shows,0);
            assert.equal(v.form.controls.openingStock.value,'keep draft');
        }
    });
    await test('top button accepts valid leap day with explicit provider',async()=>{
        const v=make('layer',{currentDate:()=> '2028-02-29'});
        v.adapter.bind();v.header.click();await tick();
        assert.equal(v.form.controls.selectedDate.value,'2028-02-29');
        assert.equal(v.events.ready.length,1);
    });
    await test('inline legacy top-button click handler blocks entire takeover',async()=>{
        const old=button('');
        old.getAttribute=name=>name==='onclick'?'legacyOpenRecordModal()':null;
        const v=make('broiler',{headerButtons:[old]});
        assert.throws(()=>v.adapter.bind(),/top Add Record ownership/);
        assert.equal(old.listenerCount('click'),0);
        assert.equal(v.add.listenerCount('click'),0);
    });
    await test('top button current-date provider failure is safely blocked',async()=>{
        const v=make('layer',{currentDate:()=>{throw Error('clock unavailable');}});
        v.adapter.bind();v.header.click();await tick();
        assert.equal(v.events.errors.at(-1).reason,'invalid_selection');
        assert.equal(v.events.shows,0);
    });
    await test('Ruminant header blocks missing or unsupported cycle species',async()=>{
        for(const selectedCycleAnimalType of ['', 'horse']){
            const v=make('ruminant',{selectedCycleAnimalType});
            v.adapter.bind();v.header.click();await tick();
            assert.equal(v.events.errors.at(-1).reason,'invalid_species');
            assert.equal(v.events.shows,0);
            assert.equal(v.form.resets,0);
        }
    });
    await test('cancelled header confirmation preserves previously typed draft',async()=>{
        const v=make('layer',{confirmReplace:async()=>false});
        v.adapter.bind();v.form.controls.openingStock.value='draft';
        v.header.click();await tick();
        assert.equal(v.form.resets,0);
        assert.equal(v.events.shows,0);
        assert.equal(v.form.controls.openingStock.value,'draft');
    });
    await test('Layer authoritative EDIT hydration recalculates crates and rate once',async()=>{
        const v=make('layer');
        v.form.fakeAuthoritativeRecord=true;
        v.adapter.bind();
        await v.adapter.open({recordDate:'2026-10-13'});
        assert.equal(v.form.controls.cratesCount.value,'80.00');
        assert.equal(v.form.controls.layingRate.value,'80.00');
        assert.equal(v.form.controls.openingStock.listenerCount('input'),1);
        assert.equal(v.form.controls.eggProduction.listenerCount('input'),1);
        v.form.controls.eggProduction.value='1500';
        v.form.controls.eggProduction.dispatch('input');
        assert.equal(v.form.controls.cratesCount.value,'50.00');
        assert.equal(v.form.controls.layingRate.value,'50.00');
        assert.equal(v.events.renders.length,1);
    });
    await test('Layer CREATE recalculates after verified opening stock becomes available',async()=>{
        const v=make('layer');
        v.form.nextMode='create';
        v.form.fakeCreateStock=true;
        v.adapter.bind();
        await v.adapter.open({recordDate:'2026-10-13'});
        // The fake lifecycle applies closing stock without an input event.
        // Shared derived fields must recalculate onPopulationReady.
        assert.equal(v.form.controls.openingStock.value,'120');
        assert.equal(v.form.controls.cratesCount.value,'3.00');
        assert.equal(v.form.controls.layingRate.value,'75.00');
        assert.equal(v.form.controls.save.disabled,false);
    });
    await test('Ruminant authoritative EDIT synchronizes hidden feed unit and label',async()=>{
        const v=make('ruminant');v.adapter.bind();
        v.form.controls.feedItemId.selectedIndex=1;
        await v.adapter.open({recordDate:'2026-10-13',animalType:'goat'});
        assert.equal(v.form.controls.feedConsumptionUnit.value,'kg');
        assert.equal(v.feedLabel.textContent,'Feed Consumption (kg)');
        v.form.controls.feedItemId.selectedIndex=2;
        v.form.controls.feedItemId.dispatch('change');
        assert.equal(v.form.controls.feedConsumptionUnit.value,'bags');
        assert.equal(v.feedLabel.textContent,'Feed Consumption (bags)');
        assert.equal(v.form.controls.feedItemId.listenerCount('change'),1);
    });
    await test('Ruminant fresh selection resets previous feed unit and label',async()=>{
        const v=make('ruminant');v.adapter.bind();
        v.form.controls.feedItemId.selectedIndex=2;
        v.form.controls.feedItemId.dispatch('change');
        assert.equal(v.feedLabel.textContent,'Feed Consumption (bags)');
        v.form.controls.feedItemId.selectedIndex=0;
        await v.adapter.open({recordDate:'2026-10-13',animalType:'goat'});
        assert.equal(v.feedLabel.textContent,'Feed Consumption (kg)');
        assert.equal(v.form.controls.feedConsumptionUnit.value,'kg');
    });
    await test('missing Layer derived fields blocks whole page ownership before listeners',async()=>{
        const v=make('layer');
        const original=v.form.querySelector.bind(v.form);
        v.form.querySelector=s=>s==='#eggProduction'?null:original(s);
        assert.throws(()=>v.adapter.bind(),/Missing B12 Daily Record control/);
        assert.equal(v.add.listenerCount('click'),0);
        assert.equal(v.form.controls.openingStock.listenerCount('input'),0);
    });
    await test('missing Ruminant feed label blocks page cutover before listeners',async()=>{
        const v=make('ruminant');
        v.document.getElementById=()=>null;
        assert.throws(()=>v.adapter.bind(),/Incomplete B12 Ruminant feed UI/);
        assert.equal(v.add.listenerCount('click'),0);
        assert.equal(v.form.controls.feedItemId.listenerCount('change'),0);
    });
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
    await test('Ruminant legacy native confirmation refuses partial takeover',async()=>{
        const v=make('ruminant',{legacyConfirm:true});
        assert.throws(()=>v.adapter.bind(),/Legacy Ruminant native DELETE/);
        assert.equal(v.add.listenerCount('click'),0);
    });
    await test('Poultry row missing original DELETE tokens refuses takeover',async()=>{
        const v=make('layer',{missingDeleteTokens:true});
        assert.throws(()=>v.adapter.bind(),/Poultry DELETE row is not revision-owned/);
        assert.equal(v.add.listenerCount('click'),0);
    });
    await test('requires styled UI action callbacks before initialization',async()=>{
        assert.throws(()=>make('layer',{notify:null}),/Missing B12 page action/);
    });
    await test('overlapping confirmation dialogs cannot reset newer date selection',async()=>{
        let releaseA;
        const confirmationA=new Promise(resolve=>{releaseA=resolve;});
        let calls=0;
        const v=make('layer',{
            confirmReplace:()=>++calls===1?confirmationA:Promise.resolve(true)
        });
        v.adapter.bind();
        const earlier=v.adapter.open({recordDate:'2026-10-13'});
        const later=v.adapter.open({recordDate:'2026-10-14'});
        assert.equal((await later).status,'ready');
        releaseA(true);
        assert.equal((await earlier).status,'stale');
        assert.equal(v.form.controls.recordDate.value,'2026-10-14');
        assert.equal(v.form.resets,1);
        assert.equal(v.events.ready.length,1);
    });
    await test('cancelled newer selection leaves previously entered draft intact',async()=>{
        let next=true;
        const v=make('ruminant',{
            confirmReplace:()=>Promise.resolve(next)
        });
        v.adapter.bind();
        await v.adapter.open({recordDate:'2026-10-13',animalType:'goat'});
        v.form.controls.openingStock.value='saved draft';
        const priorResets=v.form.resets;
        next=false;
        const result=await v.adapter.open({
            recordDate:'2026-10-14',animalType:'sheep'
        });
        assert.equal(result.status,'cancelled');
        assert.equal(v.form.resets,priorResets);
        assert.equal(v.form.controls.openingStock.value,'saved draft');
    });
    await test('manual date change revokes an earlier pending selection confirmation',async()=>{
        let release;
        const answer=new Promise(done=>{release=done;});
        const v=make('layer',{confirmReplace:()=>answer});
        v.adapter.bind();
        v.form.controls.openingStock.value='unsaved draft';
        const pending=v.adapter.open({recordDate:'2026-10-13'});
        v.form.controls.selectedDate.value='2026-10-14';
        v.form.controls.selectedDate.dispatch('change');
        release(true);
        assert.equal((await pending).status,'stale');
        assert.equal(v.form.resets,0);
        assert.equal(v.form.controls.openingStock.value,'unsaved draft');
        assert.equal(v.events.shows,0);
    });
    await test('Ruminant species change revokes an earlier pending selection confirmation',async()=>{
        let release;
        const answer=new Promise(done=>{release=done;});
        const v=make('ruminant',{confirmReplace:()=>answer});
        v.adapter.bind();
        const pending=v.adapter.open({
            recordDate:'2026-10-13',animalType:'goat'
        });
        v.form.controls.animalType.value='sheep';
        v.form.controls.animalType.dispatch('change');
        release(true);
        assert.equal((await pending).status,'stale');
        assert.equal(v.form.resets,0);
        assert.equal(v.form.controls.animalTypeHidden.value,'sheep');
    });
    await test('draft edits revoke a prior delete-draft confirmation without clearing user data',async()=>{
        let release;
        const answer=new Promise(done=>{release=done;});
        const v=make('broiler',{confirmReplace:()=>answer});
        v.adapter.bind();
        const pending=v.adapter.open({recordDate:'2026-10-13'});
        v.form.controls.openingStock.value='789';
        v.form.controls.openingStock.dispatch('input');
        release(true);
        assert.equal((await pending).status,'stale');
        assert.equal(v.form.resets,0);
        assert.equal(v.form.controls.openingStock.value,'789');
        assert.equal(v.events.shows,0);
    });
    await test('manual date change mirrors submitted hidden date but blocks stale SAVE',async()=>{
        const v=make('layer');v.adapter.bind();
        await v.adapter.open({recordDate:'2026-10-13'});
        assert.equal(v.form.controls.recordDate.value,'2026-10-13');
        v.form.controls.selectedDate.value='2026-10-15';
        v.form.controls.selectedDate.dispatch('change');
        assert.equal(v.form.controls.recordDate.value,'2026-10-15');
        assert.equal(v.form.controls.save.disabled,true);
        assert.equal(v.adapter.isReady(),false);
    });
    await test('Ruminant species change mirrors hidden animal but blocks stale SAVE',async()=>{
        const v=make('ruminant');v.adapter.bind();
        await v.adapter.open({recordDate:'2026-10-13',animalType:'goat'});
        v.form.controls.animalType.value='sheep';
        v.form.controls.animalType.dispatch('change');
        assert.equal(v.form.controls.animalTypeHidden.value,'sheep');
        assert.equal(v.form.controls.save.disabled,true);
        assert.equal(v.adapter.isReady(),false);
    });
    await test('closing modal disables SAVE and revokes in-flight draft replacement intent',async()=>{
        const v=make('broiler');v.adapter.bind();
        await v.adapter.open({recordDate:'2026-10-13'});
        assert.equal(v.adapter.isReady(),true);
        v.opts.modalElement.dispatch('hidden.bs.modal');
        assert.equal(v.form.controls.save.disabled,true);
        assert.equal(v.adapter.isReady(),false);
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
