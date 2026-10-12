/*
 * B12 shared Daily Record modal intent controller — SOURCE ONLY, NOT WIRED.
 * Requires the future three-table record_revision migration, unique immutable
 * row reader, browser form token rendering, and transactional CAS save/delete.
 * Browser tokens are untrusted and do not confer farm access or permissions.
 *
 * A request ticket belongs to ONE selection, not to a date string. A delayed
 * result from a prior selection (even the same date revisited) is ignored.
 * UI callers must check isCurrent(ticket) before populating ANY other fields
 * or side panels from asynchronous results. Failure never means CREATE.
 */
(function (global) {
    'use strict';

    const names = Object.freeze([
        'write_intent', 'record_id', 'expected_revision',
        'original_cycle_id', 'original_record_date', 'original_animal_type'
    ]);
    const types = Object.freeze(['layer', 'broiler', 'ruminant']);
    const maxId = '9223372036854775807'; // PHP_INT_MAX on certified 64-bit PHP.
    const maxRevision = '9223372036854775806';

    function decimal(value, maximum) {
        const text = typeof value === 'number'
            ? (Number.isSafeInteger(value) ? String(value) : '')
            : (typeof value === 'string' ? value : '');
        if (!/^[1-9][0-9]*$/.test(text) ||
            text.length > maximum.length ||
            (text.length === maximum.length && text > maximum)) {
            throw new Error('Invalid Daily Record identity or revision. Reload the record.');
        }
        return text;
    }

    function date(value) {
        if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            throw new Error('Invalid Daily Record date.');
        }
        const year = Number(value.slice(0, 4));
        const month = Number(value.slice(5, 7));
        const day = Number(value.slice(8, 10));
        const leap = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);
        const days = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        if (year < 1 || month < 1 || month > 12 || day < 1 || day > days[month - 1]) {
            throw new Error('Invalid Daily Record date.');
        }
        return value;
    }

    function cycle(value) {
        return value === null || value === '' ? '' : decimal(value, maxId);
    }

    function normalizeScope(type, raw) {
        if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
            throw new Error('Missing Daily Record selection.');
        }
        const animal = raw.animal_type === null || raw.animal_type === undefined
            ? '' : raw.animal_type;
        if (typeof animal !== 'string') {
            throw new Error('Invalid Daily Record species.');
        }
        const species = animal.trim().toLowerCase();
        if ((type === 'ruminant' && (!species || species.length > 100)) ||
            (type !== 'ruminant' && species !== '')) {
            throw new Error('Invalid Daily Record species.');
        }
        return Object.freeze({
            record_date: date(raw.record_date),
            cycle_id: cycle(raw.cycle_id),
            animal_type: species
        });
    }

    function sameScope(a, b) {
        return a.record_date === b.record_date &&
            a.cycle_id === b.cycle_id && a.animal_type === b.animal_type;
    }

    function controls(form) {
        const found = {};
        names.forEach(name => {
            const matches = form.querySelectorAll('input[type="hidden"][name="' + name + '"]');
            if (matches.length !== 1 || typeof matches[0].value !== 'string') {
                throw new Error('Daily Record form token controls are missing or duplicated.');
            }
            found[name] = matches[0];
        });
        return found;
    }

    function createController({ form, type, readScope }) {
        if (!types.includes(type) || !form || typeof form.querySelectorAll !== 'function' ||
            typeof readScope !== 'function') {
            throw new Error('Invalid Daily Record modal controller setup.');
        }
        // Fail closed immediately if the future modal has incomplete hidden inputs.
        controls(form);
        let epoch = 0;
        let active = null;
        let expected = null;
        let phase = 'pending';

        function write(values) {
            const fields = controls(form);
            names.forEach(name => { fields[name].value = values[name] || ''; });
        }

        function invalidate() {
            epoch += 1;
            active = null;
            expected = null;
            phase = 'pending';
            write({});
        }

        function begin() {
            invalidate(); // Old requests lose authority BEFORE the new scope is read.
            const selected = normalizeScope(type, readScope());
            active = Object.freeze({ epoch, scope: selected });
            return active;
        }

        function isCurrent(ticket) {
            if (ticket !== active || active === null) return false;
            try {
                return sameScope(active.scope, normalizeScope(type, readScope()));
            } catch (_) {
                return false;
            }
        }

        function reject(ticket) {
            if (!isCurrent(ticket)) return false;
            invalidate();
            return true;
        }

        function allowCreate(ticket) {
            if (!isCurrent(ticket) || phase !== 'pending') return false;
            expected = Object.freeze({
                write_intent: 'create', record_id: '', expected_revision: '',
                original_cycle_id: '', original_record_date: '', original_animal_type: ''
            });
            write(expected);
            phase = 'ready';
            return true;
        }

        function loadEdit(ticket, tokens) {
            if (!isCurrent(ticket) || phase !== 'pending') return false;
            try {
                if (!tokens || typeof tokens !== 'object' || Array.isArray(tokens) ||
                    tokens.write_intent !== 'edit' || tokens.is_population_activity_only ||
                    !names.every(name => Object.prototype.hasOwnProperty.call(tokens, name))) {
                    throw new Error('Incomplete Daily Record edit snapshot.');
                }
                const rowId = decimal(tokens.record_id, maxId);
                const revision = decimal(tokens.expected_revision, maxRevision);
                const original = normalizeScope(type, {
                    record_date: tokens.original_record_date,
                    cycle_id: tokens.original_cycle_id,
                    animal_type: tokens.original_animal_type
                });
                if (!sameScope(ticket.scope, original)) {
                    throw new Error('Daily Record identity changed. Reload it.');
                }
                expected = Object.freeze({
                    write_intent: 'edit', record_id: rowId, expected_revision: revision,
                    original_cycle_id: original.cycle_id,
                    original_record_date: original.record_date,
                    original_animal_type: original.animal_type
                });
                write(expected);
                phase = 'ready';
                return true;
            } catch (_) {
                invalidate(); // Never fall back to CREATE after invalid EDIT data.
                return false;
            }
        }

        function isReady() {
            if (phase === 'pending') return false;
            if (!expected || !isCurrent(active)) {
                invalidate();
                return false;
            }
            try {
                const currentFields = controls(form);
                if (!names.every(name => currentFields[name].value === expected[name])) {
                    invalidate();
                    return false;
                }
                return true;
            } catch (_) {
                // Even if controls are removed, the submit guard must fail.
                return false;
            }
        }

        invalidate();
        return Object.freeze({ begin, isCurrent, allowCreate, loadEdit, reject, invalidate, isReady });
    }

    global.ReneeDailyRecordRevisionModal = Object.freeze({ createController });
})(typeof window !== 'undefined' ? window : this);
