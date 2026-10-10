// assets/js/app.js
function openModal(id){document.getElementById(id)?.classList.add('show');}
function closeModal(id){document.getElementById(id)?.classList.remove('show');}
function confirmLogout(e){e.preventDefault();openModal('logoutModal');}

document.addEventListener('click', e=>{
  if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('show');
});
document.addEventListener('keydown', e=>{
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.show').forEach(m=>m.classList.remove('show'));
});

// Filter pills + tabs (visual only by default; subpages may override)
document.querySelectorAll('.pill').forEach(p=>{
  p.addEventListener('click', ()=>{
    const group = p.parentElement;
    group.querySelectorAll('.pill').forEach(x=>x.classList.remove('active'));
    p.classList.add('active');
  });
});