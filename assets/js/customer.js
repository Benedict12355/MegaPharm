// assets/js/customer.js
function openProductModal() { document.getElementById('productModal')?.classList.add('show'); }
function closeProductModal() { document.getElementById('productModal')?.classList.remove('show'); }

document.addEventListener('click', e => {
  if (e.target.classList.contains('cust-modal-overlay')) e.target.classList.remove('show');
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.cust-modal-overlay.show').forEach(m => m.classList.remove('show'));
});

function qtyStep(id, delta) {
  const input = document.getElementById(id);
  if (!input) return;
  const min = parseInt(input.min || 1, 10);
  const val = Math.max(min, (parseInt(input.value, 10) || 1) + delta);
  input.value = val;
}