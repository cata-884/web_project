(function () {
            const urlParams = new URLSearchParams(window.location.search);
            const oauthToken = urlParams.get('token');
            if (oauthToken) {
                localStorage.setItem('cat_token', oauthToken);
                window.history.replaceState({}, '', window.location.pathname);
                window.location.href = 'account/account.html';
                return;
            }

            const oauthError = urlParams.get('error');
            if (oauthError) {
                const errEl = document.getElementById('login-error');
                errEl.textContent = t('auth.error_oauth') + oauthError;
                errEl.style.display = 'block';
            }

            if (localStorage.getItem('cat_token')) {
                window.location.href = 'account/account.html';
            }

            document.getElementById('login-form').addEventListener('submit', async function (e) {
                e.preventDefault();
                const btn = document.getElementById('login-btn');
                const errEl = document.getElementById('login-error');
                const identifier = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value;

                errEl.style.display = 'none';
                btn.disabled = true;
                btn.textContent = t('auth.loading');

                try {
                    const body = { password };
                    if (identifier.includes('@')) {
                        body.email = identifier;
                    } else {
                        body.username = identifier;
                    }
                    const data = await api.post('/api/auth/login', body);
                    localStorage.setItem('cat_token', data.token);
                    localStorage.setItem('cat_user', JSON.stringify(data.user));
                    window.location.href = 'account/account.html';
                } catch (err) {
                    errEl.textContent = err.message || t('auth.error_default');
                    errEl.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = t('auth.btn');
                }
            });
        })();