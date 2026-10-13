/*
 * B12 shared browser editor bridge. SOURCE ONLY; intentionally NOT LOADED.
 *
 * Future activation requires the three-table record_revision migration,
 * authenticated GET read route, all seven revision-aware writers, and
 * removal of the existing date-only async handlers in all three editors.
 *
 * This does not perform POST, change authorization, or imply CREATE
 * permission. The server must enforce original client revision, tenant,
 * CSRF, module permission, and atomic downstream effects.
 */
(function (global) {
    'use strict';

    const types = Object.freeze({
        layer: Object.freeze({
            birds_age: 'birdsAge',
            opening_stock: 'openingStock',
            mortality: 'mortality',
            egg_production: 'eggProduction',
            feed_consumption_bags: 'feedConsumption',
            feed_item_id: 'feedItemId',
            water_consumption_liters: 'waterConsumption',
            crates_count: 'cratesCount',
            laying_rate: 'layingRate',
            medications: 'medications',
            remarks: 'remarks'
        }),
        broiler: Object.freeze({
            birds_age: 'birdsAge',
            opening_stock: 'openingStock',
            mortality: 'mortality',
            feed_consumption_bags: 'feedConsumption',
            feed_item_id: 'feedItemId',
            water_consumption_liters: 'waterConsumption',
            medications: 'medications',
            remarks: 'remarks'
        }),
        ruminant: Object.freeze({
            opening_stock: 'openingStock',
            mortality: 'mortality',
            feed_consumption_kg: 'feedConsumption',
            feed_item_id: 'feedItemId',
            feed_consumption_unit: 'feedConsumptionUnit',
            water_consumption_liters: 'waterConsumption',
            tag_no: 'tagNo',
            medications: 'medications',
            reproduction_details: 'reproductionDetails',
            other_details: 'otherDetails',
            remarks: 'remarks'
        })
    });

    function createEditor(options) {
        if (!options || typeof options !== 'object' || !types[options.type]) {
            throw new Error('Unsupported Daily Record editor.');
        }
        const form = options.form;
        if (!form || typeof form.querySelector !== 'function' ||
            typeof form.addEventListener !== 'function') {
            throw new Error('Missing Daily Record form.');
        }
        const date = form.querySelector('#selectedDate');
        const hiddenDate = form.querySelector('#recordDate');
        const animal = options.type === 'ruminant'
            ? form.querySelector('#animalType') : null;
        const hiddenAnimal = options.type === 'ruminant'
            ? form.querySelector('#animalTypeHidden') : null;
        if (!date || !hiddenDate || (options.type === 'ruminant' &&
            (!animal || !hiddenAnimal))) {
            throw new Error('Incomplete Daily Record selection controls.');
        }
        // Keep the cycle identity as an exact integer string; zero/empty may
        // denote a legacy NULL cycle, NEVER the all-cycles wildcard.
        const rawCycle = options.cycleId;
        const cycle = rawCycle === null || rawCycle === ''
            ? '' : String(rawCycle);
        if (cycle !== '' && !/^[1-9][0-9]*$/.test(cycle)) {
            throw new Error('Invalid Daily Record cycle selection.');
        }
        const client = global.ReneeDailyRecordRevisionModal;
        if (!client || typeof client.createController !== 'function' ||
            typeof options.request !== 'function') {
            throw new Error('The revision-aware Daily Record reader is not available.');
        }

        function scope() {
            return {
                cycle_id: cycle,
                record_date: date.value,
                animal_type: animal ? animal.value : ''
            };
        }

        const controller = client.createController({
            form, type: options.type, readScope: scope
        });
        let requestNumber = 0;
        let readyTicket = null;
        let readyMode = null;

        function notify(callback, payload) {
            if (typeof callback === 'function') callback(payload);
        }
        function invalidate() {
            requestNumber += 1;
            readyTicket = null;
            readyMode = null;
            controller.invalidate();
        }
        function isReady() {
            if (readyTicket === null) return false;
            // An external date/species change may happen before its change
            // listener fires. Clear original hidden edit tokens immediately
            // rather than merely reporting "not ready".
            if (!controller.isCurrent(readyTicket)) {
                invalidate();
                return false;
            }
            if (!controller.isReady()) {
                readyTicket = null;
                readyMode = null;
                return false;
            }
            return true;
        }
        function guard(ticket, callback) {
            if (typeof callback !== 'function' || ticket !== readyTicket ||
                !isReady()) {
                return false;
            }
            callback();
            return true;
        }
        function fillRecord(record) {
            // Closed field map. No HTML injection, server-side farm/user
            // metadata, arbitrary object keys, or fabricated revision.
            const map = types[options.type];
            Object.keys(map).forEach(key => {
                const input = form.querySelector('#' + map[key]);
                if (input && Object.prototype.hasOwnProperty.call(record, key)) {
                    input.value = record[key] === null ? '' : String(record[key]);
                }
            });
        }
        async function select() {
            // The canonical controller clears previous edit tokens
            // synchronously when loadSelection() calls begin().
            requestNumber += 1;
            const thisRequest = requestNumber;
            readyTicket = null;
            readyMode = null;
            hiddenDate.value = date.value;
            if (hiddenAnimal) hiddenAnimal.value = animal.value;
            notify(options.onPending);
            let result;
            try {
                result = await controller.loadSelection(options.request);
            } catch (_) {
                // Even unexpected failures must not revive prior tokens.
                if (requestNumber !== thisRequest) return {status:'stale'};
                invalidate();
                notify(options.onBlocked, {reason:'reader_failure'});
                return {status:'blocked'};
            }
            if (requestNumber !== thisRequest || result.status === 'stale') {
                return {status:'stale'};
            }
            if (result.status !== 'ready' ||
                !controller.isCurrent(result.ticket) || !controller.isReady()) {
                invalidate();
                notify(options.onBlocked, {reason:'selection_conflict'});
                return {status:'blocked'};
            }
            readyTicket = result.ticket;
            readyMode = result.mode;

            // Do not allow a stale asynchronous result to fill ANY business
            // fields or show side panels belonging to a newer selection.
            if (result.mode === 'edit') {
                fillRecord(result.record);
                if (animal) animal.disabled = true;
            } else {
                if (animal) animal.disabled = false;
            }
            if (hiddenAnimal) hiddenAnimal.value = animal.value;
            notify(options.onReady, {
                mode: result.mode,
                record: result.record,
                ticket: result.ticket,
                guard: fn => guard(result.ticket, fn)
            });
            if (!isReady()) {
                invalidate();
                notify(options.onBlocked, {reason:'selection_changed'});
                return {status:'blocked'};
            }
            return {status:'ready', mode:result.mode, ticket:result.ticket};
        }

        function handleSubmit(event) {
            if (!isReady()) {
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }
                notify(options.onBlocked, {reason:'submit_not_ready'});
            }
        }
        // Capture-phase guard executes before legacy normalization handlers.
        // Future activation MUST retire legacy edit/existence listeners: a
        // capture listener alone cannot make legacy POST-time UPSERT safe.
        function bind() {
            form.addEventListener('submit', handleSubmit, true);
            form.addEventListener('reset', invalidate);
            date.addEventListener('change', invalidate);
            if (animal) animal.addEventListener('change', invalidate);
            const modal = options.modalElement;
            if (modal && typeof modal.addEventListener === 'function') {
                modal.addEventListener('hidden.bs.modal', invalidate);
            }
        }
        invalidate();
        return Object.freeze({
            select, invalidate, isReady, guard, bind,
            mode: () => isReady() ? readyMode : null
        });
    }

    global.ReneeDailyRecordRevisionEditor = Object.freeze({createEditor});
})(typeof window !== 'undefined' ? window : this);
