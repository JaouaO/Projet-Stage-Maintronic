;(() => { /* ... */ })();

// ===== DEBUG CORE =====
window.__DBG = window.__DBG || {
    ON: true, // passe à false pour couper les logs
    pfx: '[INTV]',
    log(){ if(this.ON) console.log(this.pfx, ...arguments); },
    warn(){ if(this.ON) console.warn(this.pfx, ...arguments); },
    err(){ if(this.ON) console.error(this.pfx, ...arguments); },
    expect(sel, label){
        const el = document.querySelector(sel);
        this.log('expect', label || sel, !!el, el);
        return !!el;
    }
};

// Erreurs globales
window.addEventListener('error', (e)=>{
    __DBG.err('window.onerror', e?.message, e?.filename, e?.lineno, e?.colno, e?.error);
});
window.addEventListener('unhandledrejection', (e)=>{
    __DBG.err('unhandledrejection', e?.reason);
});

// Petit “health-check” DOM lancé à la fin du fichier
window.__healthCheck = function(){
    __DBG.log('— HEALTH CHECK —');
    __DBG.expect('#infoModal', 'modal container');
    __DBG.expect('#infoModalBody', 'modal body');
    __DBG.expect('.hist table', 'table historique');
    __DBG.expect('#calGrid', 'agenda calGrid');
    __DBG.expect('#calListRows', 'agenda list rows');
    const z = getComputedStyle(document.querySelector('.modal') || document.body).zIndex;
    __DBG.log('modal z-index =', z);
};


(function () {
    // Horloge (base = heure serveur)
    //const base = new Date("{{ $serverNow }}");
    const base = new Date((window.APP && window.APP.serverNow) || Date.now());
    let now = new Date(base.getTime());
    const pad = n => (n < 10 ? '0' : '') + n;
    const draw = () => {
        const d = document.getElementById('srvDateText'), t = document.getElementById('srvTimeText');
        if (d) d.textContent = `${pad(now.getDate())}/${pad(now.getMonth() + 1)}/${now.getFullYear()}`;
        if (t) t.textContent = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
    };
    draw();
    setInterval(() => {
        now = new Date(now.getTime() + 60 * 1000);
        draw();
    }, 60 * 1000);

    // Réaffectation : toggle TECH / SAL + agenda
    // ✅ Réaffectation : seulement l’affichage TECH/SAL (plus de agendaWrap/agendaTech ici)
    const reaRadios = document.querySelectorAll('input[name="rea_type"]');
    const rowTech = document.getElementById('rowTech');
    const rowSal = document.getElementById('rowSal');
    const selTech = document.getElementById('selTech');

    function setMode(mode) {
        if (!rowTech || !rowSal) return; // garde-fou
        const techMode = mode === 'TECH';
        rowTech.classList.toggle('is-hidden', !techMode);
        rowSal.classList.toggle('is-hidden',  techMode);
        // désactive le select caché pour éviter qu’il soit posté
        document.getElementById('selTech')?.toggleAttribute('disabled', !techMode);
        document.getElementById('selSal')?.toggleAttribute('disabled', techMode);
        if (!techMode && selTech) {
            selTech.value = '';
        }
    }

    reaRadios.forEach(r => r.addEventListener('change', e => setMode(e.target.value)));
    setMode('TECH');
})();


(function () {
    const sel = document.getElementById('selModeTech');
    const calGrid = document.getElementById('calGrid');
    const calTitle = document.getElementById('calTitle');
    const calPrev = document.getElementById('calPrev');
    const calNext = document.getElementById('calNext');
    const calList = document.getElementById('calList');
    const calListTitle = document.getElementById('calListTitle');
    const calListRows = document.getElementById('calListRows');
    const calWrap   = document.getElementById('calWrap');
    const calToggle = document.getElementById('calToggle');
    let   lastShownKey = null;
    const dayNext = document.getElementById('dayNext');
    let   BYDAY = {};   // cache des données par jour (clé 'YYYY-MM-DD' -> {count, items[]})

    if (!sel || !calGrid) return;

    // Tech codes (for fallback ALL aggregation)
    //const TECHS = @json($techniciens->pluck('CodeSal')->values());
    //const NAMES = Object.fromEntries(@json($techniciens->map(fn($t)=>[$t->CodeSal,$t->NomSal])->values()));


    const APP        = window.APP || {};
    const TECHS      = APP.techs || [];
    const NAMES      = APP.names || {};
    const API_ROUTE  = APP.apiPlanningRoute || '';
    const SESSION_ID = APP.sessionId || '';
    const pad = n => (n < 10 ? '0' : '') + n;
    const ymd = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    const frMonth = (y, m) => new Date(y, m, 1).toLocaleDateString('fr-FR', {month: 'long', year: 'numeric'});

    // Visible month state
    let view = new Date();
    view.setDate(1);

    // Heat color from low (green) -> high (red)
    const heat = (val, max) => {
        if (!max) return '#ffffff';
        const t = Math.max(0, Math.min(1, val / max));           // 0..1
        const hue = Math.round(120 * (1 - t));                    // 120=green -> 0=red
        const sat = 80, light = 92 - Math.round(35 * t);          // lighter for low counts
        return `hsl(${hue} ${sat}% ${light}%)`;
    };

    async function fetchRange(code, from, to) {
        //const urlBase = `{{ route('api.planning.tech', ['codeTech'=>'__X__']) }}`.replace('__X__', encodeURIComponent(code));
        //const url = `${urlBase}?from=${from}&to=${to}&id={{ session('id') }}`;
        const urlBase = API_ROUTE.replace('__X__', encodeURIComponent(code));
        const url = `${urlBase}?from=${from}&to=${to}&id=${encodeURIComponent(SESSION_ID)}`;

        const res = await fetch(url, {headers: {'Accept': 'application/json'}});
        const txt = await res.text();
        let data = null;
        try {
            data = JSON.parse(txt);
        } catch (e) {
        }
        __DBG.log('fetchRange', { code, from, to, ok: !!(data && data.ok === true), status: res.status, count: (data && data.events ? data.events.length : 'n/a') });

        return {ok: !!(data && data.ok === true), data, status: res.status, body: txt};
    }

    async function fetchRangeAll(from, to) {
        // Try server-side _ALL first
        const tryAll = await fetchRange('_ALL', from, to);
        if (tryAll.ok) return tryAll.data.events || [];

        // Fallback: aggregate in client
        const all = [];
        await Promise.all(TECHS.map(async code => {
            const r = await fetchRange(code, from, to);
            if (r.ok && r.data && Array.isArray(r.data.events)) {
                // enforce code_tech present
                r.data.events.forEach(e => {
                    if (!e.code_tech) e.code_tech = code;
                    all.push(e);
                });
            } else {
                console.warn('Planning fallback fetch failed for', code, r.status, r.body);
            }
        }));
        return all;
    }

    function monthBounds(d) {
        const y = d.getFullYear(), m = d.getMonth();
        const first = new Date(y, m, 1);
        const last = new Date(y, m + 1, 0); // last day of month
        return {first, last};
    }

    function startOfWeek(d) {
        const r = new Date(d);
        const wd = (r.getDay() + 6) % 7; // Mon=0
        r.setDate(r.getDate() - wd);
        return r;
    }

    function addDays(d, n) {
        const r = new Date(d);
        r.setDate(r.getDate() + n);
        return r;
    }

    function hoursOnly(iso) {
        const dt = new Date(iso);
        return pad(dt.getHours()) + ':' + pad(dt.getMinutes());
    }

    async function render() {
        const {first, last} = monthBounds(view);
        const from = ymd(first), to = ymd(last);
        calTitle.textContent = frMonth(view.getFullYear(), view.getMonth());

        const mode = sel.value || '_ALL';
        let events = [];
        if (mode === '_ALL') {
            events = await fetchRangeAll(from, to);
        } else {
            const r = await fetchRange(mode, from, to);
            if (r.ok) events = r.data.events || []; else events = [];
        }

        // Aggregate count by day
        const byDay = {}; // 'YYYY-MM-DD' => {count, items[]}
        (events || []).forEach(e => {
            const dkey = (e.start_datetime || '').slice(0, 10);
            if (!dkey) return;
            if (!byDay[dkey]) byDay[dkey] = {count: 0, items: []};
            byDay[dkey].count++;
            byDay[dkey].items.push(e);
        });
        const maxCount = Object.values(byDay).reduce((m, v) => Math.max(m, v.count), 0);

        // Build grid: 7 weekdays header + 6 rows
        const labels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        let html = labels.map(w => `<div class="cal-weekday">${w}</div>`).join('');

        const gridStart = startOfWeek(new Date(first));     // Monday before (or equal) 1st
        const totalCells = 42;                               // 6 weeks
        for (let i = 0; i < totalCells; i++) {
            const day = addDays(gridStart, i);
            const inMonth = day.getMonth() === view.getMonth();
            const key = ymd(day);
            const meta = byDay[key] || {count: 0, items: []};
            const bg = heat(meta.count, maxCount);
            html += `<div class="cal-cell ${inMonth ? '' : 'muted'}" data-date="${key}" style="background:${bg}">
        <span class="d">${day.getDate()}</span>
        <span class="dot" title="${meta.count} RDV" style="background:${meta.count ? '#1112' : ''}"></span>
      </div>`;
        }
        calGrid.innerHTML = html;
        BYDAY = byDay;

// Click day => show list (via showDay)
        calGrid.querySelectorAll('.cal-cell').forEach(cell=>{
            cell.addEventListener('click', ()=>{
                const key = cell.getAttribute('data-date');
                showDay(key, byDay);
            });
        });

// Persistance de la liste : si un jour est déjà choisi, on le ré-affiche
        if (lastShownKey && byDay[lastShownKey]) {
            showDay(lastShownKey, byDay);
        } else if (calWrap?.classList.contains('collapsed')) {
            // en mode replié sans sélection -> afficher aujourd'hui (ou 1er jour dispo)
            const todayKey = ymd(new Date());
            const fallbackKey = byDay[todayKey] ? todayKey : Object.keys(byDay).sort()[0];
            if (fallbackKey) showDay(fallbackKey, byDay);
        } else {
            // mois déplié et aucune sélection -> on peut masquer la liste
            calList.classList.add('is-hidden');}


    }
    function escapeHtml(s){
        return String(s ?? '')
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;')
            .replace(/'/g,'&#39;');
    }

    function showDay(key, byDay){
        if (!key) return;

        const list = (byDay[key]?.items || []).slice()
            .sort((a,b)=> (a.start_datetime||'').localeCompare(b.start_datetime||''));

        calListTitle.textContent = `RDV du ${key.split('-').reverse().join('/')}`;

        const rows = list.map(e=>{
            const hhmm    = hoursOnly(e.start_datetime);
            const tech    = e.code_tech || '';
            const contact = e.contact   || '—';
            const label = e.label     || '';

            return `<tr data-row="rdv">
      <td>${escapeHtml(hhmm)}</td>
      <td>${escapeHtml(tech)}</td>
      <td>${escapeHtml(contact)}</td>
      <td>${escapeHtml(label)}</td>
      <td class="col-icon">
        <button class="icon-btn info-btn"
                type="button"
                title="Informations rendez-vous"
                aria-label="Informations rendez-vous"
                data-type="rdv"
                data-id="${e.id ?? ''}"
                data-heure="${escapeHtml(hhmm)}"
                data-tech="${escapeHtml(tech)}"
                data-contact="${escapeHtml(contact)}"
                data-label="${escapeHtml(label)}">i</button>
      </td>
    </tr>`;
        }).join('') || `<tr data-row="empty"><td colspan="5" class="note">Aucun rendez-vous</td></tr>`;

        calListRows.innerHTML = rows;
        ensureInfoButtons(list); // ← sécurise la présence des boutons

        calList.classList.remove('is-hidden');
        lastShownKey = key;

        __DBG && __DBG.log && __DBG.log(
            'RDV rows =', calListRows.querySelectorAll('tr[data-row="rdv"]').length,
            '| buttons =', calListRows.querySelectorAll('.info-btn[data-type="rdv"]').length
        );
    }

    function ensureInfoButtons(list){
        const trs = calListRows.querySelectorAll('tr[data-row="rdv"]');
        trs.forEach((tr, i) => {
            if (tr.querySelector('td[colspan]')) return;

            let cell = tr.querySelector('td.col-icon');
            if (!cell){
                cell = document.createElement('td');
                cell.className = 'col-icon';
                tr.appendChild(cell);
            }

            if (!cell.querySelector('.info-btn')){
                const e   = list[i] || {};
                const btn = document.createElement('button');
                btn.type  = 'button';
                btn.className = 'icon-btn info-btn';
                btn.title = 'Informations rendez-vous';
                btn.setAttribute('aria-label','Informations rendez-vous');
                btn.dataset.type = 'rdv';
                btn.dataset.id   = e.id ?? '';
                btn.dataset.heure = hoursOnly(e.start_datetime || '');
                btn.dataset.tech  = e.code_tech || '';
                btn.dataset.contact = e.contact || '—';
                btn.dataset.label = e.label || '';
                btn.textContent = 'i';

                // fallback local si la délégation globale ne prend pas
                btn.addEventListener('click', function(){
                    window.__openInfoFromButton && window.__openInfoFromButton(btn);
                });

                cell.appendChild(btn);
            }
        });
    }

    // Prev/Next month
    calPrev?.addEventListener('click', () => {
        view.setMonth(view.getMonth() - 1);
        render();
    });
    calNext?.addEventListener('click', () => {
        view.setMonth(view.getMonth() + 1);
        render();
    });

    // Change selection (ALL by default)
    sel.addEventListener('change', () => render());

    function keyToDate(key){ const [y,m,d] = key.split('-').map(Number); return new Date(y, m-1, d); }

    async function goNextDay(){
        // point de départ = jour déjà affiché, sinon aujourd’hui
        const base = lastShownKey ? keyToDate(lastShownKey) : new Date();
        const next = new Date(base.getFullYear(), base.getMonth(), base.getDate()+1);
        const nextKey = ymd(next);

        // si le mois change, on avance la vue puis on re-render avant d'afficher
        const monthChanged = (next.getMonth() !== view.getMonth()) || (next.getFullYear() !== view.getFullYear());
        if (monthChanged){
            view = new Date(next.getFullYear(), next.getMonth(), 1);
            await render();             // remet à jour BYDAY
        }
        showDay(nextKey, BYDAY);        // affichera "Aucun rendez-vous" si pas d’items
    }

    dayNext?.addEventListener('click', ()=>{ goNextDay(); });


    function setCollapsed(on){
        if (!calWrap) return;
        calWrap.classList.toggle('collapsed', !!on);
        if (calToggle){
            calToggle.textContent = on ? '▸ Mois' : '▾ Mois';
            calToggle.setAttribute('aria-expanded', (!on).toString());
        }
        // Si on replie sans sélection en cours, afficher aujourd'hui
        if (on && !lastShownKey) {
            const { first } = monthBounds(view);
            const todayKey = ymd(new Date());
            // On re-déclenchera showDay après le prochain render()
        }
    }

    calToggle?.addEventListener('click', ()=>{
        const on = !calWrap.classList.contains('collapsed');
        setCollapsed(on);
        // re-render pour recalculer byDay + fallback showDay si besoin
        render();
    });

// Par défaut : calendrier déplié
    setCollapsed(false);
    render(); // rafraîchit la grille tout de suite


    // Initial
    (function(){
        const box = document.getElementById('agendaBox');

        function sizeAgendaBox(){
            if(!box) return;
            const rect = box.getBoundingClientRect();
            const gap = 12; // marge basse, même esprit que tes paddings
            const max = window.innerHeight - rect.top - gap;
            box.style.maxHeight = Math.max(200, max) + 'px';
        }

        // évite que la page scrolle quand on est dans l'agenda
        box?.addEventListener('wheel', (e)=>{
            const el = box;
            const delta = e.deltaY;
            const atTop = el.scrollTop <= 0;
            const atBottom = Math.ceil(el.scrollTop + el.clientHeight) >= el.scrollHeight;
            if ((delta < 0 && !atTop) || (delta > 0 && !atBottom)){
                e.preventDefault();          // on consomme le scroll ici
                el.scrollTop += delta;
            }
        }, { passive:false });

        window.addEventListener('resize', sizeAgendaBox);
        // petit délai pour laisser le layout se poser (taille sticky dépend du contenu)
        window.addEventListener('load', ()=>setTimeout(sizeAgendaBox, 0));
        sizeAgendaBox();
    })();


})();

(function(){
    const elNote = document.getElementById('noteInterne');
    if (!elNote) return;

    const btnEdit   = document.getElementById('btnEdit');
    const btnSave   = document.getElementById('btnSave');
    const btnCancel = document.getElementById('btnCancel');
    const statusEl  = document.getElementById('noteStatus');
    const counterEl = document.getElementById('noteCounter');

    const updateUrl = elNote.dataset.updateUrl;
    const csrftoken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const sessionId = (window.APP_SESSION_ID || '').toString();
    const MAX = 1000;

    let initialValue = elNote.textContent;

    function applyButtonsState(on){
        elNote.classList.toggle('is-editing', on);
        btnSave .classList.toggle('is-hidden', !on);
        btnCancel.classList.toggle('is-hidden', !on);
        btnEdit .classList.toggle('is-hidden',  on);
    }
    function setEditing(on){
        elNote.setAttribute('contenteditable', on ? 'true' : 'false');
        applyButtonsState(on);
        if (on){
            elNote.focus();
            updateCounter();
        } else if (counterEl){
            counterEl.textContent = '';
        }
    }
    function getText(){ return (elNote.textContent || '').trim(); }
    function updateCounter(){
        if (!counterEl) return;
        let val = getText();
        if (val.length > MAX){
            elNote.textContent = val.slice(0, MAX);
            placeCaretAtEnd(elNote);
            val = getText();
        }
        counterEl.textContent = `${val.length}/${MAX}`;
    }
    function placeCaretAtEnd(node){
        const range = document.createRange();
        const sel = window.getSelection();
        range.selectNodeContents(node);
        range.collapse(false);
        sel.removeAllRanges();
        sel.addRange(range);
    }

    // Ctrl/Cmd+S pour sauver uniquement si visible
    elNote.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            if (!btnSave.classList.contains('is-hidden')) btnSave.click();
        }
    });

    // init
    setEditing(false);
    btnEdit  && btnEdit .addEventListener('click', () => setEditing(true));
    btnCancel&& btnCancel.addEventListener('click', () => { elNote.textContent = initialValue; setEditing(false); });
    elNote   && elNote   .addEventListener('input', updateCounter);
    btnSave  && btnSave  .addEventListener('click', async () => {
        const newValue = getText();
        if (newValue.length > MAX){ statusEl.textContent = 'Échec de l’enregistrement (limite 1000 caractères).'; return; }
        statusEl.textContent = 'Enregistrement…';
        try{
            const res = await fetch(updateUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json','Accept': 'application/json','X-CSRF-TOKEN': csrftoken},
                body: JSON.stringify({ id: sessionId, note: newValue })
            });
            const data = await res.json();
            if (!res.ok || !data || data.ok !== true) throw new Error();
            initialValue = newValue; statusEl.textContent = 'Enregistré ✔'; setEditing(false);
            setTimeout(()=> (statusEl.textContent = ''), 1500);
        }catch(e){ statusEl.textContent = 'Échec de l’enregistrement'; }
    });
})();
document.getElementById('interventionForm')?.addEventListener('submit', ()=>{
    const div = document.getElementById('noteInterne');
    const hid = document.getElementById('noteInterneField');
    if (div && hid) hid.value = (div.textContent || '').trim();
});

// === MODALE (contenu suivi / rdv) ===
(function(){
    const m    = document.getElementById('infoModal');
    const body = document.getElementById('infoModalBody');
    const xBtn = document.getElementById('infoModalClose');

    // --- helpers ---
    const esc = (s)=>
        String(s ?? '')
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;')
            .replace(/'/g,'&#39;');

    function open(html){
        if (!m || !body) return;
        body.innerHTML = html;                  // IMPORTANT: innerHTML (on veut du markup)
        m.classList.add('is-open');
        m.setAttribute('aria-hidden','false');
    }
    function close(){
        if (!m || !body) return;
        m.classList.remove('is-open');
        m.setAttribute('aria-hidden','true');
        body.innerHTML = '';                    // on nettoie
    }

    // --- “templates” de contenu ---
    function renderSuivi(btn){
        // On remonte à la <tr> et on réutilise la mise en forme existante de la 2e colonne
        const tr   = btn.closest('tr');
        const tds  = tr ? tr.children : [];
        const date = (tds?.[0]?.textContent || '—').trim();
        const html = (tds?.[1]?.innerHTML   || '').trim(); // conserve <strong>/<em> etc.

        return `
      <h3 style="margin:0 0 10px 0;font-size:16px;">Suivi du ${esc(date)}</h3>
      <div>${html}</div>
    `;
    }

    function renderRDV(btn){
        const d = btn.dataset || {};
        return `
      <h3 style="margin:0 0 10px 0;font-size:16px;">Détail du rendez-vous</h3>
      <div><strong>Heure&nbsp;:</strong> ${esc(d.heure || '—')}</div>
      <div><strong>Technicien&nbsp;:</strong> ${esc(d.tech || '—')}</div>
      <div><strong>Contact&nbsp;:</strong> ${esc(d.contact || '—')}</div>
      <div style="margin-top:8px;"><strong>Label</strong><br>${esc(d.label || '').replace(/\n/g,'<br>')}</div>
    `;
    }

    // --- écoute des clics ---
    document.addEventListener('click', (e)=>{
        // fermer si clic sur le fond
        if (e.target === m) return close();

        // ouvrir si clic sur un bouton info
        const btn = e.target.closest('.info-btn');
        if (!btn) return;

        const type = btn.dataset.type; // 'suivi' | 'rdv'
        if (type === 'suivi') return open(renderSuivi(btn));
        if (type === 'rdv')   return open(renderRDV(btn));

        // fallback
        open('<p>Pas de contenu disponible pour ce bouton.</p>');
    });

    // bouton ×
    xBtn && xBtn.addEventListener('click', close);

    // ESC
    document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') close(); });

    // petite API de debug
    window.MODAL = { open, close };
})();

const form = document.getElementById('interventionForm');
document.getElementById('btnPlanifier')?.addEventListener('click', () => {
    form.requestSubmit(); // lance l’événement submit proprement
});
