/*
 * B12 staged Daily Record browser composition — SOURCE ONLY, NOT LOADED.
 *
 * Connects the existing authenticated scope/exact GET reader, immutable
 * modal selection controller, and fail-closed AJAX POST sender to ONE editor.
 * This file performs NO automatic initialization, event binding or fetch.
 *
 * Activation requires the coordinated three-table record_revision migration,
 * all seven CAS writers, hard-gated PHP read/save, browser deletion tokens,
 * replacement of all legacy date-only readers, and authenticated browser QA.
 *
 * The active pages use selectedCycleId=0 for ALL cycles, not a persisted
 * NULL-cycle. Require an explicit positive cycle string; never reinterpret
 * 0/empty/null as a legacy row or invent a CREATE selection.
 */
(function (global) {
    'use strict';

    const types = Object.freeze(['layer', 'broiler', 'ruminant']);
    const maxId = '9223372036854775807';

    function cycleId(value) {
        if (typeof value !== 'string' || !/^[1-9][0-9]*$/.test(value) ||
            value.length > maxId.length ||
            (value.length === maxId.length && value > maxId)) {
            throw new Error('Select an exact active Daily Record cycle before editing.');
        }
        return value;
    }

    function createWorkspace(options) {
        if (!options || typeof options !== 'object' ||
            !types.includes(options.type) ||
            typeof options.fetchImpl !== 'function' ||
            !options.form ||
            typeof options.form.querySelector !== 'function' ||
            typeof options.form.addEventListener !== 'function') {
            throw new Error('Incomplete revision-aware Daily Record workspace.');
        }

        const cycle = cycleId(options.cycleId);
        for (const callback of ['onPending', 'onReady', 'onBlocked', 'onSaved']) {
            if (typeof options[callback] !== 'function') {
                // A future activation must provide UI state and user-visible
                // failure/success handling, not silently swallow 409/errors.
                throw new Error('Missing revision-aware Daily Record UI callback.');
            }
        }

        const readerModule = global.ReneeDailyRecordRevisionTransport;
        const modalModule = global.ReneeDailyRecordRevisionModal;
        const editorModule = global.ReneeDailyRecordRevisionEditor;
        const submitModule = global.ReneeDailyRecordRevisionSubmit;

        if (!readerModule || typeof readerModule.createRequest !== 'function' ||
            !modalModule || typeof modalModule.createController !== 'function' ||
            !editorModule || typeof editorModule.createEditor !== 'function' ||
            !submitModule || typeof submitModule.createSender !== 'function') {
            throw new Error('Revision-aware Daily Record modules are not available.');
        }

        // Same fetch object, same authenticated browser origin, one shared
        // read scope and one guarded form submission per editor.
        const request = readerModule.createRequest({fetchImpl:options.fetchImpl});
        const submit = submitModule.createSender({
            fetchImpl:options.fetchImpl,
            FormDataImpl:options.FormDataImpl,
            URLSearchParamsImpl:options.URLSearchParamsImpl
        });
        const editor = editorModule.createEditor({
            type:options.type,
            form:options.form,
            cycleId:cycle,
            modalElement:options.modalElement,
            request,
            onSubmit:submit,
            canSubmit:options.canSubmit,
            onPending:options.onPending,
            onReady:options.onReady,
            onBlocked:options.onBlocked,
            onSaved:options.onSaved
        });

        // Only the explicitly called bind() may attach events. New pages must
        // first REMOVE legacy check_record/get_record and submit listeners.
        // A bound B12 editor does not revive any legacy source mutations.
        return Object.freeze({
            bind:() => editor.bind(),
            select:() => editor.select(),
            invalidate:() => editor.invalidate(),
            isReady:() => editor.isReady(),
            mode:() => editor.mode()
        });
    }

    global.ReneeDailyRecordRevisionWorkspace = Object.freeze({createWorkspace});
})(typeof window !== 'undefined' ? window : this);
