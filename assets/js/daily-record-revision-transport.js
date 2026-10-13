/*
 * B12 staged scope/exact GET transport — SOURCE ONLY; NOT LOADED.
 *
 * The existing three Daily Record pages still use their old scripts while
 * the hard-off server cutover is disabled. No implicit WRITE, CREATE
 * permission, fallback to a date-only read, or use of POST/session farm IDs.
 *
 * createRequest({ fetchImpl }) is injected into the existing dormant
 * ReneeDailyRecordRevisionModal.loadSelection() and editor bridge. The modal
 * owns selection tickets, token clearing and immutable identity validation.
 * This transport owns strict query shape, GET status and JSON responses.
 */
(function (global) {
    'use strict';

    const types = Object.freeze(['layer', 'broiler', 'ruminant']);
    const maxId = '9223372036854775807';
    const endpoint = '../api/daily_record_revision_read.php';

    function positiveId(value) {
        if (typeof value !== 'string' || !/^[1-9][0-9]*$/.test(value) ||
            value.length > maxId.length ||
            (value.length === maxId.length && value > maxId)) {
            throw new Error('Invalid Daily Record identity.');
        }
        return value;
    }

    function requestParameters(input) {
        if (!input || typeof input !== 'object' || Array.isArray(input)) {
            throw new Error('Daily Record selection is unavailable.');
        }
        const allowed = ['mode', 'type', 'cycle_id', 'record_date',
            'animal_type', 'record_id'];
        const keys = Object.keys(input);
        if (keys.some(key => !allowed.includes(key)) ||
            typeof input.mode !== 'string' ||
            typeof input.type !== 'string' ||
            typeof input.cycle_id !== 'string' ||
            typeof input.record_date !== 'string' ||
            !['scope', 'exact'].includes(input.mode) ||
            !types.includes(input.type) ||
            !/^\d{4}-\d{2}-\d{2}$/.test(input.record_date) ||
            (input.cycle_id !== '' && !/^[1-9][0-9]*$/.test(input.cycle_id))) {
            throw new Error('Invalid Daily Record selection.');
        }
        if (input.cycle_id !== '') positiveId(input.cycle_id);
        const animalRequired = input.type === 'ruminant';
        if (animalRequired !== Object.prototype.hasOwnProperty.call(input, 'animal_type') ||
            (animalRequired && (
                typeof input.animal_type !== 'string' ||
                input.animal_type.trim() !== input.animal_type ||
                !input.animal_type ||
                input.animal_type.length > 100
            ))) {
            throw new Error('Invalid Daily Record species selection.');
        }
        const exact = input.mode === 'exact';
        if (exact !== Object.prototype.hasOwnProperty.call(input, 'record_id')) {
            throw new Error('The selected Daily Record identity is incomplete.');
        }
        if (exact) positiveId(input.record_id);

        const order = ['mode', 'type', 'cycle_id', 'record_date'];
        if (animalRequired) order.push('animal_type');
        if (exact) order.push('record_id');
        return order.map(key => encodeURIComponent(key) + '=' +
            encodeURIComponent(input[key])).join('&');
    }

    function createRequest(options) {
        if (!options || typeof options !== 'object' ||
            typeof options.fetchImpl !== 'function') {
            throw new Error('Authenticated Daily Record GET transport is unavailable.');
        }
        // No caller-provided endpoint or host is accepted; the URL is a
        // fixed same-origin relative API route, never a remote service.
        const fetchImpl = options.fetchImpl;
        return async function request(selection) {
            const query = requestParameters(selection);
            let response;
            try {
                response = await fetchImpl(endpoint + '?' + query, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    redirect: 'error',
                    headers: {Accept: 'application/json'}
                });
            } catch (_) {
                throw new Error('Daily Record lookup is unavailable. Reload and review.');
            }
            if (!response || response.ok !== true || response.status !== 200 ||
                !response.headers || typeof response.headers.get !== 'function' ||
                !/^application\/json(?:\s*;|$)/i.test(
                    String(response.headers.get('content-type') || '').trim()
                ) || typeof response.json !== 'function') {
                // 404/409/403/503, login redirects and non-JSON pages can
                // NEVER be translated into CREATE or a cached EDIT snapshot.
                throw new Error('Daily Record lookup could not be verified. Reload and review.');
            }
            let data;
            try {
                data = await response.json();
            } catch (_) {
                throw new Error('Daily Record lookup returned an invalid response.');
            }
            if (!data || typeof data !== 'object' || Array.isArray(data) ||
                data.success !== true || data.mode !== selection.mode ||
                !data.data || typeof data.data !== 'object' ||
                Array.isArray(data.data)) {
                throw new Error('Daily Record lookup identity is not authoritative.');
            }
            return data;
        };
    }

    global.ReneeDailyRecordRevisionTransport = Object.freeze({
        createRequest, requestParameters
    });
})(typeof window !== 'undefined' ? window : this);
