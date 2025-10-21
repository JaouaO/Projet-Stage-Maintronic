;(() => { /* placeholder */ })();

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

// Petit “health-check” DOM
window.__healthCheck = function(){
    __DBG.log('— HEALTH CHECK —');
    __DBG.expect('#infoModal', 'modal container');
    __DBG.expect('#infoModalBody', 'modal body');
    __DBG.expect('#calGrid', 'agenda calGrid');
    __DBG.expect('#calListRows', 'agenda list rows');
    const z = getComputedStyle(document.querySelector('.modal') || document.body).zIndex;
    __DBG.log('modal z-index =', z);
};

// --- Horloge serveur (span #srvDateTimeText) ---
(function () {
    const base = new Date((window.APP && window.APP.serverNow) || Date.now());
    let now = new Date(base.getTime());
    const pad = n => (n < 10 ? '0' : '') + n;

    function draw() {
        const el = document.getElementById('srvDateTimeText');
        if (!el) return;
        const dateTxt = `${pad(now.getDate())}/${pad(now.getMonth() + 1)}/${now.getFullYear()}`;
        const timeTxt = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
        el.textContent = `${dateTxt} ${timeTxt}`;
    }

    draw();
    setInterval(() => {
        now = new Date(now.getTime() + 60 * 1000);
        draw();
    }, 60 * 1000);
})();

// --- Agenda technicien (mois + liste du jour) ---
(function () {
    const sel         = document.getElementById('selModeTech');
    const calGrid     = document.getElementById('calGrid');
    const calTitle    = document.getElementById('calTitle');
    const calPrev     = document.getElementById('calPrev');
    const calNext     = document.getElementById('calNext');
    const calList     = document.getElementById('calList');
    const calListTitle= document.getElementById('calListTitle');
    const calListRows = document.getElementById('calListRows');
    const calWrap     = document.getElementById('calWrap');
    const calToggle   = document.getElementById('calToggle');
    const dayNext     = document.getElementById('dayNext');
    const dayPrev     = document.getElementById('dayPrev');
    let   lastShownKey= null;
    let   BYDAY       = {};

    if (!sel || !calGrid) return;

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

    // Heat color: green -> red
    const heat = (val, max) => {
        if (!max) return '#ffffff';
        const t = Math.max(0, Math.min(1, val / max));
        const hue = Math.round(120 * (1 - t));
        const sat = 80, light = 92 - Math.round(35 * t);
        return `hsl(${hue} ${sat}% ${light}%)`;
    };

    async function fetchRange(code, from, to) {
        const urlBase = API_ROUTE.replace('__X__', encodeURIComponent(code));
        const url = `${urlBase}?from=${from}&to=${to}&id=${encodeURIComponent(SESSION_ID)}`;
        const res = await fetch(url, {headers: {'Accept': 'application/json'}});
        const txt = await res.text();
        let data = null;
        try { data = JSON.parse(txt); } catch(e){}
        __DBG.log('fetchRange', { code, from, to, ok: !!(data && data.ok === true), status: res.status, count: (data && data.events ? data.events.length : 'n/a') });
        return {ok: !!(data && data.ok === true), data, status: res.status, body: txt};
    }

    async function fetchRangeAll(from, to) {
        const tryAll = await fetchRange('_ALL', from, to);
        if (tryAll.ok) return tryAll.data.events || [];

        const all = [];
        await Promise.all(TECHS.map(async code => {
            const r = await fetchRange(code, from, to);
            if (r.ok && r.data && Array.isArray(r.data.events)) {
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
        const last = new Date(y, m + 1, 0);
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
        const pad = n => (n < 10 ? '0' : '') + n;
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
            events = r.ok ? (r.data.events || []) : [];
        }

        const byDay = {};
        (events || []).forEach(e => {
            const dkey = (e.start_datetime || '').slice(0, 10);
            if (!dkey) return;
            if (!byDay[dkey]) byDay[dkey] = {count: 0, items: []};
            byDay[dkey].count++;
            byDay[dkey].items.push(e);
        });
        const maxCount = Object.values(byDay).reduce((m, v) => Math.max(m, v.count), 0);

        const labels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        let html = labels.map(w => `<div class="cal-weekday">${w}</div>`).join('');

        const gridStart = startOfWeek(new Date(first));
        const totalCells = 42;
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

        calGrid.querySelectorAll('.cal-cell').forEach(cell=>{
            cell.addEventListener('click', ()=>{
                const key = cell.getAttribute('data-date');
                showDay(key, byDay);
            });
        });

        if (lastShownKey && byDay[lastShownKey]) {
            showDay(lastShownKey, byDay);
        } else if (calWrap?.classList.contains('collapsed')) {
            const todayKey = ymd(new Date());
            const fallbackKey = byDay[todayKey] ? todayKey : Object.keys(byDay).sort()[0];
            if (fallbackKey) showDay(fallbackKey, byDay);
        } else {
            calList.classList.add('is-hidden');
        }
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
            const isTemp     = (e.is_validated === false || e.is_validated === 0 || e.is_validated === '0');
            const labelText  = (isTemp ? '[Temporaire] ' : '') + (e.label || '');
            const trClass    = isTemp ? ' class="temporaire"' : '';

            return `<tr data-row="rdv"${trClass}>
  <td>${escapeHtml(hhmm)}</td>
  <td>${escapeHtml(tech)}</td>
  <td>${escapeHtml(contact)}</td>
  <td>${escapeHtml(labelText)}</td>
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
            data-label="${escapeHtml(labelText)}">i</button>
  </td>
</tr>`;
        }).join('') || `<tr data-row="empty"><td colspan="5" class="note">Aucun rendez-vous</td></tr>`;

        calListRows.innerHTML = rows;
        ensureInfoButtons(list);

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
    calPrev?.addEventListener('click', () => { view.setMonth(view.getMonth() - 1); render(); });
    calNext?.addEventListener('click', () => { view.setMonth(view.getMonth() + 1); render(); });

    // Change selection
    sel.addEventListener('change', () => render());

    function keyToDate(key){ const [y,m,d] = key.split('-').map(Number); return new Date(y, m-1, d); }

    async function goNextDay(){
        const base = lastShownKey ? keyToDate(lastShownKey) : new Date();
        const next = new Date(base.getFullYear(), base.getMonth(), base.getDate()+1);
        const nextKey = ymd(next);

        const monthChanged = (next.getMonth() !== view.getMonth()) || (next.getFullYear() !== view.getFullYear());
        if (monthChanged){
            view = new Date(next.getFullYear(), next.getMonth(), 1);
            await render();
        }
        showDay(nextKey, BYDAY);
    }

    async function goPrevDay(){
        const base = lastShownKey ? keyToDate(lastShownKey) : new Date();
        const prev = new Date(base.getFullYear(), base.getMonth(), base.getDate()-1);
        const prevKey = ymd(prev);
        const monthChanged = (prev.getMonth() !== view.getMonth()) || (prev.getFullYear() !== view.getFullYear());
        if (monthChanged){
            view = new Date(prev.getFullYear(), prev.getMonth(), 1);
            await render();
        }
        showDay(prevKey, BYDAY);
    }

    dayNext?.addEventListener('click', ()=>{ goNextDay(); });
    dayPrev?.addEventListener('click', ()=>{ goPrevDay(); });

    function setCollapsed(on){
        if (!calWrap) return;
        calWrap.classList.toggle('collapsed', !!on);
        if (calToggle){
            calToggle.textContent = on ? '▸ Mois' : '▾ Mois';
            calToggle.setAttribute('aria-expanded', (!on).toString());
        }
        if (on && !lastShownKey) {
            // fallback via render()
        }
    }
    calToggle?.addEventListener('click', ()=>{
        const on = !calWrap.classList.contains('collapsed');
        setCollapsed(on);
        render();
    });

    // Par défaut : calendrier déplié
    setCollapsed(false);
    render();

    // Ajuste la hauteur de la box agenda + empêche le scroll de la page
    (function(){
        const box = document.getElementById('agendaBox');
        function sizeAgendaBox(){
            if(!box) return;
            const rect = box.getBoundingClientRect();
            const gap = 12;
            const max = window.innerHeight - rect.top - gap;
            box.style.maxHeight = Math.max(200, max) + 'px';
        }
        box?.addEventListener('wheel', (e)=>{
            const el = box;
            const delta = e.deltaY;
            const atTop = el.scrollTop <= 0;
            const atBottom = Math.ceil(el.scrollTop + el.clientHeight) >= el.scrollHeight;
            if ((delta < 0 && !atTop) || (delta > 0 && !atBottom)){
                e.preventDefault();
                el.scrollTop += delta;
            }
        }, { passive:false });
        window.addEventListener('resize', sizeAgendaBox);
        window.addEventListener('load', ()=>setTimeout(sizeAgendaBox, 0));
        sizeAgendaBox();
    })();
})();

// === MODALE (contenu suivi / rdv) ===
(function(){
    const m    = document.getElementById('infoModal');
    const body = document.getElementById('infoModalBody');
    const xBtn = document.getElementById('infoModalClose');

    const esc = (s)=>
        String(s ?? '')
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;')
            .replace(/'/g,'&#39;');

    function open(html){
        if (!m || !body) return;
        body.innerHTML = html;
        m.classList.add('is-open');
        m.setAttribute('aria-hidden','false');
    }
    function close(){
        if (!m || !body) return;
        m.classList.remove('is-open');
        m.setAttribute('aria-hidden','true');
        body.innerHTML = '';
    }

    function renderSuivi(btn){
        const tr   = btn.closest('tr');
        const tds  = tr ? tr.children : [];
        const date = (tds?.[0]?.textContent || '—').trim();
        const html = (tds?.[1]?.innerHTML   || '').trim();
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

    document.addEventListener('click', (e)=>{
        if (e.target === m) return close();
        const btn = e.target.closest('.info-btn');
        if (!btn) return;
        const type = btn.dataset.type;
        if (type === 'suivi') return open(renderSuivi(btn));
        if (type === 'rdv')   return open(renderRDV(btn));
        open('<p>Pas de contenu disponible pour ce bouton.</p>');
    });

    xBtn && xBtn.addEventListener('click', close);
    document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') close(); });
    window.MODAL = { open, close };
})();



// Planifier un nouvel appel => pas d'ajout RDV
(function(){
    const form = document.getElementById('interventionForm');
    const btnCall = document.getElementById('btnPlanifierAppel');
    const actionType = document.getElementById('actionType');

    if (!form || !btnCall || !actionType) return;

    btnCall.addEventListener('click', () => {
        actionType.value = 'call';
        // Optionnel: on ignore date/heure du formulaire pour ne pas laisser croire qu'on va planifier
        // document.getElementById('dtPrev')?.value = '';
        // document.getElementById('tmPrev')?.value = '';
        form.requestSubmit();
    });
})();



// Valider le prochain RDV : propose Remplacer / Valider quand même / Modifier avant de valider
(function(){
    const form     = document.getElementById('interventionForm');
    const btn      = document.getElementById('btnValider');
    const numInt   = document.getElementById('openHistory')?.dataset.numInt || '';
    const csrf     = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Modale réutilisée
    const modal    = document.getElementById('infoModal');
    const modalBody= document.getElementById('infoModalBody');
    const modalX   = document.getElementById('infoModalClose');

    function openModal(html){
        if(!modal || !modalBody) return;
        modalBody.innerHTML = html;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden','false');
    }
    function closeModal(){
        if(!modal || !modalBody) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden','true');
        modalBody.innerHTML = '';
    }
    modalX?.addEventListener('click', closeModal);

    if (!btn || !form) return;

    btn.addEventListener('click', async () => {
        document.getElementById('actionType').value = 'validate_rdv'; // optionnel mais explicite
        const tech  = document.getElementById('selAny')?.value || '';
        const date  = document.getElementById('dtPrev')?.value || '';
        const heure = document.getElementById('tmPrev')?.value || '';

        // Si un des 3 manque → soumission classique
        if (!numInt || !tech || !date || !heure) {
            form.requestSubmit();
            return;
        }

        // 1) Check existence RDV temporaire identique (dry-run)
        const urlCheck = `/interventions/${encodeURIComponent(numInt)}/rdv/valider`;
        try{
            const r1 = await fetch(urlCheck, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    rea_sal: tech,
                    date_rdv: date,
                    heure_rdv: heure,
                    dry_run: true
                }),
            });
            const j1 = await r1.json().catch(()=>({ok:false}));
            const exists = !!(j1 && j1.ok && j1.exists === true);

            if (!exists) {
                // → Pas de RDV temporaire : soumission normale
                form.requestSubmit();
                return;
            }

            // 2) RDV temporaire trouvé → pop-up avec 3 choix
            const html = `
        <div>
          <h3 style="margin:0 0 10px 0;font-size:16px;">RDV temporaire détecté</h3>
          <p>Un RDV <em>temporaire</em> existe déjà pour <strong>${date.split('-').reverse().join('/')}</strong> à <strong>${heure}</strong> sur ce dossier.</p>
          <p>Que souhaites-tu faire&nbsp;?</p>
          <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;">
            <button id="optRemplacer" class="btn ok" type="button" title="Valider et actualiser le RDV existant">Remplacer</button>
            <button id="optValiderQuandMeme" class="btn" type="button" title="Ne pas toucher au RDV temporaire et valider le formulaire">Valider quand même</button>
            <button id="optModifier" class="btn" type="button" title="Fermer la fenêtre pour modifier avant de valider">Modifier avant de valider</button>
          </div>
        </div>
      `;
            openModal(html);

            // 2.a) Remplacer → on valide/actualise côté serveur, puis on submit le formulaire
            modalBody.querySelector('#optRemplacer')?.addEventListener('click', async ()=>{
                try{
                    const r2 = await fetch(urlCheck, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({
                            rea_sal: tech,
                            date_rdv: date,
                            heure_rdv: heure,
                            dry_run: false
                        }),
                    });
                    const j2 = await r2.json().catch(()=>({ok:false}));
                    if (!r2.ok || !j2.ok) {
                        alert("Impossible de valider/actualiser le RDV temporaire.");
                        return;
                    }
                    // Flag pour backend (si tu veux éviter un doublon planning dans updateIntervention)
                    const flag = document.getElementById('rdvValidatedByAjax');
                    if (flag) flag.value = '1';

                    // Refresh vue agenda et submit
                    document.getElementById('selModeTech')?.dispatchEvent(new Event('change'));
                    closeModal();
                    form.requestSubmit();
                }catch(e){
                    console.error('[Valider RDV - Remplacer] erreur', e);
                    alert("Erreur lors de la validation/actualisation du RDV.");
                }
            });

            // 2.b) Valider quand même → on ne touche pas au RDV temporaire, on envoie le formulaire
            modalBody.querySelector('#optValiderQuandMeme')?.addEventListener('click', ()=>{
                closeModal();
                form.requestSubmit();
            });

            // 2.c) Modifier avant de valider → on ferme, aucun submit
            modalBody.querySelector('#optModifier')?.addEventListener('click', ()=>{
                closeModal();
            });

        }catch(e){
            console.error('[Valider RDV] erreur', e);
            // En cas de souci réseau, on retombe sur la validation normale pour ne pas bloquer
            form.requestSubmit();
        }
    });
})();



document.getElementById('btnPlanifierRdv')?.addEventListener('click', async () => {
    document.getElementById('actionType').value = ''; // <- reset
    const numInt = document.getElementById('openHistory')?.dataset.numInt;
    const tech   = document.getElementById('selAny')?.value || '';
    const date   = document.getElementById('dtPrev')?.value || '';
    const time   = document.getElementById('tmPrev')?.value || '';

    if (!numInt || !tech || !date || !time) {
        alert('Sélectionne le technicien, la date et l’heure.');
        return;
    }

    const url = `/interventions/${encodeURIComponent(numInt)}/rdv/temporaire`;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({
            rea_sal: tech,
            date_rdv: date,
            heure_rdv: time,
            code_postal: document.querySelector('input[name="code_postal"]')?.value || null,
            ville: document.querySelector('input[name="ville"]')?.value || null,
        }),
    });

    const out = await res.json().catch(()=>({ok:false}));
    if (!res.ok || !out.ok) {
        alert('Erreur lors de la création du RDV temporaire.');
        return;
    }

    // Reste sur la page et rafraîchit l’agenda côté client
    // (force un rerender du mois courant)
    const ev = new Event('change');
    document.getElementById('selModeTech')?.dispatchEvent(ev);
});


// === Fenêtre "Historique" (popup) — sans boutons ===
(function(){
    const btn = document.getElementById('openHistory');
    if (!btn) return;

    btn.addEventListener('click', () => {
        const tpl = document.getElementById('tplHistory');
        if (!tpl) { console.error('[HIST] template #tplHistory introuvable'); return; }

        const w = window.open('', 'historique_'+Date.now(), 'width=960,height=720');
        if (!w) { console.error('[HIST] window.open a été bloqué'); return; }

        // Récupère le HTML du template
        let inner = '';
        if (tpl.content && tpl.content.cloneNode) {
            const frag = tpl.content.cloneNode(true);
            inner = (frag.firstElementChild?.outerHTML || frag.textContent || '').trim();
        } else {
            inner = (tpl.innerHTML || '').trim();
        }
        if (!inner) {
            console.error('[HIST] Le template est vide');
            inner = '<p style="color:#b00">Aucun contenu trouvé pour l’historique.</p>';
        }

        // Adapter les classes pour réutiliser la feuille CSS existante
        inner = inner.replace(/class="hist-table"/g, 'class="table"');

        // URL absolue du CSS existant (celui déjà chargé dans la page)
        const cssHref = document.querySelector('link[rel="stylesheet"][href*="intervention_edit.css"]')?.href || '';

        // Numéro d'intervention via data-num-int du bouton
        const numInt = btn.dataset.numInt || '';
        const safeNum = numInt ? String(numInt).replace(/[<>&"]/g, s=>({ '<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;' }[s])) : '';

        const html = `
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Historique</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  ${cssHref ? `<link rel="stylesheet" href="${cssHref}">` : ''}
</head>
<body>
  <div class="box" style="margin:12px">
    <div class="body">
      <div class="table">
        ${inner}
      </div>
    </div>
  </div>
  <script>
    console.log('[HIST] popup chargée (CSS externe OK)');
    document.addEventListener('click', function(e){
      const btn = e.target.closest('.hist-toggle');
      if(!btn) return;
      const tr = btn.closest('tr');
      const next = tr && tr.nextElementSibling;
      if(!next || !next.matches('.row-details')) return;
      const open = next.style.display !== 'none';
      next.style.display = open ? 'none' : '';
      btn.textContent = open ? '+' : '−';
      btn.setAttribute('aria-expanded', (!open).toString());
    });
  <\/script>
</body>
</html>`;

        try {
            w.document.open();
            w.document.write(html);
            w.document.close();
        } catch (err) {
            console.error('[HIST] Erreur écriture document:', err);
        }
    });
})();


(function enforceNoPast(){
    const d = document.getElementById('dtPrev');
    const t = document.getElementById('tmPrev');

    if(!d || !t) return;

    // min = aujourd'hui
    const now = new Date();
    const pad = n => (n<10?'0':'')+n;
    const today = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}`;
    const hhmm  = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
    d.min = today;

    function applyTimeMin(){
        // Si la date choisie est aujourd'hui -> min = heure courante, sinon min libre
        if (d.value === today) {
            t.min = hhmm;
            // Si l'heure choisie est passée, on la pousse à maintenant
            if (t.value && t.value < hhmm) t.value = hhmm;
        } else {
            t.removeAttribute('min');
        }
    }

    d.addEventListener('change', applyTimeMin);
    // au chargement
    applyTimeMin();
})();
