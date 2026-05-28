const API_BASE = window.location.protocol === 'file:' || !window.location.host
    ? 'http://localhost/cat/public'
    : '/cat/public';

(function injectToastStyles() {
    if (document.getElementById('cat-toast-styles')) return;
    const s = document.createElement('style');
    s.id = 'cat-toast-styles';
    s.textContent = `
#cat-toast-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 99999;
    display: flex;
    flex-direction: column-reverse;
    gap: 10px;
    pointer-events: none;
}
.cat-toast {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: #fff;
    border-radius: 10px;
    padding: 13px 14px 13px 16px;
    min-width: 260px;
    max-width: 340px;
    box-shadow: 0 4px 20px rgba(0,0,0,.13), 0 1px 4px rgba(0,0,0,.08);
    border-left: 4px solid #6b7280;
    pointer-events: all;
    position: relative;
    overflow: hidden;
    animation: cat-toast-in .28s cubic-bezier(.22,.68,0,1.2) both;
}
.cat-toast.cat-toast--leaving {
    animation: cat-toast-out .22s ease-in forwards;
}
@keyframes cat-toast-in {
    from { opacity: 0; transform: translateX(40px) scale(.96); }
    to   { opacity: 1; transform: translateX(0)   scale(1);   }
}
@keyframes cat-toast-out {
    from { opacity: 1; transform: translateX(0)   scale(1);   max-height: 120px; margin-bottom: 0; }
    to   { opacity: 0; transform: translateX(40px) scale(.94); max-height: 0;   margin-bottom: -10px; }
}
.cat-toast--success { border-color: #059669; }
.cat-toast--error   { border-color: #dc2626; }
.cat-toast--warning { border-color: #d97706; }
.cat-toast--info    { border-color: #2563eb; }
.cat-toast-icon {
    font-size: 17px;
    line-height: 1;
    flex-shrink: 0;
    margin-top: 1px;
}
.cat-toast-body { flex: 1; }
.cat-toast-msg {
    font-size: 13.5px;
    color: #1f2937;
    line-height: 1.45;
    font-family: inherit;
}
.cat-toast-close {
    background: none;
    border: none;
    color: #9ca3af;
    font-size: 16px;
    cursor: pointer;
    padding: 0 0 0 6px;
    line-height: 1;
    flex-shrink: 0;
    margin-top: -1px;
    transition: color .15s;
}
.cat-toast-close:hover { color: #374151; }
.cat-toast-bar {
    position: absolute;
    bottom: 0; left: 0;
    height: 3px;
    border-radius: 0 0 0 6px;
    animation: cat-toast-bar linear forwards;
}
.cat-toast--success .cat-toast-bar { background: #059669; }
.cat-toast--error   .cat-toast-bar { background: #dc2626; }
.cat-toast--warning .cat-toast-bar { background: #d97706; }
.cat-toast--info    .cat-toast-bar { background: #2563eb; }
@keyframes cat-toast-bar {
    from { width: 100%; }
    to   { width: 0%; }
}
.cat-confirm-overlay {
    position: fixed; inset: 0; z-index: 999998;
    background: rgba(0,0,0,.35);
    backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    animation: cat-cfm-fade .18s ease both;
}
@keyframes cat-cfm-fade {
    from { opacity: 0; }
    to   { opacity: 1; }
}
.cat-confirm-box {
    background: #fff;
    border-radius: 14px;
    padding: 28px 28px 22px;
    max-width: 380px;
    width: 90%;
    box-shadow: 0 16px 48px rgba(0,0,0,.18);
    animation: cat-cfm-up .22s cubic-bezier(.22,.68,0,1.15) both;
    font-family: inherit;
}
@keyframes cat-cfm-up {
    from { opacity: 0; transform: translateY(16px) scale(.97); }
    to   { opacity: 1; transform: translateY(0)   scale(1);   }
}
.cat-confirm-icon {
    font-size: 28px;
    margin-bottom: 10px;
    display: block;
}
.cat-confirm-title {
    font-size: 16px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 7px;
}
.cat-confirm-msg {
    font-size: 13.5px;
    color: #6b7280;
    line-height: 1.5;
    margin-bottom: 22px;
}
.cat-confirm-btns {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
.cat-confirm-btns button {
    padding: 8px 20px;
    border-radius: 8px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    border: 1.5px solid transparent;
    transition: opacity .14s, background .14s;
}
.cat-cfm-cancel {
    background: #f3f4f6;
    color: #374151;
    border-color: #e5e7eb !important;
}
.cat-cfm-cancel:hover { background: #e5e7eb; }
.cat-cfm-ok {
    background: #dc2626;
    color: #fff;
}
.cat-cfm-ok:hover { opacity: .88; }
.cat-cfm-ok--warning { background: #d97706 !important; }
.cat-cfm-ok--info    { background: #2563eb !important; }
.cat-cfm-ok--success { background: #059669 !important; }
`;
    document.head.appendChild(s);
})();

const _TOAST_ICONS = { success: '', error: '', warning: '', info: '' };
const _TOAST_DURATION = { success: 3500, error: 5000, warning: 4000, info: 4000 };

function _getToastContainer() {
    let c = document.getElementById('cat-toast-container');
    if (!c) { c = document.createElement('div'); c.id = 'cat-toast-container'; document.body.appendChild(c); }
    return c;
}

function showToast(message, type = 'error') {
    const container = _getToastContainer();
    const duration  = _TOAST_DURATION[type] ?? 4000;

    const el = document.createElement('div');
    el.className = `cat-toast cat-toast--${type}`;
    el.innerHTML = `
        <span class="cat-toast-icon">${_TOAST_ICONS[type] ?? ''}</span>
        <span class="cat-toast-body"><span class="cat-toast-msg">${String(message).replace(/</g,'&lt;')}</span></span>
        <button class="cat-toast-close" aria-label="Inchide"></button>
        <span class="cat-toast-bar" style="animation-duration:${duration}ms"></span>
    `;

    const dismiss = () => {
        el.classList.add('cat-toast--leaving');
        el.addEventListener('animationend', () => el.remove(), { once: true });
    };
    el.querySelector('.cat-toast-close').addEventListener('click', dismiss);
    container.appendChild(el);
    setTimeout(dismiss, duration);
}

function showConfirm(message, { title = 'Confirmare', confirmText = 'Confirma', type = 'error' } = {}) {
    return new Promise(resolve => {
        const overlay = document.createElement('div');
        overlay.className = 'cat-confirm-overlay';
        const iconMap = { error: '', warning: '️', info: '️', success: '' };
        overlay.innerHTML = `
            <div class="cat-confirm-box" role="dialog" aria-modal="true">
                <span class="cat-confirm-icon">${iconMap[type] ?? ''}</span>
                <div class="cat-confirm-title">${title}</div>
                <div class="cat-confirm-msg">${String(message).replace(/</g,'&lt;')}</div>
                <div class="cat-confirm-btns">
                    <button class="cat-cfm-cancel">Anuleaza</button>
                    <button class="cat-cfm-ok cat-cfm-ok--${type}">${confirmText}</button>
                </div>
            </div>
        `;
        const close = (val) => { overlay.remove(); resolve(val); };
        overlay.querySelector('.cat-cfm-cancel').addEventListener('click', () => close(false));
        overlay.querySelector('.cat-cfm-ok').addEventListener('click', () => close(true));
        overlay.addEventListener('click', e => { if (e.target === overlay) close(false); });
        document.body.appendChild(overlay);
        overlay.querySelector('.cat-cfm-ok').focus();
    });
}

const api = {
    async fetch(endpoint, options = {}) {
        const token = localStorage.getItem('cat_token');
        const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
        if (token) headers['Authorization'] = `Bearer ${token}`;

        const response = await fetch(`${API_BASE}${endpoint}`, { ...options, headers });

        if (response.status === 401) {
            localStorage.removeItem('cat_token');
            localStorage.removeItem('cat_user');
            if (!window.location.pathname.includes('auth.html')) {
                const parts = window.location.pathname.split('/').filter(Boolean);
                const depth = parts.length - parts.indexOf('frontend') - 1;
                const prefix = depth > 1 ? '../'.repeat(depth - 1) : '';
                window.location.href = `${prefix}pages/auth.html`;
            }
            return null;
        }

        if (response.status === 403) {
            const isJson = response.headers.get('content-type')?.includes('application/json');
            const data = isJson ? await response.json() : null;
            showToast(data?.error || 'Acces interzis');
            const err = new Error(data?.error || 'Forbidden');
            err.status = 403;
            throw err;
        }

        const isJson = response.headers.get('content-type')?.includes('application/json');
        const data = isJson ? await response.json() : null;

        if (!response.ok) {
            const err = new Error((data && data.error) || response.statusText);
            err.response = response;
            err.data = data;
            throw err;
        }

        return data;
    },

    get(endpoint, options = {}) {
        return this.fetch(endpoint, { ...options, method: 'GET' });
    },

    post(endpoint, body, options = {}) {
        return this.fetch(endpoint, { ...options, method: 'POST', body: JSON.stringify(body) });
    },

    patch(endpoint, body, options = {}) {
        return this.fetch(endpoint, { ...options, method: 'PATCH', body: JSON.stringify(body) });
    },

    delete(endpoint, options = {}) {
        return this.fetch(endpoint, { ...options, method: 'DELETE' });
    }
};

window.api = api;
window.showToast = showToast;
window.showConfirm = showConfirm;
