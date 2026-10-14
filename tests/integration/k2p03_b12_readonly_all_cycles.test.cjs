'use strict';
/*
 * B12 WS09 source-only integrated All Cycles navigation.
 * Fake DOM only. No HTTP, database, app bootstrap, migration or production.
 */
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const win={};
const source=fs.readFileSync(path.join(
    __dirname,'../../assets/js/daily-record-revision-readonly-nav.js'
),'utf8');
vm.runInNewContext(source,{window:win},{filename:'daily-record-revision-readonly-nav.js'});
let passed=0;
function check(name,fn){fn();passed++;console.log('PASS: '+name);}
function control(value='') {
    const events={};
    return {
        value,events,
        addEventListener(type,fn){(events[type]??=[]).push(fn);},
        fire(type){for(const fn of events[type]??[])fn();},
        count(type){return (events[type]||[]).length;}
    };
}
function setup(type='layer',overrides={}) {
    const ids={
        layer:'layerDailyConfig',
        broiler:'broilerDailyConfig',
        ruminant:'ruminantDailyConfig'
    };
    const config={dataset:{
        selectedCycleId:'0',yearMonth:'2026-10',...overrides.dataset
    }};
    const month=control('2026-10-14'),cycle=control('0'),urls=[];
    const items={
        [ids[type]]:config,monthSelector:month,activeCycleSelector:cycle
    };
    const scripts=(overrides.scriptSources||[]).map(src=>({
        getAttribute(key){return key==='src'?src:null;}
    }));
    const document={
        getElementById(name){return items[name]||null;},
        querySelectorAll(selector){return selector==='script[src]'?scripts:[];}
    };
    const location={assign(url){urls.push(url);}};
    const create=()=>win.ReneeDailyRecordRevisionReadOnlyNav.create(type,{
        document,location,...overrides.options
    });
    return {create,month,cycle,urls,document,config};
}
check('module exposes explicit create and no automatic listener attachment',()=>{
    const v=setup();
    assert.equal(typeof win.ReneeDailyRecordRevisionReadOnlyNav.create,'function');
    assert.equal(v.month.count('blur'),0);
    assert.equal(v.cycle.count('change'),0);
});
for(const [type,page] of [
    ['layer','layers_daily_record.php'],
    ['broiler','broiler_daily_record.php'],
    ['ruminant','ruminant_daily_record.php']
]){
    check(type+' binds only two read-only navigation events',()=>{
        const v=setup(type),nav=v.create();
        assert.equal(v.month.count('blur'),0);
        nav.bind();
        assert.equal(v.month.count('blur'),1);
        assert.equal(v.cycle.count('change'),1);
        assert.equal(v.urls.length,0);
    });
    check(type+' navigates from valid day to month with cycle_id 0',()=>{
        const v=setup(type),nav=v.create();nav.bind();
        v.month.value='2026-11-27';v.month.fire('blur');
        assert.equal(v.urls[0],page+'?month=2026-11&cycle_id=0');
    });
    check(type+' switches only to selected exact cycle',()=>{
        const v=setup(type),nav=v.create();nav.bind();
        v.cycle.value='17';v.cycle.fire('change');
        assert.equal(v.urls[0],page+'?month=2026-10&cycle_id=17');
    });
    check(type+' refuses invalid dates and malicious cycle IDs',()=>{
        const v=setup(type),nav=v.create();nav.bind();
        for(const day of ['2026-02-30','0000-10-01','not-a-date']){
            v.month.value=day;v.month.fire('blur');
        }
        for(const id of ['../evil','-1','01','9223372036854775808']){
            v.cycle.value=id;v.cycle.fire('change');
        }
        assert.deepEqual(v.urls,[]);
    });
}
check('read-only navigation never reattaches listeners or claims another owner',()=>{
    const v=setup(),nav=v.create();nav.bind();
    assert.throws(()=>nav.bind(),/Duplicate All Cycles/);
    assert.throws(()=>v.create(),/already bound/);
    assert.equal(v.month.count('blur'),1);
});
check('exact-cycle editor context cannot be passed as All Cycles navigation',()=>{
    for(const selectedCycleId of ['17','',null,17]){
        const v=setup('layer',{dataset:{selectedCycleId}});
        assert.throws(()=>v.create(),/ownership/);
        assert.equal(v.month.count('blur'),0);
    }
});
check('revision ownership markers fail closed in read-only navigation',()=>{
    for(const marker of [
        {b12RevisionPage:'layer'},
        {b12LegacyRetired:'1'},
        {b12DeleteOwnership:'replacement'}
    ]){
        const v=setup('layer',{dataset:marker});
        assert.throws(()=>v.create(),/ownership/);
    }
});
check('legacy page controller cannot coexist with read-only navigation',()=>{
    const v=setup('broiler',{
        scriptSources:['/assets/js/broiler-daily-record.js?v=2']
    });
    assert.throws(()=>v.create(),/Legacy Daily Record/);
    assert.equal(v.cycle.count('change'),0);
});
check('unknown species, malformed date scope and missing controls fail closed',()=>{
    const v=setup('layer');
    assert.throws(()=>win.ReneeDailyRecordRevisionReadOnlyNav.create('other',{
        document:v.document,location:{assign(){}}
    }),/complete page/);
    const wrong=setup('ruminant',{dataset:{yearMonth:'2026-15'}});
    assert.throws(()=>wrong.create(),/ownership/);
    const missing=setup('layer');
    missing.document.getElementById=id=>id==='monthSelector'?null:{dataset:{}};
    assert.throws(()=>missing.create(),/ownership/);
});
check('read-only module has no GET POST form or DELETE actions',()=>{
    for(const prohibited of ['fetch(','.submit(','.delete(', 'XMLHttpRequest',
        'data-b12-delete-tokens','ReneeDailyRecordRevisionSubmit']){
        assert.equal(source.includes(prohibited),false,prohibited);
    }
});
console.log('B12_WS09_ALL_CYCLES_NAV=PASS');
console.log('PASS_COUNT='+passed);
