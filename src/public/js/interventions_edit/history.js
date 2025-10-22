// history.js
export function initHistory() {
    const btn = document.getElementById('openHistory');
    if (!btn) return;

    btn.addEventListener('click', () => {
        const tpl = document.getElementById('tplHistory');
        if (!tpl) { console.error('[HIST] template #tplHistory introuvable'); return; }

        const w = window.open('', 'historique_' + Date.now(), 'width=960,height=720');
        if (!w) { console.error('[HIST] window.open a été bloqué'); return; }

        let inner = '';
        if (tpl.content && tpl.content.cloneNode) {
            const frag = tpl.content.cloneNode(true);
            inner = (frag.firstElementChild?.outerHTML || frag.textContent || '').trim();
        } else {
            inner = (tpl.innerHTML || '').trim();
        }
        if (!inner) inner = '<p class="note">Aucun contenu trouvé pour l’historique.</p>';

        // On réutilise la classe .table existante
        inner = inner.replace(/class="hist-table"/g, 'class="table"');

        const cssHref = document.querySelector('link[rel="stylesheet"][href*="intervention_edit.css"]')?.href || '';

        const html = `<!doctype html>
<html lang="fr"><head>
<meta charset="utf-8"><title>Historique</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
${cssHref ? `<link rel="stylesheet" href="${cssHref}">` : ''}
</head><body>
  <div class="box m-12">
    <div class="body">
      <div class="table">
        ${inner}
      </div>
    </div>
  </div>
  <script>
    document.addEventListener('click', function(e){
      const btn = e.target.closest('.hist-toggle'); if(!btn) return;
      const tr = btn.closest('tr'); const next = tr && tr.nextElementSibling;
      if(!next || !next.matches('.row-details')) return;

      // État réel = style calculé, pas inline
      const isOpen = getComputedStyle(next).display !== 'none';
      next.style.display = isOpen ? 'none' : 'table-row';

      btn.textContent = isOpen ? '+' : '−';
      btn.setAttribute('aria-expanded', String(!isOpen));
    });
  <\/script>
</body></html>`;

        try { w.document.open(); w.document.write(html); w.document.close(); }
        catch (err) { console.error('[HIST] Erreur écriture document:', err); }
    });
}
