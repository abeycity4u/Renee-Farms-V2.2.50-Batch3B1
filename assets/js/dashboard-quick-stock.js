/**
 * Dashboard permitted quick-stock interaction behavior.
 * Externalized for CSP compatibility.
 */
document.addEventListener('DOMContentLoaded',function(){
  const form=document.getElementById('quickStockForm');
  if(!form)return;

  document.querySelectorAll('#stockTable button[onclick^="quickStockUpdate("]').forEach(function(btn){
    btn.title='Quick Stock Use';
    btn.innerHTML='<i class="bi bi-dash-circle me-1"></i> Use Stock';
  });

  form.addEventListener('submit',async function(event){
    event.preventDefault();
    event.stopImmediatePropagation();

    const submitBtn=form.querySelector('button[type="submit"]');
    if(!submitBtn)return;
    const originalText=submitBtn.innerHTML;
    submitBtn.innerHTML='<span class="spinner-border spinner-border-sm"></span> Deducting...';
    submitBtn.disabled=true;

    try {
      const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
      const transactionDate=document.querySelector('meta[name="app-today"]')?.content||'';
      const payload={
        item_id:document.getElementById('stockItemId')?.value||'',
        type:'used',
        quantity:document.getElementById('quantity')?.value||'',
        remarks:document.getElementById('remarks')?.value||''
      };
      if(transactionDate)payload.transaction_date=transactionDate;

      const response=await fetch('api/update_stock.php',{
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},
        body:JSON.stringify(payload)
      });
      const data=await response.json();
      if(response.ok&&data.success){
        showAlert('success',data.message||'Stock deducted successfully!');
        bootstrap.Modal.getInstance(document.getElementById('quickStockModal'))?.hide();
        setTimeout(function(){location.reload();},1000);
        return;
      }
      showAlert('danger','Error: '+(data.error||data.message||'Action could not be completed.'));
    } catch(error) {
      showAlert('danger','Network error: '+error.message);
    }

    submitBtn.innerHTML=originalText;
    submitBtn.disabled=false;
  },true);
});
