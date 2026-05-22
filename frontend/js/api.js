const API_BASE = window.location.protocol === 'file:' || !window.location.host
    ? 'http://localhost/cat/public'
    : '/cat/public';

function showToast(message, type = 'error') {
    const existing = document.getElementById('cat-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'cat-toast';
    toast.style.cssText = [
        'position:fixed', 'top:20px', 'right:20px', 'z-index:9999',
        'padding:12px 20px', 'border-radius:8px', 'color:#fff',
        `background:${type === 'error' ? '#EF6A00' : '#2D4C36'}`,
        'font-size:14px', 'max-width:320px', 'box-shadow:0 4px 12px rgba(0,0,0,.25)',
        'transition:opacity .3s'
    ].join(';');
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
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
