/*
 * B12 staged shared Daily Record PAGE handler ownership.
 * SOURCE ONLY; NOT referenced by any live PHP page. No automatic binding.
 *
 * A future coordinated cutover MUST retire existing per-species Daily Record
 * JS and same-modal attachEditModal/data-confirm handlers, then set an
 * explicit page marker. This module refuses legacy or partial ownership.
 * No date-only existence check or get_record.php, no native POST, no direct
 * population fetch, no SQL, and no business formula duplication.
 */
(function (global) {
    'use strict';
    const types = Object.freeze(['layer','broiler','ruminant']);
    const retiredScript =
        /(?:^|\/)(?:layers|broiler|ruminant)-daily-record\.js(?:\?|$)/i;

    function createAdapter(type, options) {
        if (!types.includes(type) || !options || typeof options !== 'object' ||
            !options.form || typeof options.form.querySelector !== 'function' ||
            typeof options.form.reset !== 'function' ||
            !options.document || typeof options.document.querySelectorAll !== 'function' ||
            !options.modalElement || !options.pageMarker ||
            !options.pageMarker.dataset ||
            typeof options.fetchImpl !== 'function' ||
            !options.display || typeof options.display.render !== 'function' ||
            typeof options.display.clear !== 'function') {
            throw new Error('Incomplete B12 Daily Record page integration.');
        }
        for (const action of ['confirmReplace','showModal','notify','onSaved']) {
            if (typeof options[action] !== 'function') {
                throw new Error('Missing B12 page action: '+action);
            }
        }
        const module = global.ReneeDailyRecordRevisionPageLifecycle;
        const fieldsModule = global.ReneeDailyRecordRevisionPageFields;
        if (!module || typeof module.createLifecycle !== 'function' ||
            !fieldsModule || typeof fieldsModule.createFields !== 'function') {
            throw new Error('Shared B12 Daily Record modules are not available.');
        }
        const form=options.form;
        const field=id=>form.querySelector('#'+id);
        const date=field('selectedDate'),hiddenDate=field('recordDate');
        const animal=type==='ruminant'?field('animalType'):null;
        const hiddenAnimal=type==='ruminant'?field('animalTypeHidden'):null;
        const opening=field('openingStock'),save=form.querySelector('[name="save_record"]');
        const title=options.modalTitle;
        if (!date || !hiddenDate || !opening || !save ||
            !title || !('textContent' in title) ||
            (type==='ruminant'&&(!animal || !hiddenAnimal))) {
            throw new Error('Incomplete B12 Daily Record modal fields.');
        }
        // One central derived-field service; thin animal-type entrypoints
        // must not reimplement rates, feed units, or stock calculations.
        // Neither module binds listeners or fetches during construction.
        const derived=fieldsModule.createFields({
            type,form,document:options.document
        });
        let lifecycle, bound=false;
        let switchGeneration=0;

        function pending() {
            save.disabled=true;
            opening.readOnly=false;
            title.textContent='Loading Daily Record...';
            if (typeof options.onPending==='function') options.onPending();
        }
        lifecycle=module.createLifecycle({
            type,cycleId:options.cycleId,form,
            modalElement:options.modalElement,
            fetchImpl:options.fetchImpl,
            FormDataImpl:options.FormDataImpl,
            URLSearchParamsImpl:options.URLSearchParamsImpl,
            clearPopulation:()=>options.display.clear(),
            onPending:pending,
            onReady:ready=>{
                save.disabled=true;
                // Authoritative EDIT form values are hydrated by the shared
                // editor before this point; no second date-only read.
                derived.ready(ready);
                title.textContent=ready.mode==='edit'?'Edit Record':'Add Daily Record';
                if (typeof options.onReady==='function')options.onReady(ready);
            },
            onPopulation:(payload,scope)=>{
                // The lifecycle has already enforced the revision ticket.
                // Display only: no hidden identity, mortality, or SQL updates.
                if (type==='ruminant') {
                    const mortality=scope.mode==='edit'
                        ? Number(scope.record?.mortality||0):0;
                    options.display.render(payload,mortality);
                } else {
                    options.display.render(payload);
                }
                if (scope.mode==='create' && payload.closing_stock != null &&
                    options.canEditOpeningStock !== true) {
                    opening.readOnly=true;
                }
                if(typeof options.onPopulation==='function') {
                    options.onPopulation(payload,scope);
                }
            },
            onPopulationReady:scope=>{
                if (!lifecycle.isReady()) {
                    save.disabled=true;
                    return;
                }
                // CREATE opening stock may have been populated asynchronously
                // WITHOUT a DOM input event. Recompute Layer rate before
                // allowing the form to become submittable.
                derived.computeLayer();
                save.disabled=false;
                if(typeof options.onPopulationReady==='function') {
                    options.onPopulationReady(scope);
                }
            },
            onBlocked:detail=>{
                save.disabled=true;
                options.notify(detail);
            },
            onSaved:result=>{
                save.disabled=true;
                options.onSaved(result);
            }
        });

        function ownership() {
            if (bound) return;
            // Both an explicit page-level cutover marker AND absence of the
            // old script are mandatory. A JS-only marker does not establish
            // server/tenant/permission authority.
            if (options.pageMarker.dataset.b12RevisionPage!==type ||
                options.legacyHandlersRetired!==true ||
                options.deleteOwnership!=='replacement' ||
                (typeof form.getAttribute==='function' &&
                    form.getAttribute('data-record-form')==='true')) {
                throw new Error('B12 page handler ownership not transferred.');
            }
            const scripts=options.document.querySelectorAll('script[src]');
            for (const script of scripts) {
                if (retiredScript.test(String(
                    typeof script.getAttribute==='function'
                        ? script.getAttribute('src')||'' : script.src||''
                ))) {
                    throw new Error('Legacy Daily Record page script is still loaded.');
                }
            }
            // Ruminant native confirmation has a document-level capture
            // listener that calls form.submit(): never bind both paths.
            if(type==='ruminant') {
                // The global confirmation capture listener can call native
                // form.submit(), bypassing a JS editor's submit guard.
                if(options.document.querySelectorAll('form[data-confirm]').length!==0) {
                    throw new Error('Legacy Ruminant native DELETE is still active.');
                }
            } else {
                // A future enabled page must carry the ORIGINAL row revision
                // beside each deletable Poultry row, not fetch it on DELETE.
                for(const button of options.document.querySelectorAll(
                    '[data-poultry-daily-delete-id]'
                )) {
                    if(!button.dataset ||
                        typeof button.dataset.b12DeleteTokens!=='string' ||
                        button.dataset.b12DeleteTokens==='') {
                        throw new Error('Poultry DELETE original-row tokens unavailable.');
                    }
                }
            }
        }

        async function open(selection) {
            if (!bound) throw new Error('B12 page handler is not bound.');
            const attempt=++switchGeneration;
            if (!selection || typeof selection.recordDate!=='string' ||
                !/^\d{4}-\d{2}-\d{2}$/.test(selection.recordDate)) {
                options.notify({reason:'invalid_selection'});
                return {status:'blocked'};
            }
            let species='';
            if(type==='ruminant') {
                species=String(selection.animalType||'').toLowerCase();
                if(!['cattle','goat','sheep','other'].includes(species)) {
                    options.notify({reason:'invalid_species'});
                    return {status:'blocked'};
                }
            }
            let permitted=false;
            try {permitted=await options.confirmReplace(selection)===true;}catch(_){}
            if(attempt!==switchGeneration)return {status:'stale'};
            if(!permitted)return {status:'cancelled'};
            // The user explicitly approved leaving the prior form scope.
            // A failed GET after this selection does NOT erase new draft data.
            lifecycle.invalidate();
            form.reset();
            derived.reset(); // Derived values cannot leak across selections.
            date.value=selection.recordDate;
            hiddenDate.value=selection.recordDate;
            if(animal)animal.value=species;
            if(hiddenAnimal)hiddenAnimal.value=species;
            opening.readOnly=false;
            save.disabled=true;
            options.showModal();
            return lifecycle.select(); // New immutable scope/exact GET.
        }

        function bind() {
            if(bound)return;
            ownership(); // No partial listener binding when legacy owns page.
            const actions=[];
            for(const selector of ['.add-record-btn','.edit-record-btn']) {
                for(const button of options.document.querySelectorAll(selector)) {
                    if (!button || typeof button.addEventListener!=='function') {
                        throw new Error('Invalid B12 calendar action.');
                    }
                    actions.push(button);
                }
            }
            // Validate all species-specific UI fields before attaching the
            // shared controller and any calendar action listeners.
            derived.bind();
            bound=true;
            save.disabled=true;
            lifecycle.bind();
            for(const button of actions) {
                button.addEventListener('click',()=>{
                        const ds=button.dataset||{};
                        const species=type==='ruminant'
                            ?(ds.animalType||ds.cycleAnimalType||
                                options.selectedCycleAnimalType||''):'';
                        open({
                            recordDate:ds.recordDate||'',
                            animalType:species
                        }).catch(()=>options.notify({reason:'selection_failed'}));
                });
            }
            // The lifecycle/editor already invalidate selection tokens on
            // these events. Do not also install a second date-only fetch.
            date.addEventListener('change',()=>{save.disabled=true;});
            if(animal)animal.addEventListener('change',()=>{save.disabled=true;});
        }

        function invalidate() {
            ++switchGeneration;
            save.disabled=true;
            lifecycle.invalidate();
        }
        return Object.freeze({
            bind,open,invalidate,
            isReady:()=>lifecycle.isReady()&&save.disabled===false,
            mode:()=>lifecycle.mode(),
            populationSettled:()=>lifecycle.populationSettled()
        });
    }
    global.ReneeDailyRecordRevisionPageAdapters=Object.freeze({
        createAdapter
    });
})(typeof window!=='undefined'?window:this);
