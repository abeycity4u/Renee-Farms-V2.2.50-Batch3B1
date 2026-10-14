/*
 * B12 Daily Record derived-field parity, SOURCE ONLY and NOT LOADED.
 *
 * Shared display behavior from legacy Layer and Ruminant record editors:
 * layer egg crates = eggs/30; laying rate = 100*eggs/opening stock
 * (two decimals); Ruminant feed item selection updates unit and its label.
 * No pricing, inventory changes, record identity, fetch or form submission.
 *
 * Call bind() only from a future single-owner B12 page adapter after the
 * legacy page controller has been fully retired. This module NEVER auto-binds.
 */
(function (global) {
    'use strict';

    function number(value) {
        const clean=String(value==null?'':value).replace(/,/g,'').trim();
        const amount=Number.parseFloat(clean);
        return Number.isFinite(amount) && amount >= 0 ? amount : 0;
    }

    function createFields(options) {
        if (!options || !['layer','broiler','ruminant'].includes(options.type) ||
            !options.form || typeof options.form.querySelector!=='function') {
            throw new Error('Incomplete B12 Daily Record derived-field configuration.');
        }
        const get=id=>options.form.querySelector('#'+id);
        let bound=false;
        const actions=[];

        function requireControl(id) {
            const field=get(id);
            if (!field) throw new Error('Missing B12 Daily Record control: '+id);
            return field;
        }

        function computeLayer() {
            if (options.type!=='layer') return;
            const opening=number(requireControl('openingStock').value);
            const eggs=number(requireControl('eggProduction').value);
            requireControl('cratesCount').value=(eggs/30).toFixed(2);
            // A zero/empty opening stock cannot retain an old date's rate.
            requireControl('layingRate').value=opening>0
                ? ((eggs/opening)*100).toFixed(2):'';
        }

        function feedUnit(record=null) {
            if (options.type!=='ruminant') return;
            const item=requireControl('feedItemId');
            const unitInput=requireControl('feedConsumptionUnit');
            const label=options.document &&
                typeof options.document.getElementById==='function'
                    ? options.document.getElementById('feedConsumptionLabel') : null;
            if (!label || !('textContent' in label)) {
                throw new Error('Missing B12 Ruminant feed consumption label.');
            }

            // In EDIT mode retain the persisted record's unit; changing the
            // selected item will instead use its own option's configured unit.
            const selected=item.options && item.options[item.selectedIndex];
            const selectedUnit=selected && selected.dataset
                ? selected.dataset.unit : null;
            const preferred=record && typeof record.feed_consumption_unit==='string'
                && record.feed_consumption_unit.trim()!==''
                ? record.feed_consumption_unit.trim() : selectedUnit;
            const unit=typeof preferred==='string' && preferred.trim()!==''
                ? preferred.trim():'kg';
            // Only plain text labels. Unit values remain server-validated.
            unitInput.value=unit;
            label.textContent='Feed Consumption ('+unit+')';
        }

        function ready(readyState) {
            if (!readyState || !['create','edit'].includes(readyState.mode)) {
                throw new Error('Invalid B12 derived-field selection.');
            }
            if (options.type==='layer') computeLayer();
            if (options.type==='ruminant') {
                feedUnit(readyState.mode==='edit'?readyState.record:null);
            }
        }

        function reset() {
            if (options.type==='layer') {
                requireControl('cratesCount').value='';
                requireControl('layingRate').value='';
            }
            if (options.type==='ruminant') {
                requireControl('feedConsumptionUnit').value='kg';
                const label=options.document.getElementById('feedConsumptionLabel');
                if (label)label.textContent='Feed Consumption (kg)';
            }
        }

        function bind() {
            if(bound)return;
            // Validate all required controls before installing ANY listeners.
            if(options.type==='layer'){
                for(const id of ['openingStock','eggProduction',
                                  'cratesCount','layingRate']) {
                    const field=requireControl(id);
                    if(['openingStock','eggProduction'].includes(id) &&
                        typeof field.addEventListener!=='function') {
                        throw new Error('Invalid B12 derived input listener.');
                    }
                }
                const update=()=>computeLayer();
                actions.push([get('openingStock'),'input',update]);
                actions.push([get('eggProduction'),'input',update]);
            }
            if(options.type==='ruminant'){
                const item=requireControl('feedItemId');
                requireControl('feedConsumptionUnit');
                if(!options.document ||
                    typeof options.document.getElementById!=='function' ||
                    !options.document.getElementById('feedConsumptionLabel') ||
                    typeof item.addEventListener!=='function'){
                    throw new Error('Incomplete B12 Ruminant feed UI.');
                }
                actions.push([item,'change',()=>feedUnit()]);
            }
            bound=true;
            for(const [field,event,callback] of actions) {
                field.addEventListener(event,callback);
            }
        }
        return Object.freeze({bind,ready,reset,computeLayer,feedUnit});
    }

    global.ReneeDailyRecordRevisionPageFields=Object.freeze({createFields});
})(typeof window!=='undefined'?window:this);
