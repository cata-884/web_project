const API_BASE_URL = '/cat/public';

const api = {
    async fetch(endpoint, options = {}) {
        const token = localStorage.getItem('token');
        const headers = {
            'Content-Type': 'application/json',
            ...(options.headers || {})
        };

        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        const config = {
            ...options,
            headers
        };

        const response = await fetch(`${API_BASE_URL}${endpoint}`, config);

        if (response.status === 401) {
            localStorage.removeItem('token');
            // Check if we are already on the auth page to avoid redirect loops
            if (!window.location.pathname.includes('auth.html')) {
                // Adjust path depending on current location depth
                const depth = window.location.pathname.split('/').length - 3;
                const prefix = depth > 0 ? '../'.repeat(depth) : '';
                window.location.href = `${prefix}pages/auth.html`;
            }
        }

        const isJson = response.headers.get('content-type')?.includes('application/json');
        const data = isJson ? await response.json() : null;

        if (!response.ok) {
            const error = new Error((data && data.error) || response.statusText);
            error.response = response;
            error.data = data;
            throw error;
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
