/**
 * Dashboard stock controls for read-only inventory access.
 * Externalized for CSP compatibility.
 */
document.addEventListener('DOMContentLoaded',function(){
  const table=document.getElementById('stockTable');
  if(!table)return;
  table.querySelectorAll('button[onclick^="quickStockUpdate("]').forEach(function(btn){btn.remove();});
  const headers=Array.from(table.querySelectorAll('thead th'));
  const actionIndex=headers.findIndex(function(th){return th.textContent.trim().toLowerCase()==='actions';});
  if(actionIndex<0)return;
  table.querySelectorAll('tr').forEach(function(row){
    const cells=row.children;
    if(cells[actionIndex])cells[actionIndex].remove();
  });
});
