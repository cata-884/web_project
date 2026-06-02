const _TOAST_ICONS    = { success: '', error: '', warning: '', info: '' };
const _TOAST_DURATION = { success: 3500, error: 5000, warning: 4000, info: 4000 };
const _CONFIRM_ICONS  = { error: '', warning: '️', info: '️', success: '' };

function _getToastContainer() {
    let c = document.getElementById('cat-toast-container');
    if (!c) {
        c = document.createElement('div');
        c.id = 'cat-toast-container';
        document.body.appendChild(c);
    }
    return c;
}

function showToast(message, type = 'error') {
    const duration = _TOAST_DURATION[type] ?? 4000;

    const icon = document.createElement('span');
    icon.className   = 'cat-toast-icon';
    icon.textContent = _TOAST_ICONS[type] ?? '';

    const msg = document.createElement('span');
    msg.className   = 'cat-toast-msg';
    msg.textContent = message;

    const body = document.createElement('span');
    body.className = 'cat-toast-body';
    body.appendChild(msg);

    const closeBtn = document.createElement('button');
    closeBtn.className = 'cat-toast-close';
    closeBtn.setAttribute('aria-label', 'Inchide');
    closeBtn.textContent = '×';

    const bar = document.createElement('span');
    bar.className = 'cat-toast-bar';
    bar.style.animationDuration = `${duration}ms`;

    const el = document.createElement('div');
    el.className = `cat-toast cat-toast--${type}`;
    el.append(icon, body, closeBtn, bar);

    const dismiss = () => {
        el.classList.add('cat-toast--leaving');
        el.addEventListener('animationend', () => el.remove(), { once: true });
    };
    closeBtn.addEventListener('click', dismiss);
    _getToastContainer().appendChild(el);
    setTimeout(dismiss, duration);
}

function showConfirm(message, { title = 'Confirmare', confirmText = 'Confirma', type = 'error' } = {}) {
    return new Promise(resolve => {
        const iconEl = document.createElement('span');
        iconEl.className   = 'cat-confirm-icon';
        iconEl.textContent = _CONFIRM_ICONS[type] ?? '';

        const titleEl = document.createElement('div');
        titleEl.className   = 'cat-confirm-title';
        titleEl.textContent = title;

        const msgEl = document.createElement('div');
        msgEl.className   = 'cat-confirm-msg';
        msgEl.textContent = message;

        const cancelBtn = document.createElement('button');
        cancelBtn.className   = 'cat-cfm-cancel';
        cancelBtn.textContent = 'Anuleaza';

        const okBtn = document.createElement('button');
        okBtn.className   = `cat-cfm-ok cat-cfm-ok--${type}`;
        okBtn.textContent = confirmText;

        const btns = document.createElement('div');
        btns.className = 'cat-confirm-btns';
        btns.append(cancelBtn, okBtn);

        const box = document.createElement('div');
        box.className = 'cat-confirm-box';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.append(iconEl, titleEl, msgEl, btns);

        const overlay = document.createElement('div');
        overlay.className = 'cat-confirm-overlay';
        overlay.appendChild(box);

        const close = val => { overlay.remove(); resolve(val); };
        cancelBtn.addEventListener('click', () => close(false));
        okBtn.addEventListener('click',     () => close(true));
        overlay.addEventListener('click', e => { if (e.target === overlay) close(false); });

        document.body.appendChild(overlay);
        okBtn.focus();
    });
}

window.showToast   = showToast;
window.showConfirm = showConfirm;
