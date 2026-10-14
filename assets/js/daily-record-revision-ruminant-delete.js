/*
 * B12 Ruminant Daily Record DELETE form binder — SOURCE ONLY, NOT LOADED.
 *
 * The delegated and privileged PHP handlers already share a guarded
 * JSON/409 contract. This binder may only be activated after the original
 * native data-confirm listener is removed from the form, and after the
 * full seven-writer/DDL/source/browser cutover is approved.
 *
 * No automatic listeners, synthetic tokens, network calls, navigation or
 * implicit form submission. Callbacks control the existing styled confirm,
 * success and error experiences. A rejected request never retries as legacy.
 */
(function (global) {
    'use strict';

    const boundForms = new WeakSet();

    function createBindings(options) {
        if (!options || !Array.isArray(options.forms) ||
            options.forms.length === 0 ||
            typeof options.fetchImpl !== 'function') {
            throw new Error('Incomplete B12 Ruminant DELETE binding configuration.');
        }
        for (const name of ['confirm','onDeleted','onConflict','onBlocked']) {
            if (typeof options[name] !== 'function') {
                throw new Error('Missing B12 Ruminant DELETE UI callback.');
            }
        }
        const shared = global.ReneeDailyRecordRevisionDelete;
        if (!shared || typeof shared.createRuminantFormSender !== 'function') {
            throw new Error('The shared B12 DELETE transport is missing.');
        }
        const forms = options.forms;
        if (new Set(forms).size !== forms.length ||
            forms.some(form => !form ||
                typeof form.addEventListener !== 'function' ||
                typeof form.hasAttribute !== 'function' ||
                !form.dataset ||
                form.dataset.b12RevisionDelete !== 'ruminant' ||
                form.hasAttribute('data-confirm') ||
                boundForms.has(form))) {
            throw new Error('Ruminant DELETE form is not safely cut over.');
        }
        const send = shared.createRuminantFormSender({
            fetchImpl: options.fetchImpl,
            FormDataImpl: options.FormDataImpl,
            URLSearchParamsImpl: options.URLSearchParamsImpl
        });
        const pending = new WeakSet();
        const revoked = new WeakSet();

        function bind() {
            // Validate the WHOLE set before binding to avoid half-cutover
            // forms. Never use the existing data-confirm native form handler.
            if (forms.some(form => boundForms.has(form))) {
                throw new Error('B12 DELETE form is already bound.');
            }
            for (const form of forms) {
                boundForms.add(form);
                form.addEventListener('submit', event => {
                    // Always block a native POST, including during loading or
                    // after failure. Otherwise redirects lose conflict context.
                    if (event && typeof event.preventDefault === 'function') {
                        event.preventDefault();
                    }
                    if (pending.has(form)) {
                        options.onBlocked({reason:'delete_pending'});
                        return;
                    }
                    if (revoked.has(form)) {
                        options.onBlocked({reason:'reload_required'});
                        return;
                    }
                    pending.add(form);
                    // A user-confirmation provider is explicitly required:
                    // no silent delete, auto-confirm, or implicit retry.
                    Promise.resolve().then(() => options.confirm(form)).then(
                        confirmed => {
                            if (confirmed !== true) return {status:'cancelled'};
                            return send({form});
                        }
                    ).then(result => {
                        if (!result || result.status === 'cancelled') return;
                        if (result.status === 'deleted') {
                            revoked.add(form);
                            options.onDeleted(result);
                        } else if (result.status === 'conflict') {
                            revoked.add(form);
                            options.onConflict({
                                reason:'record_conflict',
                                message:'The Daily Record changed. Reload before deleting.'
                            });
                        } else {
                            revoked.add(form);
                            options.onBlocked({reason:'reload_required'});
                        }
                    }).catch(() => {
                        revoked.add(form);
                        options.onBlocked({reason:'reload_required'});
                    }).finally(() => pending.delete(form));
                }, true);
            }
        }

        return Object.freeze({bind});
    }

    global.ReneeDailyRecordRevisionRuminantDelete = Object.freeze({createBindings});
})(typeof window !== 'undefined' ? window : this);
