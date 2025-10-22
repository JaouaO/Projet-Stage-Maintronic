// horloge.js
export function initHorloge() {
    const el = document.getElementById('srvDateTimeText');
    if (!el) return;

    // base = serveur ; offset pour corriger l’horloge client
    const base = new Date((window.APP && window.APP.serverNow) || Date.now());
    const offsetMs = base.getTime() - Date.now();

    const pad = n => (n < 10 ? '0' : '') + n;
    const now = () => new Date(Date.now() + offsetMs);

    function format(d){
        const dateTxt = `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
        const timeTxt = `${pad(d.getHours())}:${pad(d.getMinutes())}`; // minutes uniquement
        return `${dateTxt} ${timeTxt}`;
    }

    function draw(){
        el.textContent = format(now());
    }

    function scheduleNextTick(){
        const t = now();
        // ms jusqu’à la *prochaine minute* pile (précision à la seconde)
        const ms = (60 - t.getSeconds())*1000 - t.getMilliseconds();
        setTimeout(() => {
            draw();
            scheduleNextTick(); // chainé : très peu de CPU
        }, Math.max(0, ms));
    }

    // Premier rendu immédiat, puis on s’aligne sur la minute suivante
    draw();
    scheduleNextTick();

    // En sortie de veille / changement d’onglet, on rafraîchit
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) draw();
    });
}
