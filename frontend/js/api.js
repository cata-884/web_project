const API_BASE = window.location.protocol === 'file:' || !window.location.host
    ? 'http://localhost/cat/public'
    : '/cat/public';

const api = {
    async fetch(endpoint, options = {}) {
        const token   = localStorage.getItem('cat_token');
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
            const data   = isJson ? await response.json() : null;
            showToast(data?.error || 'Acces interzis');
            const err = new Error(data?.error || 'Forbidden');
            err.status = 403;
            throw err;
        }

        const isJson = response.headers.get('content-type')?.includes('application/json');
        const data   = isJson ? await response.json() : null;

        if (!response.ok) {
            const err = new Error((data && data.error) || response.statusText);
            err.response = response;
            err.data     = data;
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
    },
};

window.api = api;
