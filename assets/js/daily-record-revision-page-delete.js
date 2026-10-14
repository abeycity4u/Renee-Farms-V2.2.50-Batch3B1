/*
 * B12 coordinated page DELETE owner — SOURCE ONLY and NOT LOADED.
 * All three species share confirmation, original-row tokens, 409 feedback,
 * one-time binding and reload-after-conflict rules. No DELETE-time GET,
 * legacy fallback, native form POST, implicit startup or endpoint switch.
 *
 * The caller must retire old page DELETE controllers before bind(). It
 * explicitly invokes preflight() during shared page ownership acquisition.
 */
(function (global) {
    'use strict';
    const supported=['layer','broiler','ruminant'];
    const bound=new WeakSet();

    function createBindings(options) {
        if (!options || !supported.includes(options.type) ||
            !options.document || typeof options.document.querySelectorAll!=='function' ||
            typeof options.fetchImpl!=='function') {
            throw new Error('Incomplete B12 page DELETE ownership.');
        }
        for(const fn of ['confirm','onDeleted','onConflict','onBlocked']){
            if(typeof options[fn]!=='function'){
                throw new Error('Missing B12 page DELETE feedback.');
            }
        }
        const shared=global.ReneeDailyRecordRevisionDelete;
        if(!shared || typeof shared.createSender!=='function' ||
            typeof shared.createRuminantFormSender!=='function') {
            throw new Error('Shared B12 DELETE transport unavailable.');
        }
        const type=options.type,document=options.document;
        let installed=false,plan=null;

        function preflight() {
            if(installed)throw new Error('B12 DELETE handlers already attached.');
            if(type==='ruminant'){
                if(!global.ReneeDailyRecordRevisionRuminantDelete ||
                    typeof global.ReneeDailyRecordRevisionRuminantDelete.createBindings!=='function') {
                    throw new Error('Ruminant B12 DELETE binder unavailable.');
                }
                // Must not leave global capture-phase native confirmation
                // attached to even one old DELETE form.
                if(document.querySelectorAll('form[data-confirm]').length!==0) {
                    throw new Error('Legacy Ruminant native DELETE is still active.');
                }
                const forms=Array.from(document.querySelectorAll(
                    'form[data-b12-revision-delete="ruminant"]'
                ));
                const all=Array.from(document.querySelectorAll(
                    'form[data-b12-revision-delete]'
                ));
                if(all.length!==forms.length || new Set(forms).size!==forms.length){
                    throw new Error('Ruminant DELETE form markers conflict.');
                }
                const binder=forms.length===0?null:
                    global.ReneeDailyRecordRevisionRuminantDelete.createBindings({
                        forms,
                        fetchImpl:options.fetchImpl,
                        FormDataImpl:options.FormDataImpl,
                        URLSearchParamsImpl:options.URLSearchParamsImpl,
                        confirm:form=>options.confirm(form),
                        onDeleted:result=>options.onDeleted(result),
                        onConflict:detail=>options.onConflict(detail),
                        onBlocked:detail=>options.onBlocked(detail)
                    });
                plan={type,forms,binder};
            } else {
                const buttons=Array.from(document.querySelectorAll(
                    '[data-poultry-daily-delete-id]'
                ));
                if(new Set(buttons).size!==buttons.length){
                    throw new Error('Duplicate Poultry DELETE elements.');
                }
                for(const button of buttons) {
                    // Refuse page takeover before any new listener is
                    // installed when an earlier controller owns this row.
                    if(bound.has(button)) {
                        throw new Error('Poultry DELETE button was already bound.');
                    }
                    const ds=button && button.dataset;
                    if(!button || typeof button.addEventListener!=='function' ||
                        typeof button.getAttribute!=='function' ||
                        button.getAttribute('onclick')!==null ||
                        !ds || ds.poultryDailyDeleteType!==type ||
                        typeof ds.poultryDailyDeleteId!=='string' ||
                        !/^[1-9][0-9]*$/.test(ds.poultryDailyDeleteId) ||
                        typeof ds.b12DeleteTokens!=='string' ||
                        ds.b12DeleteTokens==='') {
                        throw new Error('Poultry DELETE row is not revision-owned.');
                    }
                }
                if(buttons.length &&
                    (typeof options.csrfToken!=='string' ||
                     options.csrfToken.trim()==='')) {
                    throw new Error('Missing Poultry DELETE CSRF token.');
                }
                const send=shared.createSender({
                    fetchImpl:options.fetchImpl,
                    URLSearchParamsImpl:options.URLSearchParamsImpl
                });
                plan={type,buttons,send};
            }
            return true;
        }

        function bind() {
            if(installed)return;
            // The page adapter runs this AFTER all broader ownership checks.
            // A second preflight here prevents a changed form/row population
            // from unexpectedly installing listeners on stale markup.
            preflight();
            if(type==='ruminant'){
                if(plan.binder)plan.binder.bind();
                installed=true;
                return;
            }
            const pending=new WeakSet(),revoked=new WeakSet();
            for(const button of plan.buttons){
                if(bound.has(button)){
                    throw new Error('Poultry DELETE button was already bound.');
                }
            }
            for(const button of plan.buttons){
                bound.add(button);
                button.addEventListener('click',event=>{
                    // Fail closed before other potential page click handlers.
                    if(event&&typeof event.preventDefault==='function'){
                        event.preventDefault();
                    }
                    if(event&&typeof event.stopImmediatePropagation==='function'){
                        event.stopImmediatePropagation();
                    }
                    if(pending.has(button)){
                        options.onBlocked({reason:'delete_pending'});
                        return;
                    }
                    if(revoked.has(button)){
                        options.onBlocked({reason:'reload_required'});
                        return;
                    }
                    pending.add(button);
                    Promise.resolve().then(()=>options.confirm(button)).then(
                        yes=>{
                            if(yes!==true)return {status:'cancelled'};
                            return plan.send({
                                type,
                                recordId:button.dataset.poultryDailyDeleteId,
                                button,
                                csrfToken:options.csrfToken
                            });
                        }
                    ).then(result=>{
                        if(!result || result.status==='cancelled')return;
                        revoked.add(button);
                        if(result.status==='deleted'){
                            options.onDeleted(result);
                        }else if(result.status==='conflict'){
                            options.onConflict({
                                reason:'record_conflict',
                                message:'The Daily Record changed. Reload before deleting.'
                            });
                        }else{
                            options.onBlocked({reason:'reload_required'});
                        }
                    }).catch(()=>{
                        revoked.add(button);
                        options.onBlocked({reason:'reload_required'});
                    }).finally(()=>pending.delete(button));
                },true);
            }
            installed=true;
        }
        return Object.freeze({preflight,bind});
    }
    global.ReneeDailyRecordRevisionPageDelete=Object.freeze({createBindings});
})(typeof window!=='undefined'?window:this);
