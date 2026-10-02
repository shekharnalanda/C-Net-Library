<script>
(function(){
 document.querySelectorAll('form').forEach(form=>{
  const slot=form.querySelector('select[name="study_slot_id"]'),start=form.querySelector('input[name="start_time"]'),end=form.querySelector('input[name="end_time"]');
  if(!slot||!start||!end) return;
  function sync(reset){const option=slot.selectedOptions[0],full=option?.dataset.full==='1';start.disabled=end.disabled=full;start.required=!full;end.readOnly=true;
   if(full){start.value=end.value='';return;}
   if(reset || !start.value) start.value=option?.dataset.start||'';
   if(start.value&&option?.dataset.duration){const [h,m]=start.value.split(':').map(Number);const minutes=(h*60+m+Math.round(Number(option.dataset.duration)*60))%1440;end.value=String(Math.floor(minutes/60)).padStart(2,'0')+':'+String(minutes%60).padStart(2,'0');}else end.value='';
  }
  slot.addEventListener('change',()=>sync(true));start.addEventListener('input',()=>sync(false));start.addEventListener('change',()=>sync(false));sync(false);
 });
})();
</script>