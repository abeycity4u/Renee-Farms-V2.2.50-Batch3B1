/*
 * B12 prospectively authorized browser page bootstrap — SOURCE ONLY.
 * NO AUTO INITIALIZATION. No active PHP page loads this script.
 *
 * A separate FUTURE, audited server page cutover must render the B12
 * ownership markers, omit all legacy modal / DELETE controllers, satisfy
 * seven CAS writers and DDL, and explicitly call create(...).bind().
 *
 * This bootstrap never changes revision tokens, SQL, permissions, pricing,
 * posting semantics or population counts. It only composes the shared
 * ticket-guarded editor with established notification/navigation UI.
 */
(function (global) {
    'use strict';

    const routes=Object.freeze({
        layer:Object.freeze({
            page:'layers_daily_record.php',
            config:'layerDailyConfig',
            entry:'ReneeDailyRecordRevisionLayerPage'
        }),
        broiler:Object.freeze({
            page:'broiler_daily_record.php',
            config:'broilerDailyConfig',
            entry:'ReneeDailyRecordRevisionBroilerPage'
        }),
        ruminant:Object.freeze({
            page:'ruminant_daily_record.php',
            config:'ruminantDailyConfig',
            entry:'ReneeDailyRecordRevisionRuminantPage'
        })
    });
    const activeDocuments=new WeakSet();
    const maxId='9223372036854775807';

    function positive(value) {
        return typeof value==='string' &&
            /^[1-9][0-9]*$/.test(value) &&
            value.length<=maxId.length &&
            (value.length!==maxId.length || value<=maxId);
    }
    function month(value) {
        return typeof value==='string' &&
            /^\d{4}-(?:0[1-9]|1[0-2])$/.test(value);
    }
    function validDate(value) {
        if(typeof value!=='string' ||
            !/^\d{4}-\d{2}-\d{2}$/.test(value))return false;
        const y=Number(value.slice(0,4)),m=Number(value.slice(5,7));
        const d=Number(value.slice(8,10));
        const leap=y%4===0&&(y%100!==0||y%400===0);
        const days=[31,leap?29:28,31,30,31,30,31,31,30,31,30,31];
        return y>0&&m>=1&&m<=12&&d>0&&d<=days[m-1];
    }
    const blockedReasons=Object.freeze({
        record_conflict:'The Daily Record changed. Reload before trying again.',
        reload_required:'Please reload before trying this action again.',
        population_unavailable:'Unable to verify opening stock. Reload and try again.',
        submit_not_ready:'Wait until the selected record finishes loading.',
        population_pending:'Wait for the opening-stock check.',
        selection_conflict:'The selected record changed. Reload and try again.',
        selection_changed:'The date or animal selection changed. Select again.',
        invalid_selection:'Choose a valid Daily Record date.',
        invalid_species:'Choose the animal type from the selected cycle.',
        submit_failed:'The Daily Record was not saved. Check your entries and reload.',
        authentication_required:'Your session ended. Preserve your draft and sign in again before saving.',
        forbidden:'Your account cannot save this Daily Record. Keep your draft and contact the Farm Admin.',
        subscription_access_required:'This farm subscription does not permit saving. Keep your draft and contact the Farm Admin.',
        revision_client_required:'This form needs a fresh revision-aware page. Preserve the draft and reload.',
        invalid_csrf:'Your form security token is no longer valid. Keep your draft and reload before retrying.',
        invalid_record:'The record details are invalid. Review the draft before retrying.',
        storage_unavailable:'Saving is temporarily unavailable. Keep your draft and try again later.',
        selection_failed:'Unable to load the Daily Record. Retry after reload.',
        reader_failure:'Unable to read the selected record.',
        render_failure:'Unable to display the selected record.',
        delete_pending:'A deletion is already in progress.',
        submit_pending:'A save is already in progress.'
    });
    function create(type,options) {
        const descriptor=routes[type];
        if(!descriptor||!options||!options.document||
            typeof options.document.getElementById!=='function'||
            typeof options.document.querySelectorAll!=='function'||
            typeof options.fetchImpl!=='function'||
            typeof options.currentDate!=='function'||
            !options.bootstrap||!options.bootstrap.Modal||
            typeof options.bootstrap.Modal.getOrCreateInstance!=='function'||
            !options.AppConfirm||typeof options.AppConfirm.ask!=='function'||
            !options.AppNotify||typeof options.AppNotify.success!=='function'||
            typeof options.AppNotify.error!=='function'||
            !options.populationUI||!options.location||
            typeof options.location.assign!=='function'||
            typeof options.location.reload!=='function') {
            throw new Error('Incomplete B12 Daily Record browser integration.');
        }
        const document=options.document;
        const config=document.getElementById(descriptor.config);
        const form=document.getElementById('recordForm');
        const modalElement=document.getElementById('recordModal');
        const modalTitle=document.getElementById('modalTitle');
        const monthPicker=document.getElementById('monthSelector');
        const cyclePicker=document.getElementById('activeCycleSelector');
        if(!config||!config.dataset||!form||!modalElement||!modalTitle||
            !monthPicker||!cyclePicker||
            typeof monthPicker.addEventListener!=='function'||
            typeof cyclePicker.addEventListener!=='function') {
            throw new Error('B12 page markup is incomplete.');
        }
        const ds=config.dataset;
        // These marker names do NOT exist on current legacy pages.
        // They can only be emitted by a separately authorized PHP switch.
        if(ds.b12RevisionPage!==type||
            ds.b12LegacyRetired!=='1'||
            ds.b12DeleteOwnership!=='replacement') {
            throw new Error('B12 server-rendered page ownership is not ready.');
        }
        const cycle=ds.selectedCycleId;
        if(!positive(cycle) || !month(ds.yearMonth)||
            !['0','1'].includes(ds.canEditOpening)) {
            throw new Error('B12 selected production cycle is not valid.');
        }
        const species=type==='ruminant'
            ?String(ds.selectedCycleAnimalType||'').toLowerCase():'';
        if(type==='ruminant'&&
            !['cattle','goat','sheep','other'].includes(species)) {
            throw new Error('Unsupported selected cycle animal type.');
        }
        if(type!=='ruminant'&&
            (typeof ds.csrfToken!=='string'||!ds.csrfToken.trim()||
            !['0','1'].includes(ds.canDelete))) {
            throw new Error('Poultry B12 page permission evidence is missing.');
        }
        if(typeof form.getAttribute==='function' &&
            form.getAttribute('data-record-form')==='true'){
            throw new Error('Competing generic Daily Record submit.');
        }
        // A simple B12 page marker is NOT sufficient: the actual legacy
        // script may still own the modal and DELETE buttons.
        const scripts=Array.from(document.querySelectorAll('script[src]'));
        const legacy=/(?:^|\/)(?:layers|broiler|ruminant)-daily-record\.js(?:[?#]|$)/i;
        if(scripts.some(script=>legacy.test(String(
            typeof script.getAttribute==='function'
                ?script.getAttribute('src')||'':script.src||''
        ))))throw new Error('Legacy Daily Record controller is still loaded.');
        const page=global[descriptor.entry];
        if(!page||typeof page.create!=='function') {
            throw new Error('B12 species adapter is unavailable.');
        }
        if(typeof options.populationUI.createSoldStockDisplay!=='function' ||
            typeof options.populationUI.createRuminantMovementDisplay!=='function') {
            throw new Error('B12 population display module is unavailable.');
        }
        if(activeDocuments.has(document)){
            throw new Error('B12 Daily Record page already has a browser owner.');
        }
        const display=type==='ruminant'
            ?options.populationUI.createRuminantMovementDisplay()
            :options.populationUI.createSoldStockDisplay({type,cycleId:Number(cycle)});
        if(!display||typeof display.clear!=='function'||
            typeof display.render!=='function'){
            throw new Error('B12 population panel is unavailable.');
        }
        const modal=options.bootstrap.Modal.getOrCreateInstance(modalElement);
        if(!modal||typeof modal.show!=='function'){
            throw new Error('B12 Bootstrap modal is unavailable.');
        }
        const ask=(message,detail)=>options.AppConfirm.ask(message,detail);
        const notify=detail=>{
            const reason=detail&&detail.reason;
            const msg=blockedReasons[reason]||'The action could not be completed.';
            options.AppNotify.error(msg);
        };
        const confirmReplace=()=>{
            // This strict default avoids silent draft loss. Future UX may
            // introduce a proven dirty-state guard only with focused tests.
            return ask(
                'Open the selected Daily Record? Unsaved entries in this form will be discarded.',
                {title:'Change Daily Record?',confirmText:'Open record',tone:'warning'}
            );
        };
        const confirmDelete=()=>ask(
            'Delete this Daily Record? This action cannot be undone.',
            {title:'Delete daily record?',confirmText:'Delete',tone:'danger'}
        );
        const onSaved=()=>{
            options.AppNotify.success('Daily Record saved successfully.');
            options.location.reload();
        };
        const onDeleted=()=>{
            options.AppNotify.success('Daily Record deleted successfully.');
            options.location.reload();
        };
        const onDeleteConflict=detail=>notify(detail);
        const onDeleteBlocked=detail=>notify(detail);
        const adapter=page.create({
            document,form,modalElement,modalTitle,
            pageMarker:config,cycleId:cycle,
            fetchImpl:options.fetchImpl,
            FormDataImpl:options.FormDataImpl,
            URLSearchParamsImpl:options.URLSearchParamsImpl,
            display,
            csrfToken:ds.csrfToken||'',
            selectedCycleAnimalType:species,
            canEditOpeningStock:ds.canEditOpening==='1',
            legacyHandlersRetired:ds.b12LegacyRetired==='1',
            deleteOwnership:ds.b12DeleteOwnership,
            currentDate:()=>{
                const today=options.currentDate();
                return validDate(today)?today:'';
            },
            confirmReplace,showModal:()=>modal.show(),
            notify,onSaved,confirmDelete,onDeleted,
            onDeleteConflict,onDeleteBlocked
        });
        if(!adapter||typeof adapter.bind!=='function'||
            typeof adapter.invalidate!=='function'){
            throw new Error('B12 page adapter is incomplete.');
        }
        let bound=false;
        function navigate(next) {
            // Normal page navigation is not an edit/write authorization.
            adapter.invalidate();
            options.location.assign(next);
        }
        function bind() {
            if(bound)return;
            if(activeDocuments.has(document)) {
                throw new Error('Duplicate B12 page initializer.');
            }
            // The adapter performs its own complete legacy/DELETE preflight
            // before binding. Navigation listeners attach only afterwards.
            adapter.bind();
            bound=true;
            activeDocuments.add(document);
            monthPicker.addEventListener('blur',()=>{
                const date=monthPicker.value;
                if(!validDate(date)){
                    notify({reason:'invalid_selection'});
                    return;
                }
                navigate(descriptor.page+'?month='+encodeURIComponent(date.slice(0,7))+
                    '&cycle_id='+encodeURIComponent(cycle));
            });
            cyclePicker.addEventListener('change',()=>{
                const target=String(cyclePicker.value||'');
                // Zero is permitted for NAVIGATION to all cycles only.
                // Editor scope remains pinned to the positive original ID.
                if(target!=='0'&&!positive(target)) {
                    notify({reason:'selection_changed'});
                    return;
                }
                navigate(descriptor.page+'?month='+encodeURIComponent(ds.yearMonth)+
                    '&cycle_id='+encodeURIComponent(target));
            });
        }
        return Object.freeze({bind,adapter});
    }
    global.ReneeDailyRecordRevisionPageBootstrap=Object.freeze({create});
})(typeof window!=='undefined'?window:this);
