(() => {
 const root=document.documentElement;
 let saved=null;
 try{ saved=localStorage.getItem('sg-theme'); }catch(e){}
 if(saved) root.setAttribute('data-theme',saved);
 const syncIcons=()=>{
   const dark=root.getAttribute('data-theme')==='dark';
   document.querySelectorAll('[data-theme-toggle] i').forEach(i=>i.className=dark?'bi bi-sun':'bi bi-moon-stars');
 };
 syncIcons();
 document.querySelectorAll('[data-theme-toggle]').forEach(btn=>btn.addEventListener('click',()=>{
   const next=root.getAttribute('data-theme')==='dark'?'light':'dark';
   root.setAttribute('data-theme',next);
   try{ localStorage.setItem('sg-theme',next); }catch(e){}
   syncIcons();
 }));
 // Sembunyikan / tampilkan menu samping dashboard (tersimpan per browser)
 const shell=document.getElementById('dashShell');
 const sbBtn=document.querySelector('[data-sidebar-toggle]');
 if(shell && sbBtn){
   const sync=()=>{
     const hidden=shell.classList.contains('sidebar-hidden');
     sbBtn.setAttribute('aria-expanded',hidden?'false':'true');
     const i=sbBtn.querySelector('i'); if(i) i.className=hidden?'bi bi-layout-sidebar':'bi bi-layout-sidebar-inset';
   };
   sync();
   sbBtn.addEventListener('click',()=>{
     shell.classList.toggle('sidebar-hidden');
     try{ localStorage.setItem('sg-sidebar',shell.classList.contains('sidebar-hidden')?'hidden':'shown'); }catch(e){}
     sync();
   });
 }
 document.querySelectorAll('[data-toggle-password]').forEach(btn=>btn.addEventListener('click',()=>{
   const input=document.querySelector(btn.dataset.togglePassword); if(!input)return;
   input.type=input.type==='password'?'text':'password';
   btn.querySelector('i').className=input.type==='password'?'bi bi-eye':'bi bi-eye-slash';
 }));

 // Animasi muncul saat di-scroll (otomatis nonaktif jika JS/IntersectionObserver tidak tersedia)
 if('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches){
   root.classList.add('js');
   const targets=document.querySelectorAll('.info-card,.activity-card,.student-card,.memory-tile,.timeline-item,.section-heading,.stat-item,.dash-stat,.dash-panel,.sg-card,.sg-gallery-item,.about-tags');
   const io=new IntersectionObserver((es)=>es.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target);} }),{threshold:.12,rootMargin:'0px 0px -40px 0px'});
   targets.forEach((el,i)=>{ el.classList.add('reveal'); el.style.setProperty('--d',((i%4)*0.08)+'s'); io.observe(el); });
 }
})();