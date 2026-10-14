'use strict';
/**
 * WS10 fully isolated *emitted-script* browser VM contract.
 *
 * STDIN is actual HTML emitted by k2p03_b12_ws10_gate_on_fixture_render.php
 * with --json, using a temporary gate-ON shim. The committed PHP gate stays
 * literal false. We load the actual JS modules in the emitted order using a
 * fake browser DOM, never a real browser, HTTP request, app or DB.
 */
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');

let count=0;
function check(label,fn){
    fn();
    count++;
    console.log('PASS: '+label);
}
function element(value=''){
    const events=Object.create(null);
    return {
        value,events,dataset:{},
        addEventListener(name,fn){(events[name]??=[]).push(fn);},
        fire(name){for(const fn of events[name]??[])fn();},
        count(name){return (events[name]||[]).length;},
        getAttribute(){return null;}
    };
}
function scripts(html){
    const urls=[...html.matchAll(/<script src="([^"]+)"><\/script>/g)]
        .map(x=>x[1]);
    const inline=[...html.matchAll(/<script>([\s\S]*?)<\/script>/g)]
        .map(x=>x[1]);
    return {urls,inline};
}
function contextFor(type,variant,html,markers){
    const configName={
        layer:'layerDailyConfig',
        broiler:'broilerDailyConfig',
        ruminant:'ruminantDailyConfig'
    }[type];
    const config=element();
    config.dataset={
        selectedCycleId:variant==='exact'?'17':'0',
        yearMonth:'2026-10',
        canEditOpening:'0',
        selectedCycleAnimalType:'goat',
        canDelete:'1',
        csrfToken:'synthetic-only'
    };
    for(const m of markers.matchAll(/data-(b12-[a-z-]+)="([^"]*)"/g)){
        const key=m[1].replace(/-([a-z])/g,(_,c)=>c.toUpperCase());
        config.dataset[key]=m[2];
    }
    const month=element('2026-10-12'),cycle=element(variant==='exact'?'17':'0');
    const form=element(),modal=element(),title=element();
    const objs={
        [configName]:config,monthSelector:month,activeCycleSelector:cycle,
        recordForm:form,recordModal:modal,modalTitle:title
    };
    const {urls,inline}=scripts(html);
    const scriptElements=urls.map(url=>({
        getAttribute(name){return name==='src'?url:null;}
    }));
    const doc={
        getElementById(id){return objs[id]||null;},
        querySelectorAll(selector){
            return selector==='script[src]'?scriptElements:[];
        }
    };
    const audit={httpCalls:0,bindCalls:0,captured:null,navigations:[],modalShows:0};
    const location={
        assign(url){audit.navigations.push(url);},
        reload(){audit.reload=(audit.reload||0)+1;}
    };
    const window={
        appToday:()=> '2026-10-14',
        fetch(){audit.httpCalls++;throw new Error('No HTTP in WS10');},
        FormData:class {},
        URLSearchParams,
        bootstrap:{Modal:{getOrCreateInstance:()=>({
            show(){audit.modalShows++;}
        })}},
        AppNotify:{success(){},error(){}},
        AppConfirm:{ask:async()=>true},
        DailyPopulationMovementUI:{
            createSoldStockDisplay:()=>({clear(){},render(){}}),
            createRuminantMovementDisplay:()=>({clear(){},render(){}})
        },
        location
    };
    const sandbox=vm.createContext({
        window,document:doc,console,URLSearchParams
    });
    return {window,doc,config,month,cycle,form,modal,audit,
        sandbox,urls,inline};
}
function loadEmitted(v) {
    for(const url of v.urls){
        const parsed=new URL(url);
        assert.equal(parsed.hostname,'b12-ws10.invalid');
        assert.equal(parsed.search,'?v=ws10');
        const name=path.posix.basename(parsed.pathname);
        assert.match(name,/^daily-record-revision-[a-z-]+\.js$/);
        const file=path.join(__dirname,'../../assets/js',name);
        vm.runInContext(fs.readFileSync(file,'utf8'),v.sandbox,{
            filename:name
        });
    }
}
function evaluateInline(v){
    assert.equal(v.inline.length,1);
    vm.runInContext(v.inline[0],v.sandbox,{filename:'ws10-emitted-inline.js'});
}
async function main(){
    const input=await new Promise((resolve,reject)=>{
        const pieces=[];
        process.stdin.on('data',x=>pieces.push(x));
        process.stdin.on('error',reject);
        process.stdin.on('end',()=>resolve(Buffer.concat(pieces).toString('utf8')));
    });
    const samples=JSON.parse(input);
    check('fixture has only the three known Daily Record page types',()=>{
        assert.deepEqual(Object.keys(samples).sort(),['broiler','layer','ruminant']);
    });
    for(const type of ['layer','broiler','ruminant']){
        const exact=samples[type].exact;
        const all=samples[type].all;
        check(type+' exact-cycle has original rendered ownership markers',()=>{
            assert.match(exact.markers,new RegExp('data-b12-revision-page="'+type+'"'));
            assert.match(exact.markers,/data-b12-legacy-retired="1"/);
            assert.match(exact.markers,/data-b12-delete-ownership="replacement"/);
            assert.equal(all.markers,'');
        });
        const active=contextFor(type,'exact',exact.html,exact.markers);
        check(type+' full manifest modules load without automatic binds or HTTP',()=>{
            loadEmitted(active);
            assert.equal(active.audit.httpCalls,0);
            assert.equal(active.month.count('blur'),0);
            assert.equal(active.cycle.count('change'),0);
            assert.equal(typeof active.window.ReneeDailyRecordRevisionPageBootstrap.create,'function');
            assert.equal(typeof active.window.ReneeDailyRecordRevisionPageAdapters.createAdapter,'function');
            const pageExports={
                layer:'ReneeDailyRecordRevisionLayerPage',
                broiler:'ReneeDailyRecordRevisionBroilerPage',
                ruminant:'ReneeDailyRecordRevisionRuminantPage'
            };
            assert.equal(typeof active.window[pageExports[type]].create,'function');
        });
        check(type+' emitted exact-cycle initializer transfers one-time page ownership',()=>{
            const key={
                layer:'ReneeDailyRecordRevisionLayerPage',
                broiler:'ReneeDailyRecordRevisionBroilerPage',
                ruminant:'ReneeDailyRecordRevisionRuminantPage'
            }[type];
            active.window[key]={create(options){
                active.audit.captured=options;
                return {
                    bind(){active.audit.bindCalls++;},
                    invalidate(){active.audit.invalidations=
                        (active.audit.invalidations||0)+1;}
                };
            }};
            evaluateInline(active);
            assert.equal(active.audit.bindCalls,1);
            assert.equal(active.audit.httpCalls,0);
            assert.equal(active.audit.captured.cycleId,'17');
            assert.equal(active.audit.captured.legacyHandlersRetired,true);
            assert.equal(active.audit.captured.deleteOwnership,'replacement');
            assert.equal(active.month.count('blur'),1);
            assert.equal(active.cycle.count('change'),1);
        });
        check(type+' exact-cycle navigation uses selected scope only',()=>{
            active.month.value='2026-11-26';
            active.month.fire('blur');
            assert.equal(active.audit.navigations[0],
                ({
                    layer:'layers_daily_record.php',
                    broiler:'broiler_daily_record.php',
                    ruminant:'ruminant_daily_record.php'
                })[type]+'?month=2026-11&cycle_id=17');
            assert.equal(active.audit.invalidations,1);
        });
        const view=contextFor(type,'all',all.html,all.markers);
        check(type+' All Cycles loads only the read-only navigation script',()=>{
            assert.equal(view.urls.length,1);
            assert.match(view.urls[0],/daily-record-revision-readonly-nav\.js/);
            loadEmitted(view);
            assert.equal(typeof view.window.ReneeDailyRecordRevisionReadOnlyNav.create,'function');
            assert.equal(view.window.ReneeDailyRecordRevisionPageBootstrap,undefined);
            assert.equal(view.audit.httpCalls,0);
        });
        check(type+' emitted All Cycles initializer binds only view navigation',()=>{
            evaluateInline(view);
            assert.equal(view.month.count('blur'),1);
            assert.equal(view.cycle.count('change'),1);
            view.cycle.value='17';
            view.cycle.fire('change');
            assert.equal(view.audit.navigations[0],
                ({
                    layer:'layers_daily_record.php',
                    broiler:'broiler_daily_record.php',
                    ruminant:'ruminant_daily_record.php'
                })[type]+'?month=2026-10&cycle_id=17');
            assert.equal(view.audit.httpCalls,0);
        });
    }
    console.log('B12_WS10_GATE_ON_BROWSER_VM=PASS');
    console.log('PASS_COUNT='+count);
    console.log('HTTP_REQUESTS=0');
    console.log('AUTHENTICATED_BROWSER=NOT_RUN');
}
main().catch(error=>{
    console.error('B12_WS10_GATE_ON_BROWSER_VM=FAIL '+error.stack);
    process.exitCode=1;
});
