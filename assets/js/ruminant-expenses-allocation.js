/**
 * Ruminant Expenses allocation behavior.
 * Externalized for CSP compatibility.
 */
window.RuminantExpensesAllocationConfig = {
    cycles: [],
    animals: []
};

(function () {
    const config = document.getElementById('ruminantExpensesAllocationConfig');

    if (!config) {
        return;
    }

    try {
        window.RuminantExpensesAllocationConfig = {
            cycles: JSON.parse(config.dataset.cycles || '[]'),
            animals: JSON.parse(config.dataset.animals || '[]')
        };
    } catch (error) {
        console.error('Unable to parse ruminant expense allocation data.', error);
    }
})();

const ruminantExpenseCycles = window.RuminantExpensesAllocationConfig.cycles;
    const ruminantExpenseAnimals = window.RuminantExpensesAllocationConfig.animals;
    function refreshRuminantExpenseCycles() {
        const p=document.getElementById('ruminantExpenseProduction');
        const c=document.getElementById('ruminantExpenseCycle');
        if(!p||!c) return;
        c.innerHTML='<option value="0">Shared between ruminant cycles</option>';
        ruminantExpenseCycles.filter(x => x.production_type===p.value).forEach(x => c.add(new Option(`${x.cycle_code} — ${x.status}`, String(x.id))));
    }
    document.addEventListener('DOMContentLoaded',()=>{ refreshRuminantExpenseCycles(); document.getElementById('ruminantExpenseProduction')?.addEventListener('change',refreshRuminantExpenseCycles); });
    function refreshEditRuminantExpenseCycles(selectedCycle = 0) {
        const p=document.getElementById('editExpenseProduction');
        const c=document.getElementById('editExpenseCycle');
        if(!p||!c) return;
        c.innerHTML='<option value="0">Shared between ruminant cycles</option>';
        ruminantExpenseCycles.filter(x => x.production_type===p.value).forEach(x => c.add(new Option(`${x.cycle_code} — ${x.status}`, String(x.id))));
        c.value = String(selectedCycle || 0);
        if (c.value !== String(selectedCycle || 0)) c.value = '0';
    }
    document.addEventListener('DOMContentLoaded',()=>{ document.getElementById('editExpenseProduction')?.addEventListener('change',()=>{refreshEditRuminantExpenseCycles(0); renderAnimalAllocation('edit');}); });

    function expenseTotal(prefix) {
        const amount = parseFloat(document.querySelector(prefix==='add' ? '#addExpenseModal [name=amount]' : '#editAmount')?.value || '0');
        const unit = parseFloat(document.querySelector(prefix==='add' ? '#addExpenseModal [name=unit]' : '#editUnit')?.value || '0');
        return Math.round(amount * unit * 100) / 100;
    }
    function renderAnimalAllocation(prefix, selectedRows = null) {
        const modeEl=document.getElementById(prefix==='add'?'addAnimalAllocationMode':'editAnimalAllocationMode');
        const panel=document.getElementById(prefix==='add'?'addAnimalAllocationPanel':'editAnimalAllocationPanel');
        const production=document.getElementById(prefix==='add'?'ruminantExpenseProduction':'editExpenseProduction');
        if(!modeEl||!panel||!production) return;
        const mode=modeEl.value;
        if(mode==='herd'){panel.classList.add('d-none');panel.replaceChildren();return;}
        panel.classList.remove('d-none');
        const selectedMap={};
        (selectedRows||[]).forEach(r=>selectedMap[String(r.animal_id)] = r);
        const existingChecked={};
        panel.querySelectorAll('input[type=checkbox]:checked').forEach(x=>existingChecked[x.value]=true);
        const existingAmounts={};
        panel.querySelectorAll('[data-animal-amount]').forEach(x=>existingAmounts[x.dataset.animalAmount]=x.value);
        const animals=ruminantExpenseAnimals.filter(a=>a.species===production.value);

        panel.replaceChildren();

        if(!animals.length){
            const empty=document.createElement('div');
            empty.className='small text-muted';
            empty.textContent='No registered animals match this production type.';
            panel.appendChild(empty);
            return;
        }

        const total=expenseTotal(prefix);

        const heading=document.createElement('div');
        heading.className='small fw-semibold mb-2';
        heading.textContent='Select '+production.value.charAt(0).toUpperCase()+production.value.slice(1)+' animals';
        panel.appendChild(heading);

        animals.forEach(a=>{
            const id=String(a.id);
            const checked=!!(selectedMap[id]||existingChecked[id]);
            const old=selectedMap[id]?.allocated_amount ?? existingAmounts[id] ?? '';

            const row=document.createElement('div');
            row.className='d-flex align-items-center mb-2';

            const checkbox=document.createElement('input');
            checkbox.className='form-check-input me-2';
            checkbox.type='checkbox';
            checkbox.name='animal_ids[]';
            checkbox.value=id;
            checkbox.checked=checked;
            row.appendChild(checkbox);

            const info=document.createElement('div');
            info.className='flex-grow-1';

            const tag=document.createElement('strong');
            tag.textContent=String(a.tag_no ?? '');
            info.appendChild(tag);
            info.appendChild(document.createTextNode(' '));

            const status=document.createElement('span');
            status.className='text-muted small';
            status.textContent=String(a.status ?? '');
            info.appendChild(status);

            row.appendChild(info);

            if(mode==='custom'){
                const amount=document.createElement('input');
                amount.type='number';
                amount.min='0';
                amount.step='0.01';
                amount.className='form-control form-control-sm ms-2';
                amount.style.maxWidth='150px';
                amount.name='animal_amounts['+id+']';
                amount.dataset.animalAmount=id;
                amount.value=String(old);
                amount.placeholder='₦ allocation';
                row.appendChild(amount);
            }

            panel.appendChild(row);
        });

        if(mode==='equal'){
            const equal=document.createElement('div');
            equal.className='small text-muted mt-2';
            equal.textContent='Equal split will be calculated from the expense total of ₦'
                + total.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})
                + '.';
            panel.appendChild(equal);
        }
    }
    function initAnimalAllocation(prefix){
        const mode=document.getElementById(prefix==='add'?'addAnimalAllocationMode':'editAnimalAllocationMode');
        const production=document.getElementById(prefix==='add'?'ruminantExpenseProduction':'editExpenseProduction');
        mode?.addEventListener('change',()=>renderAnimalAllocation(prefix));
        production?.addEventListener('change',()=>renderAnimalAllocation(prefix));
        const scope=prefix==='add'?document.getElementById('addExpenseModal'):document.getElementById('editExpenseModal');
        scope?.querySelectorAll('[name=amount],[name=unit]').forEach(el=>el.addEventListener('input',()=>{if(mode?.value==='equal')renderAnimalAllocation(prefix);}));
    }
    document.addEventListener('DOMContentLoaded',()=>{initAnimalAllocation('add');initAnimalAllocation('edit');});
