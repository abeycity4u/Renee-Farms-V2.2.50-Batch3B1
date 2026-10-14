/*
 * B12 Ruminant Daily Record page entry — staged and UNLOADED.
 * No script tags, automatic binding, date-only edit authority or native POST.
 * All shared lifecycle/permission/UI ownership rules stay in ONE adapter.
 */
(function (global) {
    'use strict';
    function create(options) {
        const shared = global.ReneeDailyRecordRevisionPageAdapters;
        if (!shared || typeof shared.createAdapter !== 'function') {
            throw new Error('B12 shared page owner is unavailable.');
        }
        return shared.createAdapter('ruminant', options);
    }
    global.ReneeDailyRecordRevisionRuminantPage = Object.freeze({create});
})(typeof window !== 'undefined' ? window : this);
