document.addEventListener('DOMContentLoaded', () => {

    // CARD SLIDER (landing / about page)
    const cards = document.querySelectorAll('.about-cards-stack .feature-card');
    let positions = ['pos-0', 'pos-1', 'pos-2'];

    if (cards.length > 0) {
        cards.forEach(card => {
            card.addEventListener('click', (e) => {
                if (!card.classList.contains('pos-0')) {
                    e.preventDefault();
                    cards.forEach(c => c.classList.remove('pos-0', 'pos-1', 'pos-2'));
                    positions.unshift(positions.pop());
                    cards.forEach((c, i) => c.classList.add(positions[i]));
                }
            });
        });
    }

    // TEAM TOGGLE (about page)
    const btnLuciu = document.getElementById('btn-luciu');
    const btnCatalin = document.getElementById('btn-catalin');
    const contentLuciu = document.getElementById('content-luciu');
    const contentCatalin = document.getElementById('content-catalin');

    if (btnLuciu && btnCatalin && contentLuciu && contentCatalin) {
        btnCatalin.addEventListener('click', () => {
            btnCatalin.classList.add('active');
            btnLuciu.classList.remove('active');
            contentCatalin.classList.add('active-content');
            contentLuciu.classList.remove('active-content');
        });
        btnLuciu.addEventListener('click', () => {
            btnLuciu.classList.add('active');
            btnCatalin.classList.remove('active');
            contentLuciu.classList.add('active-content');
            contentCatalin.classList.remove('active-content');
        });
    }

    // THEME TOGGLE
    const themeButtons = document.querySelectorAll('.theme-toggle .toggle-btn');
    if (themeButtons.length > 0) {
        themeButtons.forEach(button => {
            button.addEventListener('click', () => {
                themeButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
            });
        });
    }

 // TAB SYSTEM (account page)
    const accountTabBtns = document.querySelectorAll('.account-tabs .nav-item[data-tab]');
    const accountSections = document.querySelectorAll('.tab-section');

    if (accountTabBtns.length > 0 && accountSections.length > 0) {
        accountTabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                //Mutam clasa 'active' pe butonul apasat
                accountTabBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                //ASCUNDEM FORTAT absolut toate sectiunile
                accountSections.forEach(section => {
                    section.classList.remove('active-section');
                    section.style.display = 'none';
                });

                //AFISAM FORTAT doar sectiunea de care avem nevoie
                const targetId = btn.getAttribute('data-tab');
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    targetElement.classList.add('active-section');
                    targetElement.style.display = 'block'; // Aici e cheia!
                }
            });
        });
    }
    // BOOKINGS (real API)
    let allBookings = [];

    const STATUS_LABELS = () => ({
        pending: t('account.filter_upcoming'),
        confirmed: t('account.filter_upcoming'),
        cancelled: t('account.filter_cancelled'),
        completed: t('account.filter_completed'),
    });

    function bookingFilterClass(status) {
        return (status === 'pending' || status === 'confirmed') ? 'upcoming' : status;
    }

    function formatDateRange(checkIn, checkOut, guests) {
        const opts = { day: 'numeric', month: 'short', year: 'numeric' };
        const from = new Date(checkIn + 'T00:00:00').toLocaleDateString('ro-RO', opts);
        const to = new Date(checkOut + 'T00:00:00').toLocaleDateString('ro-RO', opts);
        return `${from} – ${to} • ${guests} adult${guests !== 1 ? 'i' : ''}`;
    }

    function renderBookings(bookingsArray) {
        const container = document.getElementById('bookings-container');
        if (!container) return;

        if (bookingsArray.length === 0) {
            container.innerHTML = `<p style="color:#666; font-style:italic; padding:16px 0;">${t('account.no_bookings')}</p>`;
            return;
        }

        container.innerHTML = bookingsArray.map(booking => {
            const filterCls = bookingFilterClass(booking.status);
            const label = STATUS_LABELS()[booking.status] || booking.status;
            const dates = formatDateRange(booking.check_in, booking.check_out, booking.guests);
            return `
                <div class="booking-card status-${filterCls}" onclick="openBookingDetails(${booking.id})">
                    <div class="booking-img" style="background:#e8ede8; flex-shrink:0;"></div>
                    <div class="booking-info">
                        <h3>${booking.camping_name}</h3>
                        <p class="booking-dates">${dates}</p>
                        <span class="booking-status">${label}</span>
                    </div>
                    <div class="booking-arrow"></div>
                </div>
            `;
        }).join('');
    }

    async function loadBookings() {
        const container = document.getElementById('bookings-container');
        if (!container) return;
        if (typeof api === 'undefined') return;

        try {
            const data = await api.get('/api/bookings');
            allBookings = data?.bookings || [];
            renderBookings(allBookings);
        } catch (err) {
            container.innerHTML = `<p style="color:#666; font-style:italic; padding:16px 0;">${t('account.load_bookings_err')}</p>`;
        }
    }

    // FILTER BUTTONS
    const filterBtns = document.querySelectorAll('.history-filters .filter-btn');
    if (filterBtns.length > 0) {
        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                filterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const filterValue = btn.getAttribute('data-filter');
                let filtered;
                if (filterValue === 'all') {
                    filtered = allBookings;
                } else if (filterValue === 'upcoming') {
                    filtered = allBookings.filter(b => b.status === 'pending' || b.status === 'confirmed');
                } else {
                    filtered = allBookings.filter(b => b.status === filterValue);
                }
                renderBookings(filtered);
            });
        });
    }

    // BOOKING DETAILS PANEL
    window.openBookingDetails = function (id) {
        const booking = allBookings.find(b => b.id === id);
        if (!booking) return;

        const imgEl = document.getElementById('bd-image');
        if (imgEl) {
            imgEl.style.display = 'none';
        }

        const nameEl = document.getElementById('bd-name');
        const datesEl = document.getElementById('bd-dates');
        const statusEl = document.getElementById('bd-status');

        if (nameEl) nameEl.textContent = booking.camping_name;
        if (datesEl) datesEl.textContent = formatDateRange(booking.check_in, booking.check_out, booking.guests);

        if (statusEl) {
            const label = STATUS_LABELS()[booking.status] || booking.status;
            statusEl.textContent = label;
            statusEl.style.backgroundColor = '';
            statusEl.style.color = 'white';
            if (booking.status === 'pending') statusEl.style.backgroundColor = '#84AC00';
            if (booking.status === 'confirmed') statusEl.style.backgroundColor = '#84AC00';
            if (booking.status === 'completed') statusEl.style.backgroundColor = '#2D4C36';
            if (booking.status === 'cancelled') statusEl.style.backgroundColor = '#EF6A00';
        }

        const reviewSection = document.getElementById('review-section');
        if (reviewSection) {
            reviewSection.style.display = (booking.status === 'completed') ? 'flex' : 'none';
        }

        document.querySelectorAll('.tab-section').forEach(section => section.classList.remove('active-section'));
        document.getElementById('booking-details').classList.add('active-section');

        document.querySelectorAll('.account-tabs .nav-item').forEach(b => b.classList.remove('active'));
        const historyBtn = document.querySelector('.account-tabs .nav-item[data-tab="history"]');
        if (historyBtn) historyBtn.classList.add('active');
    };

    const backBtn = document.getElementById('back-to-history');
    if (backBtn) {
        backBtn.addEventListener('click', e => {
            e.preventDefault();
            const historyBtn = document.querySelector('.account-tabs .nav-item[data-tab="history"]');
            if (historyBtn) historyBtn.click();
        });
    }

    // STAR RATING (review)
    const stars = document.querySelectorAll('.stars span');
    let selectedRating = 0;

    if (stars.length > 0) {
        stars.forEach(star => {
            star.addEventListener('mouseover', function () {
                const value = this.getAttribute('data-value');
                stars.forEach(s => {
                    s.style.color = s.getAttribute('data-value') <= value ? '#EF6A00' : '#ccc';
                });
            });
            star.addEventListener('mouseout', function () {
                stars.forEach(s => {
                    s.style.color = s.getAttribute('data-value') <= selectedRating ? '#EF6A00' : '#ccc';
                });
            });
            star.addEventListener('click', function () {
                selectedRating = this.getAttribute('data-value');
            });
        });
    }

    // WISHLIST (real API - sectiunea Favorite)
    let favoritesSectionId = null;

    const TYPE_LABELS_WL = { tent: 'Cort', glamping: 'Glamping', rv: 'Autorulotă', cabin: 'Căsuță', wild: 'Sălbatic' };

    function renderWishlist(campings) {
        const container = document.getElementById('wishlist-container');
        if (!container) return;

        if (!campings || campings.length === 0) {
            container.innerHTML = `<p style="color:#666; font-style:italic; padding:16px 0;">${t('account.no_favorites')}</p>`;
            return;
        }

        container.innerHTML = campings.map(c => `
            <div class="wishlist-card" onclick="goToCampingPage('${c.slug}')">
                <div class="wishlist-img" style="background:#e8ede8; flex-shrink:0;"></div>
                <div class="wishlist-info">
                    <h3>${c.name}</h3>
                    <p class="wishlist-address">${c.region || 'Romania'}</p>
                    <p class="wishlist-meta">${TYPE_LABELS_WL[c.type] || c.type} &nbsp;•&nbsp; ${c.price_per_night ? c.price_per_night + ' RON / noapte' : ''}</p>
                </div>
                <div class="wishlist-heart" onclick="removeFromWishlist(event, ${c.id})" title="Sterge din Favorite">×</div>
            </div>
        `).join('');
    }

    async function loadWishlist() {
        const container = document.getElementById('wishlist-container');
        if (!container) return;
        if (typeof api === 'undefined') return;

        try {
            const data = await api.get('/api/sections');
            const sections = data?.sections || [];
            const favSection = sections.find(s => s.name === 'Favorite');

            if (!favSection) {
                renderWishlist([]);
                return;
            }

            favoritesSectionId = favSection.id;
            const campData = await api.get(`/api/sections/${favSection.id}/campings`);
            renderWishlist(campData?.campings || []);
        } catch (err) {
            const container = document.getElementById('wishlist-container');
            if (container) container.innerHTML = `<p style="color:#666; font-style:italic; padding:16px 0;">${t('account.load_favorites_err')}</p>`;
        }
    }

    window.goToCampingPage = function (slug) {
        window.location.href = `../camping.html?slug=${encodeURIComponent(slug)}`;
    };

    window.removeFromWishlist = async function (event, campingId) {
        event.stopPropagation();
        if (!favoritesSectionId) return;
        try {
            await api.delete(`/api/sections/${favoritesSectionId}/campings/${campingId}`);
            loadWishlist();
        } catch (err) {
            if (typeof showToast !== 'undefined') showToast(t('account.remove_fav_err'));
        }
    };


   function populateUserPill(user) {
    const nameEl   = document.getElementById('user-pill-name');
    const emailEl  = document.getElementById('user-pill-email');
    const avatarEl = document.getElementById('user-pill-avatar');

    if (nameEl)   nameEl.textContent = user.full_name || user.username || '—';
    if (emailEl)  emailEl.textContent = user.email || '';

    if (avatarEl && user.avatar_url) {
        // Verificam daca are deja prefixul ca sa nu il punem de doua ori din greseala
        if (user.avatar_url.startsWith('/cat/public/')) {
            avatarEl.src = user.avatar_url;
        } else {
            // Daca ii lipseste (cum se intampla acum), il lipim noi aici
            avatarEl.src = '/cat/public/' + user.avatar_url;
        }
    }
}
    function populateProfileForm(user) {
        const usernameInput = document.getElementById('profile-username');
        const fullnameInput = document.getElementById('profile-fullname');
        const emailInput = document.getElementById('profile-email');

        if (usernameInput) usernameInput.value = user.username || '';
        if (fullnameInput) fullnameInput.value = user.full_name || '';
        if (emailInput) emailInput.value = user.email || '';

        // Settings tab — email + id
        const settingsEmail = document.querySelector('#settings input[type="email"]');
        const settingsId = document.querySelector('#settings input[readonly]');
        if (settingsEmail) settingsEmail.value = user.email || '';
        if (settingsId) settingsId.value = '#' + user.id;
    }

    async function loadUser() {
        if (!document.getElementById('user-pill-name')) return;
        if (typeof api === 'undefined') return;

        // Afiseaza din cache
        const cached = localStorage.getItem('cat_user');
        if (cached) {
            try {
                const user = JSON.parse(cached);
                populateUserPill(user);
                populateProfileForm(user);
            } catch (_) { }
        }

        try {
            const data = await api.get('/api/auth/me');
            if (!data) return; // 401 redirect prin api.js
            localStorage.setItem('cat_user', JSON.stringify(data.user));
            populateUserPill(data.user);
            populateProfileForm(data.user);
        } catch (err) { /* 401/403 prin api.js */ }
    }

    // SAVE PROFILE
    const saveProfileBtn = document.getElementById('save-profile-btn');
    if (saveProfileBtn) {
        saveProfileBtn.addEventListener('click', async () => {
            const msgEl = document.getElementById('profile-msg');
            const usernameVal = (document.getElementById('profile-username')?.value || '').trim();
            const fullnameVal = (document.getElementById('profile-fullname')?.value || '').trim();

            saveProfileBtn.disabled = true;
            saveProfileBtn.textContent = t('account.saving');
            if (msgEl) msgEl.style.display = 'none';

            try {
                const body = {};
                if (usernameVal) body.username = usernameVal;
                if (fullnameVal) body.full_name = fullnameVal;

                const data = await api.patch('/api/users/me', body);
                localStorage.setItem('cat_user', JSON.stringify(data.user));
                populateUserPill(data.user);

                saveProfileBtn.textContent = t('account.saved');
                if (msgEl) {
                    msgEl.textContent = t('account.save_ok');
                    msgEl.style.color = '#2D4C36';
                    msgEl.style.display = 'block';
                }
                setTimeout(() => {
                    saveProfileBtn.disabled = false;
                    saveProfileBtn.textContent = t('account.nav_settings');
                    if (msgEl) msgEl.style.display = 'none';
                }, 2500);
            } catch (err) {
                saveProfileBtn.disabled = false;
                saveProfileBtn.textContent = t('account.nav_settings');
                if (msgEl) {
                    msgEl.textContent = err.message || t('account.save_err');
                    msgEl.style.color = '#EF6A00';
                    msgEl.style.display = 'block';
                }
            }
        });
    }

    // LOGOUT
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async () => {
            try {
                if (typeof api !== 'undefined') await api.post('/api/auth/logout', {});
            } catch (_) { /* ignoram erori de server */ }
            localStorage.removeItem('cat_token');
            localStorage.removeItem('cat_user');
            window.location.href = '../auth.html';
        });
    }

    // ACCORDION (profil)
    const accordions = document.querySelectorAll('.accordion-header');
    if (accordions.length > 0) {
        accordions.forEach(acc => {
            acc.addEventListener('click', function () {
                const icon = this.querySelector('.acc-icon');
                const content = this.nextElementSibling;
                if (content.classList.contains('show')) {
                    content.classList.remove('show');
                    if (icon) icon.style.transform = 'rotate(0deg)';
                } else {
                    content.classList.add('show');
                    if (icon) icon.style.transform = 'rotate(180deg)';
                }
            });
        });
    }

    // PREFERENCES (load + save)
    const savePrefsBtn = document.getElementById('save-prefs-btn');
    if (savePrefsBtn) {
        api.get('/api/preferences').then(data => {
            ['camping_types', 'travel_styles', 'preferred_zones'].forEach(group => {
                (data[group] || []).forEach(val => {
                    const cb = document.querySelector(`input[name="${group}"][value="${val}"]`);
                    if (cb) cb.checked = true;
                });
            });
        }).catch(() => {});

        savePrefsBtn.addEventListener('click', async () => {
            const msgEl = document.getElementById('prefs-msg');
            const collect = name => Array.from(document.querySelectorAll(`input[name="${name}"]:checked`)).map(cb => cb.value);

            savePrefsBtn.disabled = true;
            const origText = savePrefsBtn.textContent;
            savePrefsBtn.textContent = t('account.saving');
            if (msgEl) msgEl.style.display = 'none';

            try {
                await api.post('/api/preferences', {
                    camping_types:   collect('camping_types'),
                    travel_styles:   collect('travel_styles'),
                    preferred_zones: collect('preferred_zones'),
                });
                savePrefsBtn.textContent = t('account.saved');
                if (msgEl) {
                    msgEl.textContent = t('profile.save_ok');
                    msgEl.style.color = '#2D4C36';
                    msgEl.style.display = 'block';
                }
                setTimeout(() => {
                    savePrefsBtn.disabled = false;
                    savePrefsBtn.textContent = origText;
                    if (msgEl) msgEl.style.display = 'none';
                }, 2500);
            } catch (err) {
                savePrefsBtn.disabled = false;
                savePrefsBtn.textContent = origText;
                if (msgEl) {
                    msgEl.textContent = err.message || t('account.save_err');
                    msgEl.style.color = '#EF6A00';
                    msgEl.style.display = 'block';
                }
            }
        });
    }

    // MOBILE HAMBURGER
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const sidebar = document.querySelector('.account-sidebar');

    if (hamburgerBtn && sidebar) {
        const overlay = document.createElement('div');
        overlay.className = 'mobile-overlay';
        document.body.appendChild(overlay);

        function toggleMobileMenu() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active', sidebar.classList.contains('open'));
        }

        hamburgerBtn.addEventListener('click', toggleMobileMenu);

        const themeButtons = document.querySelectorAll('.theme-toggle .toggle-btn');

        if (themeButtons.length > 0) {
            // citim tema salvata
            const savedTheme = localStorage.getItem('theme') || 'light';

            // aplicam tema imediat
            if (savedTheme === 'dark') {
                document.body.setAttribute('data-theme', 'dark');
            }

            // actualizam vizual butonul activ
            themeButtons.forEach(btn => {
                if (btn.getAttribute('data-set-theme') === savedTheme) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });

            // ascultam click pe butoane
            themeButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const selectedTheme = button.getAttribute('data-set-theme');

                    // aplicam tema pe body
                    if (selectedTheme === 'dark') {
                        document.body.setAttribute('data-theme', 'dark');
                    } else {
                        document.body.removeAttribute('data-theme');
                    }

                    // salvam in memorie
                    localStorage.setItem('theme', selectedTheme);

                    // schimbam clasa active intre butoane
                    themeButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                });
            });
        }
        //Click pe ORICE buton din meniu inchide sidebar-ul
        // (Pentru ca e Single Page App, vrem sa vedem pagina nou selectata)
        const sidebarLinks = sidebar.querySelectorAll('.nav-item');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 700) {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('active');
                }
            });
        });
    }

    // CONTACT FORM
    const contactForm = document.getElementById('contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('contact-btn');
            const feedback = document.getElementById('contact-feedback');
            const first = document.getElementById('contact-first').value.trim();
            const last  = document.getElementById('contact-last').value.trim();
            const name  = [last, first].filter(Boolean).join(' ');
            const email = document.getElementById('contact-email').value.trim();
            const phone = document.getElementById('contact-phone').value.trim();
            const message = document.getElementById('contact-message').value.trim();

            btn.disabled = true;
            btn.textContent = t('home.sending');
            feedback.style.display = 'none';

            try {
                await api.post('/api/contact', { name, email, phone, message });
                feedback.textContent = t('home.sent_ok');
                feedback.style.background = 'rgba(45,76,54,.1)';
                feedback.style.color = '#2D4C36';
                feedback.style.display = 'block';
                contactForm.reset();
            } catch (err) {
                feedback.textContent = err.message || t('home.sent_err');
                feedback.style.background = 'rgba(239,106,0,.08)';
                feedback.style.color = '#EF6A00';
                feedback.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = t('home.form_submit');
            }
        });
    }

    // INIT (Incarcare date API)
    if (typeof api !== 'undefined') {
        loadUser();
        loadBookings();
        loadWishlist();
    }



});



