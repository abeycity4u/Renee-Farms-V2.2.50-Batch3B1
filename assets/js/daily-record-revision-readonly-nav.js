/*
 * B12 WS09 dormant ALL-CYCLES navigation — view-only, explicit bind.
 * This controller MUST NOT fetch/read revision tokens, open editors,
 * submit forms, or attach any edit/delete handlers.
 * Only the future gate-ON all-cycles (cycle_id=0) page may load it.
 */
(function(global){
    'use strict';
    const paths=Object.freeze({
        layer:Object.freeze({page:'layers_daily_record.php',config:'layerDailyConfig'}),
        broiler:Object.freeze({page:'broiler_daily_record.php',config:'broilerDailyConfig'}),
        ruminant:Object.freeze({page:'ruminant_daily_record.php',config:'ruminantDailyConfig'})
    });
    const owned=new WeakSet();
    const maxId='9223372036854775807';
    function positive(value){
        return typeof value==='string' && /^[1-9][0-9]*$/.test(value) &&
            (value.length<maxId.length ||
             (value.length===maxId.length && value<=maxId));
    }
    function month(value){
        return typeof value==='string' &&
            /^\d{4}-(?:0[1-9]|1[0-2])$/.test(value) &&
            value.slice(0,4)!=='0000';
    }
    function date(value){
        if(typeof value!=='string'||!/^\d{4}-\d{2}-\d{2}$/.test(value))return false;
        const y=Number(value.slice(0,4)),m=Number(value.slice(5,7)),d=Number(value.slice(8));
        if(y<1||m<1||m>12)return false;
        const leap=y%4===0&&(y%100!==0||y%400===0);
        const days=[31,leap?29:28,31,30,31,30,31,31,30,31,30,31];
        return d>0&&d<=days[m-1];
    }
    function create(type,options){
        const desc=paths[type];
        if(!desc||!options||!options.document||
            typeof options.document.getElementById!=='function'||
            typeof options.document.querySelectorAll!=='function'||
            !options.location||typeof options.location.assign!=='function'){
            throw new Error('B12 read-only navigation requires a complete page.');
        }
        const document=options.document;
        const config=document.getElementById(desc.config);
        const datePicker=document.getElementById('monthSelector');
        const cyclePicker=document.getElementById('activeCycleSelector');
        if(!config||!config.dataset||!datePicker||!cyclePicker||
            typeof datePicker.addEventListener!=='function'||
            typeof cyclePicker.addEventListener!=='function'||
            config.dataset.selectedCycleId!=='0'||
            !month(config.dataset.yearMonth)||
            config.dataset.b12RevisionPage!==undefined||
            config.dataset.b12LegacyRetired!==undefined||
            config.dataset.b12DeleteOwnership!==undefined){
            throw new Error('Read-only All Cycles ownership is incomplete.');
        }
        const scripts=Array.from(document.querySelectorAll('script[src]'));
        if(scripts.some(script=>/(?:^|\/)(?:layers|broiler|ruminant)-daily-record\.js(?:[?#]|$)/i.test(
            String(typeof script.getAttribute==='function'?
                script.getAttribute('src')||'':script.src||'')))){
            throw new Error('Legacy Daily Record editor cannot own All Cycles navigation.');
        }
        if(owned.has(document))throw new Error('All Cycles navigation already bound.');
        let bound=false;
        function go(value,targetCycle){
            options.location.assign(desc.page+
                '?month='+encodeURIComponent(value)+
                '&cycle_id='+encodeURIComponent(targetCycle));
        }
        function bind(){
            if(bound || owned.has(document))throw new Error('Duplicate All Cycles navigation.');
            bound=true;
            owned.add(document);
            datePicker.addEventListener('blur',()=>{
                if(date(datePicker.value)) {
                    go(datePicker.value.slice(0,7),'0');
                }
            });
            cyclePicker.addEventListener('change',()=>{
                const id=cyclePicker.value;
                if(id==='0'||positive(id))go(config.dataset.yearMonth,id);
            });
        }
        return Object.freeze({bind});
    }
    global.ReneeDailyRecordRevisionReadOnlyNav=Object.freeze({create});
})(typeof window!=='undefined'?window:this);
