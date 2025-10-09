(function(){
    const elNote   = document.getElementById('noteInterne');
    if (!elNote) return;

    const btnEdit  = document.getElementById('btnEdit');
    const btnSave  = document.getElementById('btnSave');
    const btnCancel= document.getElementById('btnCancel');
    const statusEl = document.getElementById('noteStatus');

    const updateUrl = elNote.dataset.updateUrl;
    const csrftoken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const sessionId = (window.APP_SESSION_ID || '').toString();

    let initialValue = elNote.textContent;

    function setEditing(on){
        elNote.setAttribute('contenteditable', on ? 'true' : 'false');
        elNote.style.borderStyle = on ? 'solid' : 'dashed';
        btnSave.style.display   = on ? '' : 'none';
        btnCancel.style.display = on ? '' : 'none';
        btnEdit.style.display   = on ? 'none' : '';
        statusEl.textContent    = on ? 'Édition en cours…' : '';
        if (on) elNote.focus();
    }

    btnEdit.addEventListener('click', () => setEditing(true));

    btnCancel.addEventListener('click', () => {
        elNote.textContent = initialValue;
        setEditing(false);
    });

    btnSave.addEventListener('click', async () => {
        const newValue = elNote.textContent.trim();
        statusEl.textContent = 'Enregistrement…';

        try {
            const res   = await fetch(updateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrftoken
                },
                body: JSON.stringify({ id: sessionId, note: newValue })
            });

            const raw   = await res.text();            // <-- on lit d’abord le texte brut
            let data    = null;
            try { data = JSON.parse(raw); } catch (_) { /* ignore */ }

            if (!res.ok || !data || data.ok !== true) {
                const msg = (data && data.msg) ? data.msg : raw || `HTTP ${res.status}`;
                throw new Error(msg);
            }

            initialValue = newValue;
            statusEl.textContent = 'Enregistré ✔';
            setEditing(false);
            setTimeout(()=> statusEl.textContent = '', 1500);

        } catch (e) {
            statusEl.textContent = 'Erreur: ' + (e && e.message ? e.message : 'inconnue');
            console.error('Update note failed:', e);
        }
    });

    // Ctrl/Cmd+S pour sauver
    elNote.addEventListener('keydown', (e)=>{
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            if (btnSave.style.display !== 'none') btnSave.click();
        }
    });
})();
