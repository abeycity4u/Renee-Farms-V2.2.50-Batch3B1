'use strict';
/*
 * B12 staged/unloaded derived-field UI parity tests.
 * Synthetic DOM controls only: no browser, HTTP, DB, schema, PHP bootstrap.
 */
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const assert=require('node:assert/strict');
const win={};
const source=fs.readFileSync(
    path.join(__dirname,'../../assets/js/daily-record-revision-page-fields.js'),'utf8'
);
vm.runInNewContext(source,{window:win},{filename:'daily-record-revision-page-fields.js'});
function control(value='') {
    const listeners={};
    return {
        value,disabled:false,readOnly:false,listeners,
        addEventListener(name,callback){
            (listeners[name]??=[]).push(callback);
        },
        dispatch(name){
            for(const fn of listeners[name]||[])fn();
        },
        count(name){return (listeners[name]||[]).length;}
    };
}
function setup(type='layer',override={}) {
    const ids=['openingStock','eggProduction','layingRate','cratesCount',
        'feedItemId','feedConsumptionUnit'];
    const elements=Object.fromEntries(ids.map(id=>[id,control()]));
    const label={textContent:'Feed Consumption (kg)'};
    const document={getElementById(id){
        return id==='feedConsumptionLabel'?label:null;
    }};
    elements.feedItemId.options=[
        {dataset:{}},{dataset:{unit:'bags'}},{dataset:{unit:'kg'}}
    ];
    elements.feedItemId.selectedIndex=0;
    const form={querySelector(s){return elements[s.slice(1)]||null;}};
    const fields=win.ReneeDailyRecordRevisionPageFields.createFields({
        type,form,document,...override
    });
    return {fields,elements,label,document,form};
}
let count=0;
async function check(label,fn) {
    await fn();
    count++;
    console.log('PASS: '+label);
}
(async()=>{
    await check('no automatic listeners, updates or writes on module load',()=>{
        const v=setup();
        assert.equal(v.elements.eggProduction.count('input'),0);
        assert.equal(v.elements.cratesCount.value,'');
        assert.equal(v.elements.layingRate.value,'');
    });
    await check('layer crates preserve 30 eggs per crate at two decimal places',()=>{
        const v=setup();v.fields.bind();
        v.elements.eggProduction.value='2400';
        v.elements.openingStock.value='3000';
        v.elements.eggProduction.dispatch('input');
        assert.equal(v.elements.cratesCount.value,'80.00');
        assert.equal(v.elements.layingRate.value,'80.00');
    });
    await check('layer derived formula updates on opening-stock change',()=>{
        const v=setup();v.fields.bind();
        v.elements.eggProduction.value='90';
        v.elements.openingStock.value='100';
        v.elements.eggProduction.dispatch('input');
        assert.equal(v.elements.layingRate.value,'90.00');
        v.elements.openingStock.value='120';
        v.elements.openingStock.dispatch('input');
        assert.equal(v.elements.layingRate.value,'75.00');
        assert.equal(v.elements.cratesCount.value,'3.00');
    });
    await check('layer two-decimal rounding and comma formatting match legacy',()=>{
        const v=setup();v.fields.bind();
        v.elements.eggProduction.value='1,001';
        v.elements.openingStock.value='1,200';
        v.fields.ready({mode:'edit',record:{egg_production:1001}});
        assert.equal(v.elements.cratesCount.value,'33.37');
        assert.equal(v.elements.layingRate.value,'83.42');
    });
    await check('layer zero/empty opening clears a stale laying rate',()=>{
        const v=setup();v.fields.bind();
        v.elements.eggProduction.value='30';
        v.elements.openingStock.value='60';
        v.fields.computeLayer();
        assert.equal(v.elements.layingRate.value,'50.00');
        v.elements.openingStock.value='0';
        v.elements.openingStock.dispatch('input');
        assert.equal(v.elements.layingRate.value,'');
        assert.equal(v.elements.cratesCount.value,'1.00');
    });
    await check('layer READY recalculates fields after server record hydration',()=>{
        const v=setup();
        v.elements.eggProduction.value='75';
        v.elements.openingStock.value='100';
        v.fields.ready({mode:'edit',record:{}});
        assert.equal(v.elements.layingRate.value,'75.00');
        assert.equal(v.elements.cratesCount.value,'2.50');
    });
    await check('layer reset clears derived values without clearing business data',()=>{
        const v=setup();v.fields.bind();
        v.elements.openingStock.value='200';
        v.elements.eggProduction.value='90';
        v.fields.computeLayer();
        v.fields.reset();
        assert.equal(v.elements.cratesCount.value,'');
        assert.equal(v.elements.layingRate.value,'');
        assert.equal(v.elements.openingStock.value,'200');
        assert.equal(v.elements.eggProduction.value,'90');
    });
    await check('single binder cannot register duplicate input listeners',()=>{
        const v=setup();v.fields.bind();v.fields.bind();
        assert.equal(v.elements.openingStock.count('input'),1);
        assert.equal(v.elements.eggProduction.count('input'),1);
    });
    await check('ruminant feed selection uses item option unit and text label',()=>{
        const v=setup('ruminant');v.fields.bind();
        v.elements.feedItemId.selectedIndex=1;
        v.elements.feedItemId.dispatch('change');
        assert.equal(v.elements.feedConsumptionUnit.value,'bags');
        assert.equal(v.label.textContent,'Feed Consumption (bags)');
    });
    await check('ruminant edit preserves record-specific stored feed unit',()=>{
        const v=setup('ruminant');v.fields.bind();
        v.elements.feedItemId.selectedIndex=2;
        v.fields.ready({mode:'edit',record:{feed_consumption_unit:'tonnes'}});
        assert.equal(v.elements.feedConsumptionUnit.value,'tonnes');
        assert.equal(v.label.textContent,'Feed Consumption (tonnes)');
    });
    await check('ruminant edited feed item updates a persisted unit',()=>{
        const v=setup('ruminant');v.fields.bind();
        v.fields.ready({mode:'edit',record:{feed_consumption_unit:'kg'}});
        v.elements.feedItemId.selectedIndex=1;
        v.elements.feedItemId.dispatch('change');
        assert.equal(v.elements.feedConsumptionUnit.value,'bags');
    });
    await check('ruminant blank option defaults to kg with escaped plain text',()=>{
        const v=setup('ruminant');v.fields.bind();
        v.elements.feedItemId.selectedIndex=0;
        v.elements.feedItemId.dispatch('change');
        assert.equal(v.elements.feedConsumptionUnit.value,'kg');
        assert.equal(v.label.textContent,'Feed Consumption (kg)');
    });
    await check('ruminant reset restores kg label with no record mutation',()=>{
        const v=setup('ruminant');v.fields.bind();
        v.elements.feedItemId.selectedIndex=1;
        v.fields.feedUnit();
        v.fields.reset();
        assert.equal(v.elements.feedConsumptionUnit.value,'kg');
        assert.equal(v.label.textContent,'Feed Consumption (kg)');
    });
    await check('ruminant binder only attaches one feed change listener',()=>{
        const v=setup('ruminant');v.fields.bind();v.fields.bind();
        assert.equal(v.elements.feedItemId.count('change'),1);
    });
    await check('broiler field parity is intentionally passive',()=>{
        const v=setup('broiler');v.fields.bind();
        v.elements.openingStock.value='333';
        v.fields.ready({mode:'edit',record:{}});
        v.fields.reset();
        assert.equal(v.elements.openingStock.value,'333');
        assert.equal(v.elements.eggProduction.count('input'),0);
        assert.equal(v.elements.feedItemId.count('change'),0);
    });
    await check('invalid type and missing Layer controls fail before bind',()=>{
        assert.throws(()=>setup('other'),/Incomplete/);
        const v=setup('layer');
        v.form.querySelector=s=>s==='#eggProduction'?null:v.elements[s.slice(1)]||null;
        assert.throws(()=>v.fields.bind(),/Missing B12 Daily Record control/);
        assert.equal(v.elements.openingStock.count('input'),0);
    });
    await check('missing ruminant feed label blocks partial binding',()=>{
        const v=setup('ruminant');
        v.document.getElementById=()=>null;
        assert.throws(()=>v.fields.bind(),/Incomplete B12 Ruminant feed UI/);
        assert.equal(v.elements.feedItemId.count('change'),0);
    });
    await check('invalid READY state cannot apply calculations or feed unit',()=>{
        const v=setup('layer');v.elements.cratesCount.value='old';
        assert.throws(()=>v.fields.ready({mode:'blocked'}),/Invalid B12/);
        assert.equal(v.elements.cratesCount.value,'old');
    });
    await check('UI helper never fetches, posts, or activates live routes',()=>{
        assert.equal(typeof win.ReneeDailyRecordRevisionPageFields.createFields,'function');
        assert.equal(win.ReneeDailyRecordRevisionPageFields.fetch,undefined);
        assert.equal(win.ReneeDailyRecordRevisionPageFields.bind,undefined);
    });
    console.log('B12_PAGE_FIELDS_PARITY_PURE=PASS');
    console.log('PASS_COUNT='+count);
})().catch(err=>{
    console.error('B12_PAGE_FIELDS_PARITY_PURE=FAIL',err);
    process.exitCode=1;
});
