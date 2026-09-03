(function(){
'use strict';
if(window.AppConfirm) return;
let active=null;
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
function ask(message,opts={}){
 if(active){active.finish(false);}
 const title=opts.title||'Please confirm', confirmText=opts.confirmText||'Continue', tone=opts.tone||(opts.danger===false?'primary':'danger');
 const cls=tone==='danger'?'app-confirm-danger':(tone==='warning'?'app-confirm-warning':'app-confirm-primary');
 const context=opts.context?`<div class="app-confirm-context">${esc(opts.context)}</div>`:'';
 const el=document.createElement('div'); el.className='app-confirm-backdrop';
 el.innerHTML=`<div class="app-confirm-card" role="dialog" aria-modal="true" aria-labelledby="appConfirmTitle"><div class="app-confirm-head"><div class="app-confirm-icon"><i class="bi bi-exclamation-triangle-fill"></i></div><div class="app-confirm-title" id="appConfirmTitle">${esc(title)}</div></div><div class="app-confirm-body">${esc(message)}${context}</div><div class="app-confirm-actions"><button type="button" class="app-confirm-btn app-confirm-cancel" data-confirm-cancel>${esc(opts.cancelText||'Cancel')}</button><button type="button" class="app-confirm-btn ${cls}" data-confirm-ok>${esc(confirmText)}</button></div></div>`;
 document.body.appendChild(el); const ok=el.querySelector('[data-confirm-ok]'), cancel=el.querySelector('[data-confirm-cancel]');
 let resolvePromise; const promise=new Promise(resolve=>resolvePromise=resolve);
 const previous=document.activeElement;
 const finish=value=>{if(!active||active.el!==el)return; document.removeEventListener('keydown',key); active=null; el.remove(); if(previous&&previous.focus)previous.focus(); resolvePromise(value);};
 const key=e=>{if(e.key==='Escape')finish(false);}; active={el,finish};
 ok.addEventListener('click',()=>finish(true)); cancel.addEventListener('click',()=>finish(false)); el.addEventListener('click',e=>{if(e.target===el)finish(false)}); document.addEventListener('keydown',key); setTimeout(()=>ok.focus(),20); return promise;
}
async function submit(form,message,opts={}){const yes=await ask(message,opts);if(!yes)return false;form.dataset.confirmed='1';const submitter=opts.submitter;if(submitter&&submitter.name){const h=document.createElement('input');h.type='hidden';h.name=submitter.name;h.value=submitter.value||'';form.appendChild(h);}form.submit();return false;}
document.addEventListener('submit',function(e){const form=e.target.closest('form[data-confirm]');if(!form||form.dataset.confirmed==='1')return;e.preventDefault();submit(form,form.getAttribute('data-confirm'),{title:form.getAttribute('data-confirm-title')||'Please confirm',confirmText:form.getAttribute('data-confirm-button')||'Confirm',cancelText:form.getAttribute('data-confirm-cancel')||'Cancel',tone:form.getAttribute('data-confirm-tone')||(form.getAttribute('data-confirm-danger')==='false'?'primary':'danger'),context:form.getAttribute('data-confirm-context')||'',submitter:e.submitter||null});},true);
window.AppConfirm={ask,submit};
})();
