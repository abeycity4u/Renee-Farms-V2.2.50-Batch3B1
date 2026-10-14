/*
 * B12 source-only POST sender for the three Daily Record modal editors.
 * NOT LOADED by live pages. Requires coordinated seven-writer cutover,
 * browser-loaded immutable tokens, schema, and full authentication/CSRF.
 * The current page's legacy date-only handlers MUST be retired together.
 *
 * This module never does GET, permits a configurable origin/URL, retries
 * an EDIT as CREATE, follows login redirects, or mutates the user's inputs.
 * A 409 revokes the edit selection in the caller, preserving draft fields.
 */
(function (global) {
    'use strict';

    const routes = Object.freeze({
        layer: 'layers_daily_record.php',
        broiler: 'broiler_daily_record.php',
        ruminant: 'ruminant_daily_record.php'
    });
    const maxId = '9223372036854775807';
    const maxRevision = '9223372036854775806';
    const tokens = Object.freeze([
        'write_intent', 'record_id', 'expected_revision',
        'original_cycle_id', 'original_record_date', 'original_animal_type'
    ]);

    function decimal(value, max) {
        const str = typeof value === 'number' && Number.isSafeInteger(value)
            ? String(value) : value;
        if (typeof str !== 'string' || !/^[1-9][0-9]*$/.test(str) ||
            str.length > max.length ||
            (str.length === max.length && str > max)) {
            throw new Error('Invalid Daily Record identity or revision.');
        }
        return str;
    }

    function single(data, name) {
        const values = data.getAll(name);
        if (!Array.isArray(values) || values.length !== 1 ||
            typeof values[0] !== 'string') {
            throw new Error('Incomplete or duplicated Daily Record field: ' + name);
        }
        return values[0];
    }

    function snapshot(data, type, cycleId, mode) {
        const cycle = decimal(cycleId, maxId);
        if (type !== 'ruminant' && single(data, 'cycle_id') !== cycle) {
            throw new Error('Daily Record cycle selection changed.');
        }
        if (type === 'ruminant' && data.getAll('cycle_id').length > 0 &&
            single(data, 'cycle_id') !== cycle) {
            throw new Error('Ruminant cycle selection changed.');
        }
        const date = single(data, 'record_date');
        if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
            throw new Error('Invalid Daily Record date.');
        }
        const species = type === 'ruminant'
            ? single(data, 'animal_type') : '';
        if (type === 'ruminant' &&
            !['cattle','goat','sheep','other'].includes(species)) {
            throw new Error('Invalid Ruminant species.');
        }
        if (type !== 'ruminant' && data.getAll('animal_type').length) {
            throw new Error('Unexpected Poultry species.');
        }
        if (!single(data, 'csrf_token')) {
            throw new Error('Missing Daily Record CSRF token.');
        }
        const intent = single(data, 'write_intent');
        if (intent !== mode || !['create','edit'].includes(intent)) {
            throw new Error('Daily Record write intent changed.');
        }
        const rowId = single(data, 'record_id');
        const expected = single(data, 'expected_revision');
        const originalCycle = single(data, 'original_cycle_id');
        const originalDate = single(data, 'original_record_date');
        const originalSpecies = single(data, 'original_animal_type');
        if (mode === 'create') {
            if ([rowId,expected,originalCycle,originalDate,originalSpecies]
                .some(value => value !== '')) {
                throw new Error('CREATE cannot include a previous identity.');
            }
        } else if (decimal(rowId, maxId) !== rowId ||
            decimal(expected, maxRevision) !== expected ||
            originalCycle !== cycle || originalDate !== date ||
            originalSpecies !== species) {
            throw new Error('The original Daily Record identity changed.');
        }
        // A submit button's name is not included by new FormData(form).
        // The three existing PHP save routes require this exact POST key.
        data.set('save_record', '1');
        return Object.freeze({mode, rowId, expected});
    }

    function jsonResponse(response) {
        return response && response.headers &&
            typeof response.headers.get === 'function' &&
            /^application\/json(?:\s*;|$)/i.test(
                String(response.headers.get('content-type') || '').trim()
            ) && typeof response.json === 'function';
    }

    // Only an exact status+code pair can distinguish early failures.
    // Unknown, HTML, redirect, or unexpected responses remain blocked.
    const failureReasons=Object.freeze({
        '401|authentication_required':'authentication_required',
        '403|forbidden':'forbidden',
        '403|subscription_access_required':'subscription_access_required',
        '409|revision_client_required':'revision_client_required',
        '419|invalid_csrf':'invalid_csrf',
        '422|invalid_record':'invalid_record',
        '503|storage_unavailable':'storage_unavailable'
    });
    function createSender(options) {
        if (!options || typeof options !== 'object' ||
            typeof options.fetchImpl !== 'function') {
            throw new Error('Daily Record POST sender is unavailable.');
        }
        const fetchImpl = options.fetchImpl;
        const Form = options.FormDataImpl || global.FormData;
        const Params = options.URLSearchParamsImpl || global.URLSearchParams;
        if (typeof Form !== 'function' || typeof Params !== 'function') {
            throw new Error('Daily Record form encoding is unavailable.');
        }
        const inFlight = new WeakSet();

        async function send({type, form, cycleId, mode, guard}) {
            if (!Object.prototype.hasOwnProperty.call(routes, type) ||
                !form || typeof form !== 'object' ||
                typeof guard !== 'function' || !['create','edit'].includes(mode)) {
                return Object.freeze({status:'blocked'});
            }
            if (inFlight.has(form)) {
                return Object.freeze({status:'blocked', reason:'submit_pending'});
            }
            let data, original;
            try {
                if (guard(() => {}) !== true) return Object.freeze({status:'stale'});
                data = new Form(form);
                if (typeof data.getAll !== 'function' ||
                    typeof data.set !== 'function') {
                    throw new Error('Invalid Daily Record form snapshot.');
                }
                original = snapshot(data, type, cycleId, mode);
            } catch (_) {
                return Object.freeze({status:'blocked'});
            }
            inFlight.add(form);
            try {
                const target = routes[type] + '?cycle_id=' +
                    encodeURIComponent(decimal(cycleId, maxId));
                const response = await fetchImpl(target, {
                    method: 'POST',
                    credentials: 'same-origin',
                    redirect: 'error',
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new Params(data).toString()
                });
                if (guard(() => {}) !== true) {
                    return Object.freeze({status:'stale'});
                }
                if (!jsonResponse(response)) {
                    return Object.freeze({status:'blocked'});
                }
                const payload = await response.json();
                if (guard(() => {}) !== true) {
                    return Object.freeze({status:'stale'});
                }
                if (response.status === 409 && payload && payload.success === false &&
                    payload.code === 'record_conflict') {
                    return Object.freeze({status:'conflict'});
                }
                if (response.status !== 200 || response.ok !== true ||
                    !payload || payload.success !== true) {
                    const key=String(response.status)+'|'+String(
                        payload && payload.success===false ? payload.code : ''
                    );
                    const reason=failureReasons[key];
                    return Object.freeze(reason
                        ? {status:'blocked',reason}
                        : {status:'blocked'});
                }
                const recordId = decimal(payload.record_id, maxId);
                const nextRevision = decimal(payload.record_revision, maxId);
                const count = payload.continuity_affected;
                if (!Number.isSafeInteger(count) || count < 0 ||
                    (mode === 'edit' &&
                        (recordId !== original.rowId ||
                         BigInt(nextRevision) !== BigInt(original.expected) + 1n)) ||
                    (mode === 'create' && nextRevision !== '1')) {
                    return Object.freeze({status:'blocked'});
                }
                return Object.freeze({
                    status:'saved', mode, record_id:recordId,
                    record_revision:nextRevision,
                    continuity_affected:count
                });
            } catch (_) {
                return Object.freeze({status:'blocked'});
            } finally {
                inFlight.delete(form);
            }
        }
        return send;
    }

    global.ReneeDailyRecordRevisionSubmit = Object.freeze({createSender});
})(typeof window !== 'undefined' ? window : this);
