/*
 * B12: source-only, UNLOADED Daily Record page lifecycle adapter.
 *
 * This is a future thin page integration point, not a live-page replacement.
 * Existing layer/broiler/ruminant controllers MUST be retired together with
 * date-only edit readers and overlapping form handlers before activation.
 *
 * The revision workspace authorizes one immutable edit/create selection.
 * This adapter owns one nonauthoritative population/stock GET per READY
 * selection. A late response can never render a previous selection's side
 * panel or overwrite opening stock. NO population GET can authorize CREATE,
 * EDIT, DELETE, a tenant scope, or a revision token.
 *
 * No automatic initialization, no native form POST, no dynamic endpoints,
 * no background timers, no DB writes and no activation of B12 cutover.
 */
(function (global) {
    'use strict';

    const types = Object.freeze(['layer','broiler','ruminant']);
    const endpoint = '../api/get_previous_stock.php';

    function validDate(value) {
        if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            throw new Error('Invalid Daily Record population date.');
        }
        const y = Number(value.slice(0,4));
        const m = Number(value.slice(5,7));
        const d = Number(value.slice(8,10));
        const leap = y % 4 === 0 && (y % 100 !== 0 || y % 400 === 0);
        const days = [31,leap?29:28,31,30,31,30,31,31,30,31,30,31];
        if (y < 1 || m < 1 || m > 12 || d < 1 || d > days[m-1]) {
            throw new Error('Invalid Daily Record population date.');
        }
        return value;
    }

    function populationPayload(payload) {
        if (!payload || typeof payload !== 'object' ||
            Array.isArray(payload) || payload.success === false ||
            Object.prototype.hasOwnProperty.call(payload,'error')) {
            throw new Error('Untrusted population boundary.');
        }
        if (payload.closing_stock !== undefined &&
            payload.closing_stock !== null) {
            const stock = payload.closing_stock;
            if ((typeof stock !== 'number' && typeof stock !== 'string') ||
                (typeof stock === 'string' && !/^\d+(?:\.\d+)?$/.test(stock)) ||
                !Number.isFinite(Number(stock)) || Number(stock) < 0) {
                throw new Error('Invalid opening-stock boundary.');
            }
        }
        if (payload.movement_totals !== undefined &&
            (payload.movement_totals === null ||
                typeof payload.movement_totals !== 'object' ||
                Array.isArray(payload.movement_totals))) {
            throw new Error('Invalid population movement summary.');
        }
        return payload;
    }

    function createLifecycle(options) {
        if (!options || !types.includes(options.type) ||
            typeof options.fetchImpl !== 'function' || !options.form ||
            typeof options.form.querySelector !== 'function' ||
            typeof options.form.addEventListener !== 'function' ||
            typeof options.cycleId !== 'string') {
            throw new Error('Incomplete B12 page lifecycle.');
        }
        const callbacks = [
            'onPending', 'onReady', 'onPopulation',
            'onPopulationReady', 'onBlocked', 'onSaved', 'clearPopulation'
        ];
        for (const callback of callbacks) {
            if (typeof options[callback] !== 'function') {
                throw new Error('A guarded Daily Record UI callback is missing.');
            }
        }
        const module = global.ReneeDailyRecordRevisionWorkspace;
        if (!module || typeof module.createWorkspace !== 'function') {
            throw new Error('The B12 revision workspace is not available.');
        }
        const form = options.form;
        const date = form.querySelector('#selectedDate');
        const species = options.type === 'ruminant'
            ? form.querySelector('#animalType') : null;
        const opening = form.querySelector('#openingStock');
        if (!date || !opening || (options.type === 'ruminant' && !species) ||
            typeof date.addEventListener !== 'function' ||
            typeof opening.addEventListener !== 'function' ||
            (species && typeof species.addEventListener !== 'function')) {
            throw new Error('Incomplete Daily Record population controls.');
        }
        // The shared workspace requires an exact positive string ID and
        // rejects the existing all-cycles selector (0) before any GET.
        const cycle = options.cycleId;
        const Params = options.URLSearchParamsImpl || global.URLSearchParams;
        if (typeof Params !== 'function') {
            throw new Error('Population query encoding unavailable.');
        }
        let epoch = 0;
        let openingEditVersion = 0;
        let latestPopulation = Promise.resolve({status:'idle'});
        let bound = false;

        function clear() {
            epoch += 1;
            options.clearPopulation();
        }
        function current(ticketEpoch, guard) {
            return ticketEpoch === epoch && guard(() => {}) === true;
        }
        let workspace;

        function blockPopulation(ticketEpoch, guard) {
            if (!current(ticketEpoch,guard)) return {status:'stale'};
            // A failed auxiliary boundary may not leave the form editable
            // with a partially displayed or unverified opening stock.
            clear();
            workspace.invalidate(); // Clears immutable tokens, NOT draft.
            options.onBlocked({reason:'population_unavailable'});
            return {status:'blocked'};
        }

        function loadPopulation(ready) {
            const ticketEpoch = epoch;
            const guard = ready.guard;
            const scopeDate = validDate(date.value);
            const animal = options.type === 'ruminant' ? species.value : '';
            if (options.type === 'ruminant' &&
                !['cattle','goat','sheep','other'].includes(animal)) {
                throw new Error('Unsupported Ruminant population species.');
            }
            const originalOpening = opening.value;
            const editsAtStart = openingEditVersion;
            const params = new Params({
                type:options.type,
                date:scopeDate,
                cycle_id:cycle
            });
            if (options.type === 'ruminant') {
                params.set('animal_type', animal);
            }
            const query = endpoint + '?' + params.toString();
            return Promise.resolve().then(() => {
                if (!current(ticketEpoch,guard)) return {status:'stale'};
                return options.fetchImpl(query,{
                    method:'GET',
                    credentials:'same-origin',
                    redirect:'error',
                    cache:'no-store',
                    headers:{Accept:'application/json'}
                }).then(async response => {
                    if (!current(ticketEpoch,guard)) return {status:'stale'};
                    if (!response || response.status !== 200 ||
                        response.ok !== true || !response.headers ||
                        typeof response.headers.get !== 'function' ||
                        !/^application\/json(?:\s*;|$)/i.test(
                            String(response.headers.get('content-type')||'').trim()
                        ) || typeof response.json !== 'function') {
                        throw new Error('Population boundary unavailable.');
                    }
                    const payload = populationPayload(await response.json());
                    if (!current(ticketEpoch,guard)) return {status:'stale'};
                    const applied = guard(() => {
                        if (ticketEpoch !== epoch) return;
                        // Never rewrite a loaded EDIT or an opening-stock input
                        // modified after the population fetch began.
                        if (ready.mode === 'create' &&
                            openingEditVersion === editsAtStart &&
                            opening.value === originalOpening &&
                            payload.closing_stock != null) {
                            opening.value = String(payload.closing_stock);
                        }
                        options.onPopulation(payload, {
                            type:options.type, mode:ready.mode,
                            cycle_id:cycle, record_date:scopeDate,
                            animal_type:animal, record:ready.record
                        });
                        options.onPopulationReady({
                            type:options.type, mode:ready.mode,
                            record_date:scopeDate
                        });
                    });
                    return applied && current(ticketEpoch,guard)
                        ? {status:'applied'} : {status:'stale'};
                });
            }).catch(() => blockPopulation(ticketEpoch,guard));
        }

        workspace = module.createWorkspace({
            type:options.type,
            cycleId:cycle,
            form,
            modalElement:options.modalElement,
            fetchImpl:options.fetchImpl,
            FormDataImpl:options.FormDataImpl,
            URLSearchParamsImpl:options.URLSearchParamsImpl,
            onPending:() => {
                clear(); // Before any new record scope is read.
                options.onPending();
            },
            onReady:ready => {
                // The editor has already loaded the authoritative row and
                // established a selection ticket. It alone fills edit fields.
                if (ready.guard(() => {}) !== true) {
                    throw new Error('Daily Record selection is no longer current.');
                }
                options.onReady(ready);
                latestPopulation = loadPopulation(ready);
            },
            onBlocked:detail => {
                clear();
                options.onBlocked(detail);
            },
            onSaved:result => {
                clear();
                options.onSaved(result);
            }
        });

        function bind() {
            if (bound) return;
            bound = true;
            workspace.bind();
            opening.addEventListener('input',() => {openingEditVersion++;});
            // The workspace invalidates identity tokens for these events;
            // this adapter also clears only the nonauthoritative side panel.
            date.addEventListener('change',clear);
            if (species) species.addEventListener('change',clear);
            form.addEventListener('reset',clear);
            if (options.modalElement &&
                typeof options.modalElement.addEventListener === 'function') {
                options.modalElement.addEventListener('hidden.bs.modal',clear);
            }
        }
        function invalidate() {
            clear();
            workspace.invalidate();
        }
        function select() {
            return workspace.select(); // No implicit background lookup.
        }
        return Object.freeze({
            bind, select, invalidate,
            isReady:() => workspace.isReady(),
            mode:() => workspace.mode(),
            populationSettled:() => latestPopulation
        });
    }

    global.ReneeDailyRecordRevisionPageLifecycle = Object.freeze({
        createLifecycle
    });
})(typeof window !== 'undefined' ? window : this);
