// public/js/interventions_create.js
(() => {
    const $ = (s, ctx) => (ctx || document).querySelector(s);

    function ready(fn) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", fn);
        } else {
            fn();
        }
    }

    // Récupère l'URL de suggestion depuis:
    // 1) <meta name="suggest-endpoint">
    // 2) window.CREATE_INTERVENTION_SUGGEST_URL
    // 3) data-suggest sur la balise <script src="...">
    function getSuggestUrl() {
        const meta = document.querySelector('meta[name="suggest-endpoint"]')?.content;
        if (meta) return meta;

        if (typeof window.CREATE_INTERVENTION_SUGGEST_URL === "string" && window.CREATE_INTERVENTION_SUGGEST_URL.length > 0) {
            return window.CREATE_INTERVENTION_SUGGEST_URL;
        }

        // cherche le script courant pour lire data-suggest (au cas où)
        const scripts = document.querySelectorAll('script[src*="interventions_create.js"]');
        for (const s of scripts) {
            const ds = s.getAttribute("data-suggest");
            if (ds) return ds;
        }
        return ""; // rien trouvé
    }

    ready(() => {
        const agenceSel = $("#Agence");
        const dateInput = $("#DateIntPrevu");
        const numField  = $("#NumInt");

        if (!agenceSel || !numField) return;

        const suggestEndpoint = getSuggestUrl();
        if (!suggestEndpoint) {
            // Pas d'URL: on sort silencieusement
            return;
        }

        let aborter = null;
        let debounceTimer = null;
        let reqSeq = 0; // anti course

        const refreshNum = () => {
            const ag = agenceSel.value;
            const d  = dateInput ? dateInput.value : "";

            if (!ag) return;

            // Annule la requête précédente si en cours
            if (aborter) aborter.abort();
            aborter = new AbortController();

            const seq = ++reqSeq;
            const url = new URL(suggestEndpoint, window.location.origin);
            url.searchParams.set("agence", ag);
            if (d) url.searchParams.set("date", d);

            fetch(url.toString(), {
                headers: { "X-Requested-With": "XMLHttpRequest" },
                signal: aborter.signal
            })
                .then(r => (r.ok ? r.json() : Promise.reject(new Error("HTTP " + r.status))))
                .then(js => {
                    if (seq !== reqSeq) return;              // une requête plus récente a répondu
                    if (js && js.ok && js.numInt && numField) {
                        numField.value = js.numInt;
                    }
                })
                .catch(err => {
                    if (err.name === "AbortError") return;   // normal
                    // Silencieux: ne bloque pas le flux utilisateur
                    // console.warn("Suggest error", err);
                });
        };

        const debounced = () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(refreshNum, 150);
        };

        // Écoutes: change sur agence et date + input pour saisir rapidement la date
        agenceSel.addEventListener("change", debounced);
        if (dateInput) {
            dateInput.addEventListener("change", debounced);
            dateInput.addEventListener("input", debounced);
        }
    });
})();
