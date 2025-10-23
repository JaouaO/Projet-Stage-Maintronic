// agenda.js
// Affichage agenda (mois + liste jour) avec badges URGENT
// suppose que window.APP.apiPlanningRoute existe (route('api.planning.tech', ['codeTech'=>'__X__']))


export function initAgenda(){
    const elSel   = document.getElementById('selModeTech');
    const calWrap = document.getElementById('calWrap');
    const calGrid = document.getElementById('calGrid');
    const calTitle= document.getElementById('calTitle');
    const calPrev = document.getElementById('calPrev');
    const calNext = document.getElementById('calNext');
    const calToggle = document.getElementById('calToggle');

    const listWrap= document.getElementById('calList');
    const listTitle=document.getElementById('calListTitle');
    const dayPrev = document.getElementById('dayPrev');
    const dayNext = document.getElementById('dayNext');
    const listRows= document.getElementById('calListRows');

    if (!elSel || !calWrap || !calGrid || !listRows) return;

    const state = {
        cur: startOfMonth(new Date()),
        mode: 'month', // 'month' | 'day'
        day: null,     // Date du jour sélectionné
        tech: '_ALL',
        cache: new Map(), // key `${tech}|${yyyy-mm}` -> {events, byDay}
        reqToken: 0,               // NEW: pour savoir si la requête est la plus récente
    };


    // init
    state.tech = elSel.value || '_ALL';
    paintMonth();

    // events
    elSel.addEventListener('change', () => {
        state.tech = elSel.value || '_ALL';
        state.cache.clear();
        paintMonth();
    });

    calPrev.addEventListener('click', () => {
        state.cur = addMonths(state.cur, -1);
        paintMonth();
    });
    calNext.addEventListener('click', () => {
        state.cur = addMonths(state.cur, +1);
        paintMonth();
    });

    calToggle.addEventListener('click', () => {
        const expanded = calToggle.getAttribute('aria-expanded') !== 'true';
        calToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        if (expanded){
            calWrap.classList.remove('collapsed');
            state.mode='month';
        } else {
            calWrap.classList.add('collapsed');
            state.mode='day';
        }
    });

    dayPrev.addEventListener('click', () => {
        if (!state.day) return;
        state.day = addDays(state.day, -1);
        openDay(state.day);
    });
    dayNext.addEventListener('click', () => {
        if (!state.day) return;
        state.day = addDays(state.day, +1);
        openDay(state.day);
    });

    // === helpers ===
    function ymd(d){
        const yyyy = d.getFullYear();
        const mm   = String(d.getMonth()+1).padStart(2,'0');
        const dd   = String(d.getDate()).padStart(2,'0');
        return `${yyyy}-${mm}-${dd}`;
    }    function startOfMonth(d){ const x = new Date(d); x.setDate(1); x.setHours(0,0,0,0); return x; }
    function addMonths(d, n){ const x = new Date(d); x.setMonth(x.getMonth()+n); return x; }
    function addDays(d, n){ const x = new Date(d); x.setDate(x.getDate()+n); return x; }
    function endOfMonth(d){ const x = new Date(d); x.setMonth(x.getMonth()+1,0); x.setHours(23,59,59,999); return x; }
    function fmtMonthYear(d){
        return d.toLocaleDateString('fr-FR',{month:'long', year:'numeric'});
    }
    function fmtDayTitle(d){
        return d.toLocaleDateString('fr-FR',{weekday:'long', day:'2-digit', month:'long', year:'numeric'});
    }
    function timeHHMM(iso){
        if (!iso) return '';
        const t = iso.split('T')[1] || '00:00:00';
        return t.slice(0,5);
    }
    function pad2(n){ return String(n).padStart(2,'0'); }

// ⚠️ clé de cache locale au mois, pas de toISOString()
    function monthKey(d){ return `${state.tech}|${d.getFullYear()}-${pad2(d.getMonth()+1)}`; }




    async function fetchMonthData(d){
        const first = startOfMonth(d);
        const last  = endOfMonth(d);
        const k = monthKey(first);                   // ✅ clé locale YYYY-MM

        if (state.cache.has(k)) return state.cache.get(k);

        // ✅ loader ON parce que pas en cache
        calGrid.classList.add('is-loading');

        // anti-course
        const myToken = ++state.reqToken;

        const url = (window.APP?.apiPlanningRoute || '').replace('__X__', encodeURIComponent(state.tech));
        const qs  = `from=${ymd(first)}&to=${ymd(last)}`;

        let payload = { events:[], byDay:new Map() };
        try {
            const res = await fetch(`${url}?${qs}`, {credentials:'same-origin', headers:{'Accept':'application/json'}});
            const out = await res.json().catch(()=>({ok:false,events:[]}));
            const events = out && out.ok && Array.isArray(out.events) ? out.events : [];

            const byDay = new Map();
            for (const ev of events){
                const dKey = (ev.start_datetime || '').slice(0,10);
                if (!dKey) continue;
                const bucket = byDay.get(dKey) || { list:[], urgentCount:0, count:0 };
                bucket.list.push(ev);
                bucket.count++;
                if (ev.is_urgent) bucket.urgentCount++;
                byDay.set(dKey, bucket);
            }

            payload = { events, byDay };
            state.cache.set(k, payload);
        } finally {
            // ✅ loader OFF seulement si c'est la réponse la plus récente
            if (myToken === state.reqToken) calGrid.classList.remove('is-loading');
        }

        return payload;
    }


    async function paintMonth(){
        calTitle.textContent = fmtMonthYear(state.cur);
        listWrap.classList.add('is-hidden'); // on cache la liste jour quand on affiche le mois

        const data = await fetchMonthData(state.cur);
        const first = startOfMonth(state.cur);
        const last  = endOfMonth(state.cur);
        // calc du max pour échelle heatmap
        let maxCount = 0;
        for (const [,bucket] of data.byDay) maxCount = Math.max(maxCount, bucket.count || 0);
        const heatOf = (count) => {
            if (!maxCount) return 0;
            // 0..10
            return Math.max(0, Math.min(10, Math.round((count / maxCount) * 10)));
        };


        // construire la grille : on part du lundi de la 1ère semaine
        const startGrid = new Date(first);
        const dayOfWeek = (startGrid.getDay()+6)%7; // lundi=0
        startGrid.setDate(startGrid.getDate()-dayOfWeek);

        const cells = [];
        for (let i=0;i<42;i++){
            const d = addDays(startGrid, i);
            const inMonth = (d.getMonth() === state.cur.getMonth());
            const key = ymd(d);
            const info = data.byDay.get(key) || {list:[], urgentCount:0, count:0};



            const cell = document.createElement('div');
            cell.className = 'cal-cell';

// calcule l'intensité même quand count = 0
            const heat = heatOf(info.count);

// ✅ toujours appliquer la classe heat-*
            cell.classList.add(`heat-${heat}`);

            if (info.count > 0) cell.classList.add('has-events');
            if (info.urgentCount > 0) cell.classList.add('has-urgent');
            if (!inMonth) cell.classList.add('muted');

            cell.setAttribute('data-date', key);
            cell.innerHTML = `
  <span class="d">${String(d.getDate()).padStart(2,'0')}</span>
  <span class="dot" title="${info.count ? info.count+' évènement(s)' : ''}"></span>
`;

            cell.addEventListener('click', () => {
                state.day = d;
                openDay(d);
            });

            cells.push(cell);
        }

        calGrid.replaceChildren(...cells);
        calGrid.querySelectorAll('.cal-cell[aria-current="date"]').forEach(el=>el.removeAttribute('aria-current'));
        const todayCell = calGrid.querySelector(`.cal-cell[data-date="${ymd(state.day || new Date())}"]`);
        if (todayCell) todayCell.setAttribute('aria-current','date');
        calWrap.classList.remove('collapsed');
        calToggle.setAttribute('aria-expanded','true');
        state.mode='month';
        fetchMonthData(addMonths(state.cur, +1)).catch(()=>{});
        fetchMonthData(addMonths(state.cur, -1)).catch(()=>{});
    }

    async function openDay(d){
        const data   = await fetchMonthData(d);   // ✅ prend le mois du jour cliqué
        const key    = ymd(d);
        const bucket = data.byDay.get(key) || { list:[] };

        listTitle.textContent = fmtDayTitle(d);
        listRows.innerHTML = '';

        if (!bucket.list.length){
            const tr = document.createElement('tr');
            tr.innerHTML = `<td colspan="5" class="note cell-p8-10">Aucun évènement</td>`;
            listRows.appendChild(tr);
        } else {
            const rows = bucket.list
                .sort((a,b)=> String(a.start_datetime).localeCompare(String(b.start_datetime)))
                .map(ev => makeRow(ev));
            listRows.replaceChildren(...rows);
        }

        // Afficher la liste du jour SANS collapse le mois
        listWrap.classList.remove('is-hidden');
        calWrap.classList.remove('collapsed');
        calToggle.setAttribute('aria-expanded','true');
        state.mode='month';   // on reste en mode "mois"

        // Optionnel: scroll jusqu'à la liste pour la mettre sous les yeux
        listWrap.scrollIntoView({behavior:'smooth', block:'start'});
    }


    function makeRow(ev){
        const tr = document.createElement('tr');
        if (ev && ev.is_urgent) tr.classList.add('urgent');
        if (ev && (ev.is_validated===0 || ev.is_validated===false)) tr.classList.add('temporaire');

        const hhmm = timeHHMM(ev.start_datetime);
        const code = ev.code_tech || '';
        const contact = ev.contact || '—';
        const label = ev.label || ev.num_int || '';

        // bouton "i" (modale détails)
        const btn = document.createElement('button');
        btn.className = 'icon-btn';
        btn.type = 'button';
        btn.textContent = 'i';
        btn.title = 'Détails';
        btn.addEventListener('click', () => showEventModal(ev));

        const tdInfo = document.createElement('td');
        tdInfo.className = 'col-icon';
        tdInfo.appendChild(btn);

        const tdLab = document.createElement('td');
        tdLab.textContent = label;
        if (ev.is_urgent){
            const b = document.createElement('span');
            b.className = 'badge badge-urgent';
            b.textContent = 'URGENT';
            b.style.marginLeft = '6px';
            tdLab.appendChild(b);
        }

        tr.innerHTML = `
      <td>${hhmm}</td>
      <td>${code}</td>
      <td>${escapeHtml(contact)}</td>
    `;
        tr.appendChild(tdLab);
        tr.appendChild(tdInfo);
        return tr;
    }

    function fmtDateTimeFR(iso){
        if(!iso) return '';
        const d = new Date(iso);
        if (isNaN(d)) return iso; // fallback si pas ISO
        const dd = String(d.getDate()).padStart(2,'0');
        const mm = String(d.getMonth()+1).padStart(2,'0');
        const yyyy = d.getFullYear();
        const hh = String(d.getHours()).padStart(2,'0');
        const mi = String(d.getMinutes()).padStart(2,'0');
        return `${dd}/${mm}/${yyyy} ${hh}:${mi}`;
    }


    function showEventModal(ev){
        // réutilise la modale générique
        const modal = document.getElementById('infoModal');
        const body  = document.getElementById('infoModalBody');
        const close = document.getElementById('infoModalClose');
        if (!modal || !body) return;

        const parts = [];
        parts.push(`<div class="section"><div class="section-title">Intervention</div><div><strong>${escapeHtml(ev.num_int || '—')}</strong></div></div>`);
        parts.push(`<div class="section"><div class="section-title">Quand</div><div>${escapeHtml(fmtDateTimeFR(ev.start_datetime))}</div></div>`);        parts.push(`<div class="section"><div class="section-title">Technicien</div><div>${escapeHtml(ev.code_tech || '—')}</div></div>`);
        if (ev.commentaire) parts.push(`<div class="section"><div class="section-title">Commentaire</div><div class="prewrap">${escapeHtml(ev.commentaire)}</div></div>`);
        if (ev.cp || ev.ville) parts.push(`<div class="section"><div class="section-title">Lieu</div><div>${escapeHtml([ev.cp, ev.ville].filter(Boolean).join(' '))}</div></div>`);
        if (ev.marque) parts.push(`<div class="section"><div class="section-title">Marque</div><div>${escapeHtml(ev.marque)}</div></div>`);
        if (ev.is_urgent){
            parts.push(`<div class="section"><span class="badge badge-urgent">URGENT</span></div>`);
        }

        body.innerHTML = `<h3 class="modal-title">Détails du rendez-vous</h3>${parts.join('')}`;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden','false');
        close?.addEventListener('click', () => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden','true');
            body.innerHTML = '';
        }, {once:true});
    }

    function escapeHtml(s){
        return String(s||'').replace(/[<>&"]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));
    }
}
