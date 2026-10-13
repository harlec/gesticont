/* GestiCont — comportamiento común del rediseño: aviso (toast), tamaño de letra y selector de empresa (Ctrl+K). */

function gcToast(msg, tipo) {
    var t = document.getElementById('gc-toast');
    if (!t) return;
    t.textContent = (tipo === 'error' ? '⚠ ' : '') + msg;
    t.style.background = tipo === 'error' ? 'var(--gc-neg-action)' : 'var(--gc-ink)';
    t.classList.add('is-on');
    clearTimeout(t._h);
    t._h = setTimeout(function () { t.classList.remove('is-on'); }, 2600);
}
function formatPEN(v) { return 'S/ ' + parseFloat(v || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
function openModal(id)  { var m = document.getElementById(id); if (m) m.classList.add('is-open'); }
function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('is-open'); }
function closeModalOut(e, id) { if (e.target.id === id) closeModal(id); }

/* ───── Tamaño de letra: Normal 1 · Grande 1.12 · Muy grande 1.25 (se guarda en cookie y en el navegador) ───── */
function gcLetra(z) {
    var app = document.getElementById('gc-app');
    if (app) app.style.setProperty('--gc-zoom', z);
    try { localStorage.setItem('gc_letra', z); } catch (e) {}
    document.cookie = 'gc_letra=' + z + ';path=/;max-age=31536000;SameSite=Lax';
    document.querySelectorAll('.gc-letra button').forEach(function (b) { b.classList.toggle('is-active', parseFloat(b.dataset.z) === parseFloat(z)); });
}

/* ───── Selector de empresa (Ctrl/Cmd + K) ───── */
(function () {
    var back = document.getElementById('gc-switcher');
    if (!back) return;
    var data = window.GC_EMPRESAS || [], actual = window.GC_EMPRESA_ACTUAL || 0;
    var input = document.getElementById('gc-sw-q'), list = document.getElementById('gc-sw-list'), chips = back.querySelectorAll('[data-f]');
    var filtro = 'todas', sel = 0, vista = [];
    var norm = function (s) { return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };

    function filtrar() {
        var q = norm(input.value);
        vista = data.filter(function (e) {
            if (filtro === 'vencidos' && !(e.vencidos > 0)) return false;
            if (filtro === 'clasificar' && !(e.por_clasificar > 0)) return false;
            return !q || norm(e.nombre).indexOf(q) >= 0 || e.ruc.indexOf(q) >= 0;
        });
        sel = Math.min(sel, Math.max(vista.length - 1, 0));
        pintar();
    }
    function pintar() {
        chips.forEach(function (c) {
            var n = c.dataset.f === 'todas' ? data.length : data.filter(function (e) { return c.dataset.f === 'vencidos' ? e.vencidos > 0 : e.por_clasificar > 0; }).length;
            c.querySelector('i').textContent = n; c.classList.toggle('is-active', c.dataset.f === filtro);
        });
        list.innerHTML = '';
        if (!vista.length) { var v = document.createElement('div'); v.className = 'gc-empty'; v.textContent = 'No hay empresas con ese nombre o RUC.'; list.appendChild(v); return; }
        vista.forEach(function (e, i) {
            var a = document.createElement('button'); a.type = 'button'; a.className = 'gc-sw-row' + (i === sel ? ' is-sel' : '');
            var izq = document.createElement('span'); izq.className = 'izq';
            var n = document.createElement('b'); n.textContent = e.nombre + (e.id === actual ? ' · actual' : ''); izq.appendChild(n);
            var r = document.createElement('span'); r.className = 'mono'; r.textContent = 'RUC ' + e.ruc; izq.appendChild(r);
            var der = document.createElement('span'); der.className = 'der';
            var ch = document.createElement('span'); ch.className = 'gc-chip ' + (e.tono || 'ok'); ch.textContent = e.texto; der.appendChild(ch);
            if (e.por_clasificar > 0) { var c = document.createElement('small'); c.textContent = e.por_clasificar + ' por clasificar'; der.appendChild(c); }
            a.appendChild(izq); a.appendChild(der);
            a.onmouseenter = function () { sel = i; marcar(); };
            a.onclick = function () { ir(e); };
            list.appendChild(a);
        });
    }
    function marcar() { list.querySelectorAll('.gc-sw-row').forEach(function (el, i) { el.classList.toggle('is-sel', i === sel); }); }
    function ir(e) {
        if (e.id === actual) { cerrar(); return; }
        try { sessionStorage.setItem('gc_toast', 'Ahora trabajas en ' + e.nombre); } catch (x) {}
        location.href = '/empresas/' + e.id;
    }
    function abrir() { back.classList.add('is-open'); input.value = ''; filtro = 'todas'; sel = 0; filtrar(); setTimeout(function () { input.focus(); }, 30); }
    function cerrar() { back.classList.remove('is-open'); }
    window.gcAbrirSelector = abrir; window.gcCerrarSelector = cerrar;

    input.addEventListener('input', function () { sel = 0; filtrar(); });
    chips.forEach(function (c) { c.onclick = function () { filtro = c.dataset.f; sel = 0; filtrar(); input.focus(); }; });
    back.addEventListener('mousedown', function (e) { if (e.target === back) cerrar(); });
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); back.classList.contains('is-open') ? cerrar() : abrir(); return; }
        if (!back.classList.contains('is-open')) return;
        if (e.key === 'Escape') { cerrar(); }
        else if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(sel + 1, vista.length - 1); marcar(); list.querySelectorAll('.gc-sw-row')[sel].scrollIntoView({ block: 'nearest' }); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(sel - 1, 0); marcar(); list.querySelectorAll('.gc-sw-row')[sel].scrollIntoView({ block: 'nearest' }); }
        else if (e.key === 'Enter' && vista[sel]) { e.preventDefault(); ir(vista[sel]); }
    });
})();

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') document.querySelectorAll('.gc-modal-back.is-open').forEach(function (m) { if (m.id !== 'gc-switcher') m.classList.remove('is-open'); });
});
document.addEventListener('DOMContentLoaded', function () {
    try { var m = sessionStorage.getItem('gc_toast'); if (m) { sessionStorage.removeItem('gc_toast'); gcToast(m); } } catch (e) {}
});
