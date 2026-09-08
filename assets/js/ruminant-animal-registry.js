/**
 * Ruminant Animal Registry page behavior.
 * Externalized for CSP compatibility.
 */
function newAnimal(){const f=document.querySelector('#animalModal form');f.reset();document.getElementById('animal_id').value='';const sp=document.getElementById('species');sp.disabled=false;const hs=document.getElementById('locked_species_value');if(hs)hs.remove();const h=document.getElementById('species_lock_help');if(h)h.textContent='';document.getElementById('status').value='Active';bootstrap.Modal.getOrCreateInstance(document.getElementById('animalModal')).show();}
function exitAnimal(a){document.getElementById('exit_animal_id').value=a.id;document.getElementById('exit_animal_tag').value=a.tag_no;bootstrap.Modal.getOrCreateInstance(document.getElementById('exitModal')).show();}
function editAnimal(a){const sp=document.getElementById('species');if(sp){sp.disabled=!!a.has_history;let h=document.getElementById('species_lock_help');if(!h){h=document.createElement('div');h.id='species_lock_help';h.className='form-text';sp.parentNode.appendChild(h);}h.textContent=a.has_history?'Species is locked because this animal already has operational/economic history.':'';}for(const k of ['id','tag_no','species','sex','breed','birth_date','purchase_date','purchase_cost','source','notes']){const e=document.getElementById('animal_'+k)||document.getElementById(k);if(e)e.value=a[k]??'';}let hs=document.getElementById('locked_species_value');if(hs)hs.remove();if(a.has_history){hs=document.createElement('input');hs.type='hidden';hs.name='species';hs.id='locked_species_value';hs.value=a.species;sp.form.appendChild(hs);}bootstrap.Modal.getOrCreateInstance(document.getElementById('animalModal')).show();}

document.addEventListener('DOMContentLoaded', function () {
    const config = document.querySelector('[data-ruminant-registry-edit-animal]');

    if (!config) {
        return;
    }

    const encoded = config.dataset.ruminantRegistryEditAnimal;

    if (!encoded) {
        return;
    }

    try {
        editAnimal(JSON.parse(encoded));
    } catch (error) {
        console.error('Unable to parse initial ruminant animal edit data.', error);
    }
});
