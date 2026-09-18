/**
 * Dashboard permitted Quick Stock Use interaction.
 *
 * dashboard.js owns item loading plus attribution UI composition.
 * This asset owns the one and only Dashboard stock mutation submit.
 *
 * It is injected only for users allowed to update Inventory stock.
 * Dashboard remains Use / Deduct only; stock receiving belongs to Inventory.
 *
 * quickStockUpdate remains the global opener contract owned by dashboard.js.
 */
document.addEventListener('DOMContentLoaded',function(){
  const form=document.getElementById('quickStockForm');
  if(!form)return;

  const config=document.getElementById('dashboardConfig');

  document.querySelectorAll(
    '#stockTable [data-quick-stock-id]'
  ).forEach(function(btn){
    btn.title='Quick Stock Use';
    btn.innerHTML=
      '<i class="bi bi-dash-circle me-1"></i> Use Stock';
  });

  form.addEventListener('submit',async function(event){
    event.preventDefault();
    event.stopImmediatePropagation();

    const submitBtn=form.querySelector('button[type="submit"]');
    if(!submitBtn)return;

    const itemId=
      document.getElementById('stockItemId')?.value||'';

    const quantity=
      document.getElementById('quantity')?.value||'';

    const remarks=
      document.getElementById('remarks')?.value||'';

    const feedCategory=
      String(
        form.dataset.feedCategory
        || 'general'
      ).toLowerCase();

    const productionType=
      document.getElementById(
        'quickStockProductionType'
      )?.value||'';

    const cycleId=
      document.getElementById(
        'quickStockCycleId'
      )?.value||'';

    const csrf=
      config?.dataset.csrfToken||'';

    const transactionDate=
      config?.dataset.today||'';

    const payload={
      item_id:itemId,
      type:'used',
      quantity:quantity,
      remarks:remarks
    };

    /*
     * General / Non-feed stock may carry a concrete production
     * owner and optional direct cycle. Feed ownership remains
     * canonical to its feed category / Daily Record pathways.
     */
    if(
      feedCategory==='general'
      &&
      productionType
    ){
      payload.production_type=productionType;
    }

    if(
      feedCategory==='general'
      &&
      cycleId
    ){
      payload.cycle_id=cycleId;
    }

    if(transactionDate){
      payload.transaction_date=transactionDate;
    }

    const originalText=submitBtn.innerHTML;

    submitBtn.innerHTML=
      '<span class="spinner-border spinner-border-sm"></span> Deducting...';

    submitBtn.disabled=true;

    try {
      const response=await fetch(
        'api/update_stock.php',
        {
          method:'POST',
          headers:{
            'Content-Type':'application/json',
            'X-CSRF-Token':csrf
          },
          body:JSON.stringify(payload)
        }
      );

      const data=await response.json();

      if(response.ok&&data.success){
        showAlert('success',data.message||'Stock deducted successfully!');

        bootstrap.Modal
          .getInstance(
            document.getElementById('quickStockModal')
          )
          ?.hide();

        setTimeout(function(){
          location.reload();
        },1000);

        return;
      }

      showAlert(
        'danger',
        'Error: '
        +(data.error||data.message||'Action could not be completed.')
      );

    } catch(error) {
      showAlert(
        'danger',
        'Network error: '+error.message
      );
    }

    submitBtn.innerHTML=originalText;
    submitBtn.disabled=false;
  },true);
});
