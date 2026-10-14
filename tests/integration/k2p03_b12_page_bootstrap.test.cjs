'use strict';
/*
 * B12 inert future browser initializer; fake DOM/provider tests only.
 * No authenticated HTTP, DB queries, browser, activation or migration.
 */
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const mock=Object.create(null),window={};
for(const type of ['Layer','Broiler','Ruminant']){
    window['ReneeDailyRecordRevision'+type+'Page']={
        create(config){
            mock[type]=config;
            return {
                binds:0,invalidations:0,
                bind(){this.binds++;},
                invalidate(){this.invalidations++;}
            };
        }
    };
}
const file=path.join(__dirname,'../../assets/js/daily-record-revision-page-bootstrap.js');
vm.runInNewContext(fs.readFileSync(file,'utf8'),{window},{filename:file});
function element(options={}){
    const listeners={};
    return {
        dataset:options.dataset||{},
        value:options.value||'',
        listeners,
        addEventListener(name,fn){(listeners[name]??=[]).push(fn);},
        count(name){return (listeners[name]||[]).length;},
        fire(name){for(const fn of listeners[name]||[])fn();},
        getAttribute(name){return options.attributes?.[name]??null;}
    };
}
function make(type='layer',overrides={}){
    const cap=type.charAt(0).toUpperCase()+type.slice(1);
    const conf={
        layer:'layerDailyConfig',
        broiler:'broilerDailyConfig',
        ruminant:'ruminantDailyConfig'
    }[type];
    const config=element({dataset:{
        b12RevisionPage:type,b12LegacyRetired:'1',
        b12DeleteOwnership:'replacement',selectedCycleId:'17',
        yearMonth:'2026-10',canEditOpening:'0',
        selectedCycleAnimalType:'goat',
        canDelete:'1',csrfToken:'synthetic-csrf',
        ...overrides.dataset
    }});
    const form=element();
    const modal=element(),title=element();
    const month=element({value:'2026-10-12'});
    const cycle=element({value:'17'});
    const items={
        [conf]:config,recordForm:form,recordModal:modal,
        modalTitle:title,monthSelector:month,activeCycleSelector:cycle
    };
    const scripts=(overrides.scriptSources||[]).map(src=>({
        getAttribute(name){return name==='src'?src:null;}
    }));
    const doc={
        getElementById(name){return items[name]||null;},
        querySelectorAll(query){return query==='script[src]'?scripts:[];}
    };
    const events={asks:[],success:[],error:[],nav:[],reload:0,
        modalShow:0,displayClear:0,displayRender:[],create:0};
    const location={
        assign(to){events.nav.push(to);},
        reload(){events.reload++;}
    };
    const modalInstance={show(){events.modalShow++;}};
    const ui={
        createSoldStockDisplay(){
            events.create++;
            return {clear(){events.displayClear++;},
                render(...a){events.displayRender.push(a);}
            };
        },
        createRuminantMovementDisplay(){
            events.create++;
            return {clear(){events.displayClear++;},
                render(...a){events.displayRender.push(a);}
            };
        }
    };
    const opts={
        document:doc,
        fetchImpl:async()=>{throw Error('Unexpected HTTP request');},
        currentDate:()=> '2026-10-14',
        bootstrap:{Modal:{getOrCreateInstance:()=>modalInstance}},
        AppConfirm:{ask:async(...args)=>{
            events.asks.push(args);return true;
        }},
        AppNotify:{
            success(msg){events.success.push(msg);},
            error(msg){events.error.push(msg);}
        },
        populationUI:ui,
        location,
        ...overrides
    };
    const create=()=>window.ReneeDailyRecordRevisionPageBootstrap.create(type,opts);
    return {create,opts,doc,config,form,modal,month,cycle,events,cap};
}
let n=0;
async function test(name,fn){
    await fn();n++;console.log('PASS: '+name);
}
(async()=>{
    await test('bootstrap module does not bind, call fetch, or render automatically',()=>{
        const v=make();
        assert.equal(v.events.create,0);
        assert.equal(v.month.count('blur'),0);
        assert.equal(v.cycle.count('change'),0);
    });
    for(const type of ['layer','broiler','ruminant']){
        await test(type+' resolves one exact config and creates its thin adapter',()=>{
            const v=make(type),page=v.create(),cfg=mock[v.cap];
            assert.equal(cfg.pageMarker,v.config);
            assert.equal(cfg.form,v.form);
            assert.equal(cfg.cycleId,'17');
            assert.equal(cfg.legacyHandlersRetired,true);
            assert.equal(cfg.deleteOwnership,'replacement');
            assert.equal(cfg.canEditOpeningStock,false);
            assert.equal(cfg.currentDate(),'2026-10-14');
            assert.equal(cfg.selectedCycleAnimalType,type==='ruminant'?'goat':'');
            assert.equal(typeof cfg.confirmReplace,'function');
            assert.equal(typeof cfg.confirmDelete,'function');
            assert.equal(page.adapter.binds,0);
            assert.equal(v.month.count('blur'),0);
            assert.equal(v.cycle.count('change'),0);
            page.bind();
            assert.equal(page.adapter.binds,1);
            assert.equal(v.month.count('blur'),1);
            assert.equal(v.cycle.count('change'),1);
            page.bind();
            assert.equal(page.adapter.binds,1);
            assert.equal(v.month.count('blur'),1);
        });
    }
    await test('UI callbacks use styled confirmation/notification and refresh only on success',async()=>{
        const v=make('layer'),p=v.create(),cfg=mock.Layer;
        assert.equal(await cfg.confirmReplace({}),true);
        assert.equal(await cfg.confirmDelete({}),true);
        assert.equal(v.events.asks.length,2);
        assert.match(v.events.asks[0][0],/Unsaved entries/);
        assert.match(v.events.asks[1][0],/cannot be undone/);
        cfg.notify({reason:'population_unavailable'});
        cfg.onDeleteConflict({reason:'record_conflict'});
        cfg.onDeleteBlocked({reason:'reload_required'});
        assert.equal(v.events.error.length,3);
        assert.equal(v.events.reload,0);
        cfg.onSaved({});
        assert.equal(v.events.success.length,1);
        assert.equal(v.events.reload,1);
        cfg.onDeleted({});
        assert.equal(v.events.success.length,2);
        assert.equal(v.events.reload,2);
        cfg.showModal();
        assert.equal(v.events.modalShow,1);
    });
    await test('Layer and Broiler reuse existing population display; Ruminant uses movement breakdown',()=>{
        const a=make('layer');a.create();mock.Layer.display.render({sale:-1});
        assert.equal(a.events.displayRender.length,1);
        const b=make('ruminant');b.create();mock.Ruminant.display.render({sale:-1},3);
        assert.equal(b.events.displayRender[0][1],3);
    });
    await test('month navigation preserves original positive cycle and fixed route',()=>{
        const v=make('broiler'),p=v.create();p.bind();
        v.month.value='2026-11-26';v.month.fire('blur');
        assert.equal(v.events.nav[0],
            'broiler_daily_record.php?month=2026-11&cycle_id=17');
        assert.equal(p.adapter.invalidations,1);
    });
    await test('cycle navigation allows zero only for navigation, not editing',()=>{
        const v=make('ruminant'),p=v.create();p.bind();
        v.cycle.value='0';v.cycle.fire('change');
        assert.equal(v.events.nav[0],
            'ruminant_daily_record.php?month=2026-10&cycle_id=0');
        assert.equal(mock.Ruminant.cycleId,'17');
        assert.equal(p.adapter.invalidations,1);
    });
    await test('invalid navigation date refuses URL and preserves record scope',()=>{
        const v=make('layer'),p=v.create();p.bind();
        v.month.value='2026-02-30';v.month.fire('blur');
        assert.equal(v.events.nav.length,0);
        assert.equal(p.adapter.invalidations,0);
        assert.equal(v.events.error.length,1);
    });
    await test('invalid cycle navigation refuses URL and preserves record scope',()=>{
        const v=make('broiler'),p=v.create();p.bind();
        v.cycle.value='../evil';v.cycle.fire('change');
        assert.equal(v.events.nav.length,0);
        assert.equal(p.adapter.invalidations,0);
        assert.equal(v.events.error.length,1);
    });
    await test('all-cycles zero may not initialize a revision editor',()=>{
        const v=make('layer',{dataset:{selectedCycleId:'0'}});
        assert.throws(()=>v.create(),/selected production cycle/);
    });
    await test('unsafe, too-big or non-string selected cycles fail before binding',()=>{
        for(const id of ['-1','01','9223372036854775808','none',17]){
            const v=make('layer',{dataset:{selectedCycleId:id}});
            assert.throws(()=>v.create(),/selected production cycle/);
            assert.equal(v.month.count('blur'),0);
        }
    });
    await test('wrong/missing B12 server ownership marker fails closed',()=>{
        for(const dataset of [
            {b12RevisionPage:'broiler'},
            {b12LegacyRetired:'0'},
            {b12DeleteOwnership:'legacy'}
        ]){
            const v=make('layer',{dataset});
            assert.throws(()=>v.create(),/ownership/);
            assert.equal(v.month.count('blur'),0);
        }
    });
    await test('existing legacy page script prevents partial cutover',()=>{
        const v=make('layer',{
            scriptSources:['/assets/js/layers-daily-record.js?v=4']
        });
        assert.throws(()=>v.create(),/Legacy Daily Record/);
        assert.equal(v.events.create,0);
    });
    await test('missing farm CSRF or permission flags rejects Poultry owner',()=>{
        for(const dataset of [
            {csrfToken:''},{canDelete:''},{canEditOpening:'true'}
        ]){
            const v=make('broiler',{dataset});
            assert.throws(()=>v.create(),/(permission evidence|production cycle)/);
        }
    });
    await test('invalid Ruminant selected cycle species rejects browser owner',()=>{
        const v=make('ruminant',{dataset:{selectedCycleAnimalType:'horse'}});
        assert.throws(()=>v.create(),/animal type/);
    });
    await test('missing Bootstrap, confirm, notification or population API blocks page',()=>{
        const v=make('layer');
        for(const overrides of [
            {bootstrap:null},{AppConfirm:null},{AppNotify:null},
            {populationUI:null},{currentDate:null}
        ]){
            assert.throws(()=>window.ReneeDailyRecordRevisionPageBootstrap.create(
                'layer',{...v.opts,...overrides}
            ),/Incomplete B12/);
        }
    });
    await test('missing month/cycle selectors reject navigation takeover',()=>{
        const v=make('layer');
        const old=v.doc.getElementById.bind(v.doc);
        v.doc.getElementById=id=>id==='monthSelector'?null:old(id);
        assert.throws(()=>v.create(),/page markup is incomplete/);
    });
    await test('no second initializer can own the same bound DOM',()=>{
        const v=make('layer'),p=v.create();p.bind();
        assert.throws(()=>v.create(),/already has a browser owner/);
    });
    await test('unknown type fails immediately with no network or render',()=>{
        const v=make('layer');
        assert.throws(()=>
            window.ReneeDailyRecordRevisionPageBootstrap.create('other',v.opts),
            /Incomplete B12/);
        assert.equal(v.events.create,0);
    });
    console.log('B12_PAGE_BOOTSTRAP_PURE=PASS');
    console.log('PASS_COUNT='+n);
})().catch(error=>{
    console.error('B12_PAGE_BOOTSTRAP_PURE=FAIL',error);
    process.exitCode=1;
});
