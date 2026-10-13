(function(){
 'use strict';
 const btn=document.getElementById('mobileMenuBtn'),sidebar=document.getElementById('sidebar'),overlay=document.getElementById('sidebarOverlay');
 function closeMenu(){if(sidebar)sidebar.classList.remove('is-open');if(overlay)overlay.classList.remove('show');}
 if(btn)btn.addEventListener('click',()=>{if(sidebar)sidebar.classList.toggle('is-open');if(overlay)overlay.classList.toggle('show');});
 if(overlay)overlay.addEventListener('click',closeMenu);
 if(sidebar)sidebar.querySelectorAll('a').forEach(a=>a.addEventListener('click',closeMenu));
 const pkg=document.getElementById('package'),weight=document.getElementById('weight'),dateIn=document.getElementById('dateIn'),deadline=document.getElementById('deadlinePreview'),total=document.getElementById('totalPreview'),ratePreview=document.getElementById('ratePreview');
 const rupiah=n=>'Rp '+new Intl.NumberFormat('id-ID',{maximumFractionDigits:0}).format(n);
 function update(){if(!pkg||!weight)return;const opt=pkg.options[pkg.selectedIndex],rate=Number(opt.dataset.rate||7000),hours=Number(opt.dataset.hours||48),kg=Number(weight.value||0);if(total)total.textContent=rupiah(Math.round(rate*kg));if(ratePreview)ratePreview.textContent=rupiah(rate)+' per kg';if(dateIn&&dateIn.value&&deadline){const d=new Date(dateIn.value);if(!Number.isNaN(d.getTime())){d.setHours(d.getHours()+hours);deadline.value=new Date(d.getTime()-d.getTimezoneOffset()*60000).toISOString().slice(0,16);}}}
 if(pkg)pkg.addEventListener('change',update);if(weight)weight.addEventListener('input',update);if(dateIn)dateIn.addEventListener('change',update);update();
})();
