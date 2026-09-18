
function gcToast(msg, tipo = 'ok') {
    const t = document.getElementById('gc-toast');
    const m = document.getElementById('gc-toast-msg');
    if (!t || !m) return;
    m.textContent = (tipo === 'ok' ? '✅ ' : '❌ ') + msg;
    t.classList.remove('translate-y-16','opacity-0');
    t.classList.add('translate-y-0','opacity-100');
    setTimeout(() => { t.classList.add('translate-y-16','opacity-0'); t.classList.remove('translate-y-0','opacity-100'); }, 3500);
}
function openModal(id) { const m = document.getElementById(id); if (!m) return; m.classList.remove('opacity-0','pointer-events-none'); m.classList.add('opacity-100'); }
function closeModal(id) { const m = document.getElementById(id); if (!m) return; m.classList.add('opacity-0','pointer-events-none'); m.classList.remove('opacity-100'); }
function closeModalOut(e, id) { if (e.target.id === id) closeModal(id); }
function confirmar(msg, cb) { if (confirm(msg)) cb(); }
function formatPEN(v) { return 'S/ ' + parseFloat(v||0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('[id^="modal"]').forEach(m => { m.classList.add('opacity-0','pointer-events-none'); m.classList.remove('opacity-100'); }); });
