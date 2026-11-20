document.addEventListener('DOMContentLoaded',()=>{
 const login=document.getElementById('loginForm');
 if(login) login.addEventListener('submit',function(ev){
   const username=this.querySelector('[name="identity"]').value.trim();
   const password=this.querySelector('[name="password"]').value;
   if(username==='' || password===''){ ev.preventDefault(); alert('Username/email dan password wajib diisi.'); }
   // Autentikasi sebenarnya tetap dilakukan oleh PHP dan database.
 });
 const reg=document.getElementById('registerForm');
 if(reg) reg.addEventListener('submit',function(ev){
   const p=this.querySelector('[name="password"]').value, c=this.querySelector('[name="confirm_password"]').value;
   if(p.length<8){ev.preventDefault();alert('Password minimal 8 karakter.');}
   else if(p!==c){ev.preventDefault();alert('Konfirmasi password tidak sama.');}
 });
});