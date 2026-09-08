/**
 * Poultry Health page behavior.
 * Externalized for CSP compatibility.
 */
window.PoultryHealthConfig = window.PoultryHealthConfig || {
    defaultDate: '',
    canDelete: false
};


(function () {
    const config = document.getElementById('poultryHealthConfig');

    if (!config) {
        return;
    }

    window.PoultryHealthConfig = {
        defaultDate: config.dataset.defaultDate || '',
        canDelete: config.dataset.canDelete === '1'
    };
})();

function filterCycleOptions(){
  const type=document.getElementById('production_type').value;
  const select=document.getElementById('cycle_id');
  let first=null;
  [...select.options].forEach(o=>{ const show=o.dataset.productionType===type; o.hidden=!show; o.disabled=!show; if(show && first===null) first=o; });
  if(select.selectedOptions.length===0 || select.selectedOptions[0].disabled){ if(first) first.selected=true; }
}
function newHealthEvent(){
  document.getElementById('eventForm').reset(); document.getElementById('event_id').value='0'; document.getElementById('eventModalTitle').textContent='Record Poultry Health Event';
  document.getElementById('event_date').value = window.PoultryHealthConfig.defaultDate || '';
  filterCycleOptions();
}
function editHealthEvent(e){
  document.getElementById('eventModalTitle').textContent='Edit Poultry Health Event';
  document.getElementById('event_id').value=e.id||0; document.getElementById('production_type').value=e.production_type||'layer'; filterCycleOptions();
  document.getElementById('cycle_id').value=e.cycle_id||''; document.getElementById('event_date').value=e.event_date||''; document.getElementById('event_type').value=e.event_type||'other';
  document.getElementById('product_name').value=e.product_name||''; document.getElementById('dosage').value=e.dosage||''; document.getElementById('reason_symptoms').value=e.reason_symptoms||''; document.getElementById('notes').value=e.notes||''; document.getElementById('stock_item_id').value=e.stock_item_id||'0';
}

async function confirmDeleteEvent(id){
  if (!window.PoultryHealthConfig.canDelete) {
    return;
  }

  const deleteId = document.getElementById('delete_event_id');
  const deleteForm = document.getElementById('deleteEventForm');

  if (!deleteId || !deleteForm) {
    return;
  }

  const confirmed = await AppConfirm.ask(
    'Delete this poultry health event? This removes the structured clinical/history record only; linked Inventory transactions are not changed.',
    {title:'Delete health event?', confirmText:'Delete', danger:true}
  );

  if (confirmed) {
    deleteId.value = id;
    deleteForm.submit();
  }
}

document.addEventListener('DOMContentLoaded',filterCycleOptions);
