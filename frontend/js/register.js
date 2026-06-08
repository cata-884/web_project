(function() {
    if (localStorage.getItem('cat_token')) {
        window.location.href = 'account/account.html';
    }

    document.getElementById('register-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('register-btn');
        const errEl = document.getElementById('register-error');

        const username = document.getElementById('username').value.trim();
        const fullname = document.getElementById('fullname').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm-password').value;
        const termsChecked = document.getElementById('terms').checked;

        errEl.style.display = 'none';

        if (username.length < 3) {
            errEl.textContent = t('register.error_username');
            errEl.style.display = 'block';
            return;
        }
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email)) {
            errEl.textContent = t('register.error_email');
            errEl.style.display = 'block';
            return;
        }
        if (password.length < 8) {
            errEl.textContent = t('register.error_pass_short');
            errEl.style.display = 'block';
            return;
        }
        if (password !== confirmPassword) {
            errEl.textContent = t('register.error_pass_match');
            errEl.style.display = 'block';
            return;
        }
        if (!termsChecked) {
            errEl.textContent = t('register.error_terms');
            errEl.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.textContent = t('register.loading');

        try {
            const body = {
                username,
                email,
                password
            };
            if (fullname) body.full_name = fullname;

            /** @type {{ token: string, user: object }} */
            const data = await api.post('/api/auth/register', body);
            localStorage.setItem('cat_token', data.token);
            localStorage.setItem('cat_user', JSON.stringify(data.user));
            window.location.href = 'account/account.html';
        } catch (err) {
            errEl.textContent = err.message || t('register.error_default');
            errEl.style.display = 'block';
            btn.disabled = false;
            btn.textContent = t('register.btn');
        }
    });
})();