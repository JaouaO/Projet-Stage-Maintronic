(function () {
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
            statusEl.textContent = 'Édition en cours…';
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
        counterEl.style.color = (val.length > MAX * 0.9) ? '#b45309' : '#657089';
    }

    function placeCaretAtEnd(node){
        const range = document.createRange();
        const sel = window.getSelection();
        range.selectNodeContents(node);
        range.collapse(false);
        sel.removeAllRanges();
        sel.addRange(range);
    }

    // État initial garanti (lecture seule + bons boutons)
    setEditing(false);

    btnEdit.addEventListener('click', () => setEditing(true));

    btnCancel.addEventListener('click', () => {
        elNote.textContent = initialValue;
        setEditing(false);
    });

    elNote.addEventListener('input', updateCounter);

    btnSave.addEventListener('click', async () => {
        const newValue = getText();
        if (newValue.length > MAX){
            statusEl.textContent = 'Échec de l’enregistrement (limite 1000 caractères).';
            return;
        }
        statusEl.textContent = 'Enregistrement…';
        try{
            const res = await fetch(updateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrftoken
                },
                body: JSON.stringify({ id: sessionId, note: newValue })
            });
            const raw = await res.text();
            let data = null; try{ data = JSON.parse(raw); }catch(_){}
            if (!res.ok || !data || data.ok !== true){
                throw new Error((data && data.msg) || 'Échec de l’enregistrement');
            }
            initialValue = newValue;
            statusEl.textContent = 'Enregistré ✔';
            setEditing(false);
            setTimeout(()=> (statusEl.textContent = ''), 1500);
        }catch(e){
            statusEl.textContent = 'Échec de l’enregistrement';
            console.error('Update note failed:', e);
        }
    });

    // Ctrl/Cmd+S pour sauver uniquement si visible
    elNote.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            if (!btnSave.classList.contains('is-hidden')) btnSave.click();
        }
    });
})();
