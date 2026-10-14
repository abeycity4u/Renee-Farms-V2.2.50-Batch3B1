'use strict';
/* B12 source-only exclusive DELETE ownership: real transport and fake DOM.
 * No HTTP, browser navigation, MariaDB, migration or app bootstrap. */
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const win={URLSearchParams};
const base=path.join(__dirname,'../../assets/js');
for(const filename of [
    'daily-record-revision-delete.js',
    'daily-record-revision-ruminant-delete.js',
    'daily-record-revision-page-delete.js'
]){
    vm.runInNewContext(fs.readFileSync(path.join(base,filename),'utf8'),
        {window:win},{filename});
}
function eventNode(dataset={}){
    const handlers={};
    return {
        dataset,handlers,
        addEventListener(name,cb,capture){
            (handlers[name]??=[]).push({cb,capture});
        },
        getAttribute(){return null;},
        hasAttribute(){return false;},
        dispatch(name){
            const event={prevented:false,stopped:false,
                preventDefault(){this.prevented=true;},
                stopImmediatePropagation(){this.stopped=true;}};
            for(const x of handlers[name]||[])x.cb(event);
            return event;
        },
        count(name){return (handlers[name]||[]).length;}
    };
}
const token=(id='19')=>({
    write_intent:'delete',record_id:id,expected_revision:'4',
    original_cycle_id:'11',original_record_date:'2026-10-13',
    original_animal_type:''
});
function button(type='layer',id='19'){
    return eventNode({
        poultryDailyDeleteType:type,
        poultryDailyDeleteId:id,
        b12DeleteTokens:JSON.stringify(token(id))
    });
}
function form(){
    const values={
        csrf_token:'synthetic-csrf',record_id:'19',
        write_intent:'delete',expected_revision:'4',
        original_cycle_id:'11',original_record_date:'2026-10-13',
        original_animal_type:'goat'
    };
    const out=eventNode({b12RevisionDelete:'ruminant'});
    out.fields=values;
    out.hasAttribute=name=>name==='data-confirm'&&out.legacyConfirm===true;
    return out;
}
class FakeFormData{
    constructor(f){
        this.map=new Map(Object.entries(f.fields).map(([name,value])=>
            [name,Array.isArray(value)?value:[value]]));
    }
    getAll(name){return this.map.get(name)||[];}
    set(name,value){this.map.set(name,[value]);}
    *[Symbol.iterator](){
        for(const [name,values]of this.map)
            for(const value of values)yield [name,value];
    }
}
function json(status,payload){
    return {status,ok:status>=200&&status<300,
        headers:{get(k){return k==='content-type'?'application/json':null;}},
        async json(){return payload;}};
}
const ok=()=>json(200,{success:true,code:'deleted',record_id:19});
const conflict=()=>json(409,{success:false,code:'record_conflict'});
const tick=()=>new Promise(resolve=>setImmediate(resolve));
function setup(type='layer',config={}){
    const buttons=config.buttons??(type==='ruminant'?[]:[button(type)]);
    const forms=config.forms??(type==='ruminant'?[form()]:[]);
    const errors=[],deleted=[],conflicts=[],calls=[];
    let confirmed=0;
    const doc={
        querySelectorAll(sel){
            if(sel==='[data-poultry-daily-delete-id]')return buttons;
            if(sel==='form[data-confirm]')return config.legacyConfirm?[forms[0]||{}]:[];
            if(sel==='form[data-b12-revision-delete="ruminant"]')return forms;
            if(sel==='form[data-b12-revision-delete]')return forms;
            return [];
        }
    };
    const fetchImpl=async(url,opts)=>{
        calls.push({url,opts});
        return typeof config.response==='function'
            ? config.response(url,opts):ok();
    };
    const instance=win.ReneeDailyRecordRevisionPageDelete.createBindings({
        type,document:doc,fetchImpl,
        csrfToken:config.csrfToken??'synthetic-csrf',
        FormDataImpl:FakeFormData,URLSearchParamsImpl:URLSearchParams,
        confirm:async node=>{
            confirmed++;
            return config.confirm?config.confirm(node):true;
        },
        onDeleted:x=>deleted.push(x),
        onConflict:x=>conflicts.push(x),
        onBlocked:x=>errors.push(x)
    });
    return {instance,buttons,forms,calls,errors,deleted,conflicts,
        confirmed:()=>confirmed};
}
let n=0;
async function check(name,fn){
    await fn();n++;console.log('PASS: '+name);
}
(async()=>{
    for(const type of ['layer','broiler']){
        await check(type+' original-row DELETE issues exactly one guarded AJAX POST',async()=>{
            const v=setup(type);
            assert.equal(v.calls.length,0);
            assert.equal(v.buttons[0].count('click'),0);
            assert.equal(v.instance.preflight(),true);
            v.instance.bind();
            const e=v.buttons[0].dispatch('click');
            assert.equal(e.prevented,true);
            assert.equal(e.stopped,true);
            await tick();
            assert.equal(v.confirmed(),1);
            assert.equal(v.calls.length,1);
            assert.equal(v.calls[0].url,'../api/delete_record.php');
            const posted=new URLSearchParams(v.calls[0].opts.body);
            assert.equal(posted.get('record_id'),'19');
            assert.equal(posted.get('expected_revision'),'4');
            assert.equal(posted.get('original_record_date'),'2026-10-13');
            assert.equal(posted.get('csrf_token'),'synthetic-csrf');
            assert.equal(posted.get('type'),type);
            assert.equal(v.deleted.length,1);
            assert.equal(v.conflicts.length,0);
        });
    }
    await check('cancelled DELETE confirmation does not issue request or revoke',async()=>{
        let consent=false;
        const v=setup('layer',{confirm:()=>consent});
        v.instance.bind();v.buttons[0].dispatch('click');
        await tick();
        assert.equal(v.calls.length,0);
        assert.equal(v.errors.length,0);
        consent=true;
        v.buttons[0].dispatch('click');await tick();
        assert.equal(v.calls.length,1);
        assert.equal(v.deleted.length,1);
    });
    await check('409 conflict locks original row against accidental retry',async()=>{
        const v=setup('broiler',{response:()=>conflict()});
        v.instance.bind();
        v.buttons[0].dispatch('click');await tick();
        assert.equal(v.conflicts[0].reason,'record_conflict');
        v.buttons[0].dispatch('click');await tick();
        assert.equal(v.calls.length,1);
        assert.equal(v.confirmed(),1);
        assert.equal(v.errors.at(-1).reason,'reload_required');
    });
    await check('invalid/HTML DELETE response fails closed without native fallback',async()=>{
        const v=setup('layer',{response:()=>({
            status:200,ok:true,headers:{get:()=> 'text/html'},
            async json(){return {};}
        })});
        v.instance.bind();v.buttons[0].dispatch('click');await tick();
        assert.equal(v.deleted.length,0);
        assert.equal(v.errors.at(-1).reason,'reload_required');
        assert.equal(v.calls.length,1);
    });
    await check('overlapping confirmations cannot double POST',async()=>{
        let resolve;
        const pending=new Promise(done=>{resolve=done;});
        const v=setup('layer',{confirm:()=>pending});
        v.instance.bind();
        v.buttons[0].dispatch('click');
        v.buttons[0].dispatch('click');
        await tick();
        assert.equal(v.confirmed(),1);
        assert.equal(v.errors.at(-1).reason,'delete_pending');
        assert.equal(v.calls.length,0);
        resolve(true);await tick();
        assert.equal(v.calls.length,1);
    });
    await check('missing original-row revision tokens fail preflight before binding',async()=>{
        const invalid=button('layer');
        invalid.dataset.b12DeleteTokens='';
        const v=setup('layer',{buttons:[invalid]});
        assert.throws(()=>v.instance.bind(),/not revision-owned/);
        assert.equal(invalid.count('click'),0);
    });
    await check('wrong Poultry species, inline click, missing CSRF refuse binding',async()=>{
        const wrong=button('broiler');
        const v=setup('layer',{buttons:[wrong]});
        assert.throws(()=>v.instance.preflight(),/not revision-owned/);
        const inline=button('layer');
        inline.getAttribute=name=>name==='onclick'?'legacyDelete(19)':null;
        const a=setup('layer',{buttons:[inline]});
        assert.throws(()=>a.instance.bind(),/not revision-owned/);
        const missing=setup('broiler',{csrfToken:' '});
        assert.throws(()=>missing.instance.bind(),/Missing Poultry DELETE CSRF/);
    });
    await check('duplicate binding does not install multiple DELETE click listeners',async()=>{
        const v=setup('layer');
        v.instance.bind();v.instance.bind();
        assert.equal(v.buttons[0].count('click'),1);
        assert.throws(()=>setup('layer',{buttons:v.buttons}).instance.bind(),
            /already bound/);
    });
    await check('Ruminant actual original form submits AJAX, never native POST',async()=>{
        const v=setup('ruminant');
        v.instance.preflight();v.instance.bind();
        assert.equal(v.forms[0].count('submit'),1);
        const e=v.forms[0].dispatch('submit');
        assert.equal(e.prevented,true);
        await tick();
        assert.equal(v.calls.length,1);
        assert.equal(v.calls[0].url,'ruminant_daily_record.php');
        const posted=new URLSearchParams(v.calls[0].opts.body);
        assert.equal(posted.get('delete_record'),'1');
        assert.equal(posted.get('write_intent'),'delete');
        assert.equal(posted.get('original_animal_type'),'goat');
        assert.equal(v.deleted.length,1);
    });
    await check('Ruminant native confirmation rejects partial takeover',async()=>{
        const v=setup('ruminant',{legacyConfirm:true});
        assert.throws(()=>v.instance.bind(),/Legacy Ruminant/);
        assert.equal(v.forms[0].count('submit'),0);
    });
    await check('Ruminant stale revision cannot navigate or retry DELETE',async()=>{
        const v=setup('ruminant',{response:()=>conflict()});
        v.instance.bind();v.forms[0].dispatch('submit');await tick();
        assert.equal(v.conflicts[0].reason,'record_conflict');
        assert.equal(v.calls.length,1);
        v.forms[0].dispatch('submit');await tick();
        assert.equal(v.calls.length,1);
        assert.equal(v.errors.at(-1).reason,'reload_required');
    });
    await check('Ruminant mixed/duplicate form marker blocks all listeners',async()=>{
        const a=form(),b=form();
        b.dataset.b12RevisionDelete='layer';
        const v=setup('ruminant',{forms:[a,b]});
        assert.throws(()=>v.instance.bind(),/not safely cut over/);
        assert.equal(a.count('submit'),0);
    });
    await check('Ruminant empty delete permissions bind nothing and do not fetch',async()=>{
        const v=setup('ruminant',{forms:[]});
        assert.equal(v.instance.preflight(),true);
        v.instance.bind();
        assert.equal(v.calls.length,0);
    });
    console.log('B12_PAGE_DELETE_OWNERSHIP_PURE=PASS');
    console.log('PASS_COUNT='+n);
})().catch(e=>{
    console.error('B12_PAGE_DELETE_OWNERSHIP_PURE=FAIL',e);
    process.exitCode=1;
});
