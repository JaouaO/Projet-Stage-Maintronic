// public/js/interventions/rdv.js
import { withBtnLock } from './utils.js';

export function initRDV() {
    const form       = document.getElementById('interventionForm');
    const btnCall    = document.getElementById('btnPlanifierAppel');
    const btnRdv     = document.getElementById('btnPlanifierRdv');
    const btnVal     = document.getElementById('btnValider');
    const actionType = document.getElementById('actionType');
    const numInt     = document.getElementById('openHistory')?.dataset.numInt || '';
    const csrf       = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // --- helpers modale locale (tu peux à la place importer open/close depuis modal.js si tu les exportes)
    const modal     = document.getElementById('infoModal');
    const modalBody = document.getElementById('infoModalBody');
    const modalX    = document.getElementById('infoModalClose');

    function openModal(html) {
        if (!modal || !modalBody) return;
        modalBody.innerHTML = html;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }
    function closeModal() {
        if (!modal || !modalBody) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modalBody.innerHTML = '';
    }
    modalX?.addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

    // --- Nouvel appel (ne crée pas de RDV)
    if (btnCall && form && actionType) {
        btnCall.addEventListener('click', (ev) => {
            withBtnLock(ev.currentTarget, () => {
                actionType.value = 'call';
                form.requestSubmit();
            });
        });
    }

    // --- RDV temporaire
    if (btnRdv) {
        btnRdv.addEventListener('click', async (ev) => {
            withBtnLock(ev.currentTarget, async () => {
                document.getElementById('actionType').value = '';
                const tech = document.getElementById('selAny')?.value || '';
                const date = document.getElementById('dtPrev')?.value || '';
                const time = document.getElementById('tmPrev')?.value || '';
                if (!numInt || !tech || !date || !time) { alert('Sélectionne le technicien, la date et l’heure.'); return; }

                const url = `/interventions/${encodeURIComponent(numInt)}/rdv/temporaire`;
                const res = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({
                        rea_sal: tech, date_rdv: date, heure_rdv: time,
                        code_postal: document.querySelector('input[name="code_postal"]')?.value || null,
                        ville: document.querySelector('input[name="ville"]')?.value || null,
                        commentaire: document.querySelector('#commentaire')?.value || ''
                    }),
                });

                const raw = await res.text();
                let out = null; try { out = JSON.parse(raw); } catch {}

                if (!res.ok || !out || out.ok !== true) {
                    const ct = res.headers.get('content-type') || '';
                    const probable =
                        res.status === 419 ? 'CSRF ou session expirée' :
                            res.status === 401 ? 'Non authentifié' :
                                res.status === 422 ? 'Erreurs de validation' :
                                    res.status === 500 ? 'Erreur serveur (exception)' :
                                        !ct.includes('application/json') ? 'Réponse non-JSON (souvent une page HTML de login)' : 'Inconnue';
                    let details = '';
                    if (out && out.type === 'QueryException') details = `\nSQLSTATE: ${out.sqlstate}\nErrno: ${out.errno}\nMessage: ${out.errmsg}\n@ ${out.file}`;
                    else if (out && out.errmsg) details = `\nMessage: ${out.errmsg}\n@ ${out.file || ''}`;
                    alert(['❌ Création RDV temporaire échouée', `HTTP: ${res.status}`, `Cause probable: ${probable}`, details || (ct.includes('application/json') ? '' : `\nHTML/Corps:\n${raw.slice(0,400)}`)].filter(Boolean).join('\n'));
                    return;
                }

                document.getElementById('selModeTech')?.dispatchEvent(new Event('change'));
                const c = document.querySelector('#commentaire'); if (c) c.value = '';
                alert(out.mode === 'updated' ? 'RDV temporaire mis à jour.' : 'RDV temporaire créé.');
            });
        });
    }

    // --- Valider RDV (avec choix si temporaires existants)
    if (btnVal && form) {
        btnVal.addEventListener('click', (ev) => {
            withBtnLock(ev.currentTarget, async () => {
                document.getElementById('actionType').value = 'validate_rdv';

                const tech  = document.getElementById('selAny')?.value || '';
                const date  = document.getElementById('dtPrev')?.value || '';
                const heure = document.getElementById('tmPrev')?.value || '';

                // manques → submit classique
                if (!numInt || !tech || !date || !heure) { form.requestSubmit(); return; }

                const urlCheck = `/interventions/${encodeURIComponent(numInt)}/rdv/temporaire/check`;
                const urlPurge = `/interventions/${encodeURIComponent(numInt)}/rdv/temporaire/purge`;

                try {
                    const r1 = await fetch(urlCheck, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({})
                    });
                    const j1 = await r1.json().catch(() => ({ ok:false, count:0, items:[] }));
                    const hasTemps = !!(j1 && j1.ok && (j1.count || 0) > 0);
                    if (!hasTemps) { form.requestSubmit(); return; }

                    // HTML sans styles inline → classes CSS
                    const listHtml = (j1.items || []).map(it => {
                        const hhmm = (it.StartTime || '').slice(0,5);
                        const dfr  = (it.StartDate || '').split('-').reverse().join('/');
                        const t    = it.CodeTech || '';
                        const lab  = (it.Label || '').replace(/[<>&"]/g, s => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[s]));
                        return `<li><code>${dfr} ${hhmm}</code> · <strong>${t}</strong> — ${lab}</li>`;
                    }).join('');

                    const html = `
            <div>
              <h3 class="modal-title">RDV temporaires existants</h3>
              <p>Des rendez-vous <em>temporaires</em> sont présents sur ce dossier&nbsp;:</p>
              <ul class="modal-list">
                ${listHtml}
              </ul>
              <p>Que souhaites-tu faire&nbsp;?</p>
              <div class="hstack-8 flex-wrap">
                <button id="optPurgeThenValidate" class="btn" type="button" title="Supprimer tous les temporaires puis valider">Supprimer les temporaires puis valider</button>
                <button id="optValidateAnyway" class="btn" type="button" title="Conserver les temporaires et valider quand même">Valider sans supprimer</button>
                <button id="optCancel" class="btn" type="button" title="Annuler">Annuler</button>
              </div>
            </div>`;
                    openModal(html);

                    // Purger puis valider
                    modalBody.querySelector('#optPurgeThenValidate')?.addEventListener('click', (e) => {
                        withBtnLock(e.currentTarget, async () => {
                            try {
                                const r2 = await fetch(urlPurge, {
                                    method:'POST', credentials:'same-origin',
                                    headers:{ 'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN': csrf },
                                    body: JSON.stringify({})
                                });
                                const j2 = await r2.json().catch(()=>({ ok:false, deleted:0 }));
                                if (!j2.ok) { alert('❌ Échec de la suppression des RDV temporaires.'); return; }
                                document.getElementById('selModeTech')?.dispatchEvent(new Event('change'));
                                closeModal();
                                form.requestSubmit();
                            } catch (err) {
                                console.error('[purge temporaires] erreur', err);
                                alert('❌ Erreur lors de la suppression des RDV temporaires.');
                            }
                        });
                    }, { once:true });

                    // Valider sans supprimer
                    modalBody.querySelector('#optValidateAnyway')?.addEventListener('click', (e) => {
                        withBtnLock(e.currentTarget, () => { closeModal(); form.requestSubmit(); });
                    }, { once:true });

                    // Annuler
                    modalBody.querySelector('#optCancel')?.addEventListener('click', (e) => {
                        withBtnLock(e.currentTarget, () => closeModal());
                    }, { once:true });

                } catch (err) {
                    console.error('[Valider RDV] erreur', err);
                    form.requestSubmit();
                }
            });
        });
    }
}
