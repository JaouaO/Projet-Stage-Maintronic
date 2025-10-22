import { pad, hoursOnly, escapeHtml } from './utils.js';
import { DBG } from './debug.js';

export function initAgenda() {
    const sel = document.getElementById('selModeTech');
    const calGrid = document.getElementById('calGrid');
    const calTitle = document.getElementById('calTitle');
    const calPrev = document.getElementById('calPrev');
    const calNext = document.getElementById('calNext');
    const calList = document.getElementById('calList');
    const calListTitle = document.getElementById('calListTitle');
    const calListRows = document.getElementById('calListRows');
    const calWrap = document.getElementById('calWrap');
    const dayNext = document.getElementById('dayNext');
    const dayPrev = document.getElementById('dayPrev');

    if (!sel || !calGrid) return;

    const APP = window.APP || {};
    const TECHS = APP.techs || [];
    const API_ROUTE = APP.apiPlanningRoute || '';
    const SESSION_ID = APP.sessionId || '';

    const ymd = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    const frMonth = (y, m) => new Date(y, m, 1).toLocaleDateString('fr-FR', {month: 'long', year: 'numeric'});
    let view = new Date(); view.setDate(1);
    let lastShownKey = null;
    let BYDAY = {};

    const monthBounds = (d) => ({ first: new Date(d.getFullYear(), d.getMonth(), 1), last: new Date(d.getFullYear(), d.getMonth()+1, 0) });
    const startOfWeek = (d) => { const r=new Date(d); const wd=(r.getDay()+6)%7; r.setDate(r.getDate()-wd); return r; };
    const addDays = (d,n) => { const r=new Date(d); r.setDate(r.getDate()+n); return r; };

    async function fetchRange(code, from, to) {
        const urlBase = API_ROUTE.replace('__X__', encodeURIComponent(code));
        const url = `${urlBase}?from=${from}&to=${to}&id=${encodeURIComponent(SESSION_ID)}`;
        const res = await fetch(url, {headers: {'Accept': 'application/json'}});
        const txt = await res.text();
        let data = null; try { data = JSON.parse(txt); } catch {}
        DBG.log('fetchRange', {code, from, to, ok: !!(data && data.ok === true), status: res.status, count: (data && data.events ? data.events.length : 'n/a')});
        return { ok: !!(data && data.ok === true), data, status: res.status, body: txt };
    }
    async function fetchRangeAll(from, to) {
        const tryAll = await fetchRange('_ALL', from, to);
        if (tryAll.ok) return tryAll.data.events || [];
        const all = [];
        await Promise.all((TECHS||[]).map(async code => {
            const r = await fetchRange(code, from, to);
            if (r.ok && r.data && Array.isArray(r.data.events)) {
                r.data.events.forEach(e => { if (!e.code_tech) e.code_tech = code; all.push(e); });
            }
        }));
        return all;
    }

    async function render() {
        const {first, last} = monthBounds(view);
        const from = ymd(first), to = ymd(last);
        calTitle.textContent = frMonth(view.getFullYear(), view.getMonth());

        const mode = sel.value || '_ALL';
        const events = mode === '_ALL' ? await fetchRangeAll(from, to) : ((await fetchRange(mode, from, to)).data?.events || []);

        const byDay = {};
        (events||[]).forEach(e => {
            const dkey = (e.start_datetime || '').slice(0,10);
            if (!dkey) return;
            if (!byDay[dkey]) byDay[dkey] = {count:0, items:[]};
            byDay[dkey].count++;
            byDay[dkey].items.push(e);
        });
        const maxCount = Object.values(byDay).reduce((m,v)=>Math.max(m,v.count), 0);

        const labels = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
        let html = labels.map(w => `<div class="cal-weekday">${w}</div>`).join('');

        const gridStart = startOfWeek(new Date(first));
        for (let i=0;i<42;i++){
            const day = addDays(gridStart, i);
            const inMonth = day.getMonth() === view.getMonth();
            const key = ymd(day);
            const meta = byDay[key] || {count:0, items:[]};
            const level = maxCount ? Math.min(10, Math.round((meta.count / maxCount) * 10)) : 0;

            html += `<div class="cal-cell ${inMonth ? '' : 'muted'} heat-${level} ${meta.count ? 'has-events' : ''}" data-date="${key}">
        <span class="d">${day.getDate()}</span>
        <span class="dot" title="${meta.count} RDV"></span>
      </div>`;
        }
        calGrid.innerHTML = html;
        BYDAY = byDay;

        calGrid.querySelectorAll('.cal-cell').forEach(cell => {
            cell.addEventListener('click', () => showDay(cell.getAttribute('data-date'), byDay));
        });

        if (lastShownKey && byDay[lastShownKey]) showDay(lastShownKey, byDay);
        else if (calWrap?.classList.contains('collapsed')) {
            const todayKey = ymd(new Date());
            const fallbackKey = byDay[todayKey] ? todayKey : Object.keys(byDay).sort()[0];
            if (fallbackKey) showDay(fallbackKey, byDay);
        } else {
            calList.classList.add('is-hidden');
        }
    }

    function showDay(key, byDay) {
        if (!key) return;
        const list = (byDay[key]?.items || []).slice().sort((a,b)=> (a.start_datetime||'').localeCompare(b.start_datetime||''));
        calListTitle.textContent = `RDV du ${key.split('-').reverse().join('/')}`;

        const rows = list.map(e => {
            const hhmm = hoursOnly(e.start_datetime);
            const tech = e.code_tech || '';
            const contact = e.contact || '—';
            const isTemp = (e.is_validated === false || e.is_validated === 0 || e.is_validated === '0');
            const labelText = (e.label || '');
            const badge = isTemp ? '<span class="badge badge-temp" aria-label="Rendez-vous temporaire">Temporaire</span>' :
                '<span class="badge badge-valid" aria-label="Rendez-vous validé">Validé</span>';
            const trClass = isTemp ? ' class="temporaire"' : '';
            return `<tr data-row="rdv"${trClass}>
        <td>${escapeHtml(hhmm)}</td>
        <td>${escapeHtml(tech)}</td>
        <td>${escapeHtml(contact)}</td>
        <td><div class="hstack-6">${badge}<span>${escapeHtml(labelText)}</span></div></td>
        <td class="col-icon"><button class="icon-btn info-btn" type="button" title="Informations rendez-vous" aria-label="Informations rendez-vous"
          data-type="rdv" data-id="${e.id ?? ''}" data-heure="${escapeHtml(hhmm)}" data-tech="${escapeHtml(tech)}"
          data-contact="${escapeHtml(contact)}" data-label="${escapeHtml(labelText)}" data-ville="${escapeHtml(e.ville || '')}"
          data-cp="${escapeHtml(e.cp || '')}" data-marque="${escapeHtml(e.marque || '')}" data-commentaire="${escapeHtml(e.commentaire || '')}"
          data-temp="${isTemp ? '1' : '0'}">i</button></td>
      </tr>`;
        }).join('') || `<tr data-row="empty"><td colspan="5" class="note">Aucun rendez-vous</td></tr>`;

        calListRows.innerHTML = rows;
        calList.classList.remove('is-hidden');
        lastShownKey = key;
    }

    // nav mois & jours
    document.getElementById('calPrev')?.addEventListener('click', () => { view.setMonth(view.getMonth()-1); render(); });
    document.getElementById('calNext')?.addEventListener('click', () => { view.setMonth(view.getMonth()+1); render(); });
    sel.addEventListener('change', () => render());

    document.getElementById('dayNext')?.addEventListener('click', () => {
        const base = lastShownKey ? new Date(...lastShownKey.split('-').map((n,i)=> i===1?Number(n)-1:Number(n))) : new Date();
        const next = new Date(base.getFullYear(), base.getMonth(), base.getDate() + 1);
        const monthChanged = (next.getMonth() !== view.getMonth()) || (next.getFullYear() !== view.getFullYear());
        if (monthChanged) { view = new Date(next.getFullYear(), next.getMonth(), 1); render(); }
    });
    document.getElementById('dayPrev')?.addEventListener('click', () => {
        const base = lastShownKey ? new Date(...lastShownKey.split('-').map((n,i)=> i===1?Number(n)-1:Number(n))) : new Date();
        const prev = new Date(base.getFullYear(), base.getMonth(), base.getDate() - 1);
        const monthChanged = (prev.getMonth() !== view.getMonth()) || (prev.getFullYear() !== view.getFullYear());
        if (monthChanged) { view = new Date(prev.getFullYear(), prev.getMonth(), 1); render(); }
    });

    // toggle mois
    document.getElementById('calToggle')?.addEventListener('click', () => {
        const wrap = document.getElementById('calWrap');
        const on = !wrap.classList.contains('collapsed');
        wrap.classList.toggle('collapsed', !!on);
        document.getElementById('calToggle').setAttribute('aria-expanded', (!on).toString());
        render();
    });

    // init
    (function autoSize(){
        const box = document.getElementById('agendaBox');
        function sizeAgendaBox() {
            if (!box) return;
            const rect = box.getBoundingClientRect();
            const max = window.innerHeight - rect.top - 12;
            box.style.maxHeight = Math.max(200, max) + 'px';
        }
        box?.addEventListener('wheel', (e) => {
            const el = box, delta = e.deltaY;
            const atTop = el.scrollTop <= 0;
            const atBottom = Math.ceil(el.scrollTop + el.clientHeight) >= el.scrollHeight;
            if ((delta < 0 && !atTop) || (delta > 0 && !atBottom)) { e.preventDefault(); el.scrollTop += delta; }
        }, {passive: false});
        window.addEventListener('resize', sizeAgendaBox);
        window.addEventListener('load', () => setTimeout(sizeAgendaBox, 0));
        sizeAgendaBox();
    })();

    // première peinture
    render();
}
