'use strict';
/*
 * WS08 SOURCE-ONLY generic main.js vs B12 Layer calculation ownership.
 * Pure fake DOM / Node VM, no HTTP, browser, database or application startup.
 * The existing B12 Layer two-decimal calculator is independently tested in
 * k2p03_b12_page_fields_parity.test.cjs; do not duplicate its business rule.
 */
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const assert=require('node:assert/strict');

const source=fs.readFileSync(
    path.join(__dirname,'../../assets/js/main.js'),'utf8'
);
function form(id){
    const values={
        'opening_stock':input('3'),
        'egg_production':input('1'),
        'laying_rate':input('')
    };
    const parent={
        id,
        querySelector(selector){
            for(const [key,val] of Object.entries(values)){
                if(selector.includes('name="'+key+'"')) return val;
            }
            return null;
        }
    };
    Object.values(values).forEach(value=>{
        value.closest=selector=>selector==='form'?parent:null;
    });
    return {values,parent};
}
function input(value){
    const events={};
    return {
        value,events,closest:()=>null,
        addEventListener(type,callback){
            (events[type]??=[]).push(callback);
        },
        fire(type){for(const callback of events[type]??[])callback.call(this);},
        count(type){return (events[type]??[]).length;}
    };
}
function setup(marker){
    const current=form('recordForm');
    const unrelated=form('otherForm');
    const controls=[
        current.values.opening_stock,current.values.egg_production,
        unrelated.values.opening_stock,unrelated.values.egg_production
    ];
    const listeners=[];
    const document={
        addEventListener(name,fn){listeners.push([name,fn]);},
        querySelector(selector){
            if(selector.includes('layerDailyConfig')) {
                return marker?{dataset:{
                    b12RevisionPage:'layer',b12LegacyRetired:'1'
                }}:null;
            }
            return null;
        },
        querySelectorAll(selector){
            return selector==='input[name="opening_stock"], input[name="egg_production"]'
                ? controls:[];
        }
    };
    const window={addEventListener(name,fn){listeners.push([name,fn]);}};
    const context=vm.createContext({document,window,console});
    vm.runInContext(source,context,{filename:'main.js'});
    return {current,unrelated,context,listeners};
}
let count=0;
function check(name,fn){fn();count++;console.log('PASS: '+name);}
try {
    check('main module load does not bind calculator before DOM initialization',()=>{
        const v=setup(false);
        assert.equal(v.current.values.opening_stock.count('input'),0);
        assert.equal(v.listeners.filter(x=>x[0]==='DOMContentLoaded').length,1);
    });
    check('hard-OFF legacy Layer continues to receive generic calculator',()=>{
        const v=setup(false);
        v.context.initAutoCalculations();
        assert.equal(v.current.values.opening_stock.count('input'),1);
        assert.equal(v.current.values.egg_production.count('input'),1);
        v.current.values.egg_production.fire('input');
        assert.equal(v.current.values.laying_rate.value,'33.3');
    });
    check('legacy calculator remains on unrelated forms',()=>{
        const v=setup(false);
        v.context.initAutoCalculations();
        assert.equal(v.unrelated.values.opening_stock.count('input'),1);
        assert.equal(v.unrelated.values.egg_production.count('input'),1);
    });
    check('future explicit B12 Layer owner gets no generic one-decimal listener',()=>{
        const v=setup(true);
        v.context.initAutoCalculations();
        assert.equal(v.current.values.opening_stock.count('input'),0);
        assert.equal(v.current.values.egg_production.count('input'),0);
        v.current.values.egg_production.fire('input');
        assert.equal(v.current.values.laying_rate.value,'');
    });
    check('other forms continue legacy calculation even with B12 Layer active',()=>{
        const v=setup(true);
        v.context.initAutoCalculations();
        assert.equal(v.unrelated.values.opening_stock.count('input'),1);
        assert.equal(v.unrelated.values.egg_production.count('input'),1);
        v.unrelated.values.egg_production.fire('input');
        assert.equal(v.unrelated.values.laying_rate.value,'33.3');
    });
    check('generic one-decimal calculator source still contains original rule',()=>{
        assert.match(source,/layingRate\.toFixed\(1\)/);
    });
    console.log('B12_WS08_GLOBAL_CALCULATOR_OWNER=PASS');
    console.log('PASS_COUNT='+count);
} catch(error){
    console.error('B12_WS08_GLOBAL_CALCULATOR_OWNER=FAIL '+error.stack);
    process.exitCode=1;
}
