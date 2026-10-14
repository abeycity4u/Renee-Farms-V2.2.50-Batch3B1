/*
 * B12 staged revision-aware Poultry Daily Record DELETE sender.
 * SOURCE ONLY: no active script tag, no native delete behavior replaced.
 *
 * The exact revision tokens must come from the originally rendered,
 * tenant-owned row (data-b12-delete-tokens), NEVER a fresh DELETE-time GET.
 * AJAX callers must confirm first and handle deleted/conflict/blocked states.
 * HTTP authentication, CSRF, tenancy, role and row lock are server-owned.
 * Ruminant keeps its existing guarded server-rendered DELETE form.
 */
(function (global) {
    'use strict';

    const types = Object.freeze(['layer', 'broiler']);
    const maxId = '9223372036854775807';
    const maxRevision = '9223372036854775806';
    const names = Object.freeze([
        'write_intent','record_id','expected_revision',
        'original_cycle_id','original_record_date','original_animal_type'
    ]);
    const endpoint = '../api/delete_record.php';

    function decimal(value, max) {
        if (typeof value !== 'string' || !/^[1-9][0-9]*$/.test(value) ||
            value.length > max.length ||
            (value.length === max.length && value > max)) {
            throw new Error('Invalid Daily Record delete identity.');
        }
        return value;
    }

    function checkedDate(value) {
        if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            throw new Error('Invalid Daily Record delete date.');
        }
        const year = Number(value.slice(0,4));
        const month = Number(value.slice(5,7));
        const day = Number(value.slice(8,10));
        const leap = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);
        const days = [31,leap?29:28,31,30,31,30,31,31,30,31,30,31];
        if (year < 1 || month < 1 || month > 12 ||
            day < 1 || day > days[month-1]) {
            throw new Error('Invalid Daily Record delete date.');
        }
        return value;
    }

    function sourceTokens(button, type, id) {
        if (!button || !button.dataset ||
            button.dataset.poultryDailyDeleteType !== type ||
            button.dataset.poultryDailyDeleteId !== id ||
            typeof button.dataset.b12DeleteTokens !== 'string') {
            throw new Error('Missing originally loaded Daily Record delete tokens.');
        }
        let raw;
        try {
            raw = JSON.parse(button.dataset.b12DeleteTokens);
        } catch (_) {
            throw new Error('Invalid originally loaded Daily Record delete tokens.');
        }
        if (!raw || typeof raw !== 'object' || Array.isArray(raw) ||
            Object.keys(raw).length !== names.length ||
            !names.every(name=>Object.prototype.hasOwnProperty.call(raw,name) &&
                typeof raw[name] === 'string')) {
            throw new Error('Incomplete Daily Record delete identity.');
        }
        if (raw.write_intent !== 'delete' ||
            decimal(raw.record_id,maxId) !== id ||
            !decimal(raw.expected_revision,maxRevision) ||
            (raw.original_cycle_id !== '' &&
                !decimal(raw.original_cycle_id,maxId)) ||
            raw.original_animal_type !== '') {
            throw new Error('Stale Daily Record delete identity.');
        }
        checkedDate(raw.original_record_date);
        return Object.freeze(Object.fromEntries(names.map(name=>[name,raw[name]])));
    }

    function isJson(response) {
        return response && response.headers &&
            typeof response.headers.get === 'function' &&
            /^application\/json(?:\s*;|$)/i.test(
                String(response.headers.get('content-type') || '').trim()
            ) && typeof response.json === 'function';
    }

    function createSender(options) {
        if (!options || typeof options.fetchImpl !== 'function') {
            throw new Error('B12 Daily Record DELETE transport is unavailable.');
        }
        const Params = options.URLSearchParamsImpl || global.URLSearchParams;
        if (typeof Params !== 'function') {
            throw new Error('B12 Daily Record form encoding is unavailable.');
        }
        const fetchImpl = options.fetchImpl;
        const pending = new Set();

        async function send({type, recordId, button, csrfToken}) {
            if (!types.includes(type) || typeof csrfToken !== 'string' ||
                csrfToken.trim() === '') {
                return Object.freeze({status:'blocked'});
            }
            let id, identity;
            try {
                id=decimal(recordId,maxId);
                identity=sourceTokens(button,type,id);
            } catch (_) {
                return Object.freeze({status:'blocked'});
            }
            const key=type+':'+id;
            if (pending.has(key)) {
                return Object.freeze({status:'blocked',reason:'delete_pending'});
            }
            pending.add(key);
            try {
                const form=new Params();
                form.set('type',type);
                form.set('id',id);
                form.set('csrf_token',csrfToken);
                names.forEach(name=>form.set(name,identity[name]));
                const response=await fetchImpl(endpoint,{
                    method:'POST',
                    credentials:'same-origin',
                    redirect:'error',
                    cache:'no-store',
                    headers:{
                        Accept:'application/json',
                        'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With':'XMLHttpRequest'
                    },
                    body:form.toString()
                });
                if (!isJson(response)) {
                    return Object.freeze({status:'blocked'});
                }
                const data=await response.json();
                if (response.status===409 &&
                    data && data.success===false &&
                    data.code==='record_conflict') {
                    return Object.freeze({status:'conflict'});
                }
                if (!response.ok || response.status!==200 ||
                    !data || data.success!==true ||
                    decimal(data.record_id===undefined?'':String(data.record_id),maxId)!==id) {
                    return Object.freeze({status:'blocked'});
                }
                return Object.freeze({status:'deleted',record_id:id});
            } catch (_) {
                return Object.freeze({status:'blocked'});
            } finally {
                pending.delete(key);
            }
        }
        return send;
    }

    /*
     * Ruminant uses a server-rendered DELETE <form> and the same shared
     * revision policy, but a different same-origin endpoint. SOURCE ONLY.
     * FormData(form) excludes a submit button unless explicitly supplied;
     * preserve the existing PHP route's 'delete_record' trigger here.
     * The form's original hidden tokens must predate this call. No reader,
     * fresh revision synthesis, legacy fallback, or external endpoint.
     */
    function createRuminantFormSender(options) {
        if (!options || typeof options.fetchImpl !== 'function') {
            throw new Error('B12 Ruminant DELETE transport is unavailable.');
        }
        const Form = options.FormDataImpl || global.FormData;
        const Params = options.URLSearchParamsImpl || global.URLSearchParams;
        if (typeof Form !== 'function' || typeof Params !== 'function') {
            throw new Error('B12 Ruminant DELETE form encoding is unavailable.');
        }
        const fetchImpl = options.fetchImpl;
        const pendingForms = new WeakSet();

        function exactlyOne(data, name) {
            const values = data.getAll(name);
            if (!Array.isArray(values) || values.length !== 1 ||
                typeof values[0] !== 'string') {
                throw new Error('Missing or duplicated Ruminant DELETE token.');
            }
            return values[0];
        }

        async function send({form}) {
            if (!form || typeof form !== 'object') {
                return Object.freeze({status:'blocked'});
            }
            if (pendingForms.has(form)) {
                return Object.freeze({status:'blocked',reason:'delete_pending'});
            }
            let data, id;
            try {
                data = new Form(form);
                if (!data || typeof data.getAll !== 'function' ||
                    typeof data.set !== 'function') {
                    throw new Error('Ruminant DELETE form snapshot unavailable.');
                }
                const values = Object.fromEntries(
                    names.map(name => [name, exactlyOne(data,name)])
                );
                if (values.write_intent !== 'delete') {
                    throw new Error('Ruminant DELETE needs originally loaded intent.');
                }
                id = decimal(values.record_id,maxId);
                decimal(values.expected_revision,maxRevision);
                if (values.original_cycle_id !== '') {
                    decimal(values.original_cycle_id,maxId);
                }
                checkedDate(values.original_record_date);
                if (!['cattle','goat','sheep','other'].includes(
                    values.original_animal_type
                )) {
                    throw new Error('Invalid Ruminant source species.');
                }
                if (exactlyOne(data,'record_id') !== id ||
                    exactlyOne(data,'csrf_token').trim() === '' ||
                    data.getAll('farm_id').length !== 0 ||
                    data.getAll('type').length !== 0 ||
                    data.getAll('id').length !== 0 ||
                    data.getAll('save_record').length !== 0) {
                    throw new Error('Conflicting Ruminant DELETE fields.');
                }
                // Never add intent/revision from a POST-time GET or a
                // browser-side lookup. The original form already has them.
                data.set('delete_record','1');
            } catch (_) {
                return Object.freeze({status:'blocked'});
            }

            pendingForms.add(form);
            try {
                const response = await fetchImpl('ruminant_daily_record.php',{
                    method:'POST',
                    credentials:'same-origin',
                    redirect:'error',
                    cache:'no-store',
                    headers:{
                        Accept:'application/json',
                        'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With':'XMLHttpRequest'
                    },
                    body:new Params(data).toString()
                });
                if (!isJson(response)) {
                    return Object.freeze({status:'blocked'});
                }
                const result = await response.json();
                if (response.status === 409 && result &&
                    result.success === false &&
                    result.code === 'record_conflict') {
                    return Object.freeze({status:'conflict'});
                }
                if (response.status !== 200 || response.ok !== true ||
                    !result || result.success !== true ||
                    result.code !== 'deleted' ||
                    decimal(result.record_id === undefined
                        ? '' : String(result.record_id),maxId) !== id) {
                    return Object.freeze({status:'blocked'});
                }
                return Object.freeze({status:'deleted',record_id:id});
            } catch (_) {
                return Object.freeze({status:'blocked'});
            } finally {
                pendingForms.delete(form);
            }
        }
        return send;
    }

    global.ReneeDailyRecordRevisionDelete = Object.freeze({
        createSender, createRuminantFormSender
    });
})(typeof window !== 'undefined' ? window : this);
