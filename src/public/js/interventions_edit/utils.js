// utils.js (module)
export function withBtnLock(btn, fn) {
    if (!btn) return fn();
    if (btn.dataset.lock === '1') return;
    btn.dataset.lock = '1';
    const prevDisabled = btn.disabled;
    btn.disabled = true;

    let res;
    try { res = fn(); }
    catch (e) { btn.dataset.lock = ''; btn.disabled = prevDisabled; throw e; }

    if (res && typeof res.then === 'function') {
        return res.finally(() => { btn.dataset.lock = ''; btn.disabled = prevDisabled; });
    } else {
        btn.dataset.lock = '';
        btn.disabled = prevDisabled;
        return res;
    }
}

export const pad = n => (n < 10 ? '0' : '') + n;

export function hoursOnly(iso) {
    const dt = new Date(iso);
    return `${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
}

export function escapeHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
