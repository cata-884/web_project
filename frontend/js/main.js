document.addEventListener('DOMContentLoaded', () => {

    // ==========================================
    // 1. CARDS SLIDER (ABOUT US)
    // ==========================================
    const cards = document.querySelectorAll('.about-cards-stack .feature-card');
    const sliderButtons = document.querySelectorAll('.about-cards-stack .card-btn');
    let positions = ['pos-0', 'pos-1', 'pos-2'];

    if (sliderButtons.length > 0) {
        sliderButtons.forEach(button => {
            button.addEventListener('click', () => {
                cards.forEach(card => card.classList.remove('pos-0', 'pos-1', 'pos-2'));
                positions.unshift(positions.pop());
                cards.forEach((card, index) => card.classList.add(positions[index]));
            });
        });
    }

    // ==========================================
    // 2. TABS SWITCHER (ABOUT US - LUCIU/CATALIN)
    // ==========================================
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

    // ==========================================
    // 3. THEME TOGGLE (DARK/WHITE)
    // ==========================================
    const themeButtons = document.querySelectorAll('.theme-toggle .toggle-btn');

    if (themeButtons.length > 0) {
        themeButtons.forEach(button => {
            button.addEventListener('click', () => {
                themeButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
            });
        });
    }

    // ==========================================
    // 4. MAIN SIDEBAR NAVIGATION (ACCOUNT SPA)
    // ==========================================
    const accountTabBtns = document.querySelectorAll('.account-tabs .nav-item[data-tab]');
    const accountSections = document.querySelectorAll('.tab-section');

    if (accountTabBtns.length > 0 && accountSections.length > 0) {
        accountTabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                accountTabBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                accountSections.forEach(section => section.classList.remove('active-section'));

                const targetId = btn.getAttribute('data-tab');
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    targetElement.classList.add('active-section');
                }
            });
        });
    }

    // ==========================================
    // 5. MOCK DATA & RENDER SYSTEM (HISTORY)
    // ==========================================
    const mockBookings = [
        {
            id: 1,
            name: "Valea Verde Camping",
            dates: "20 Iul. - 25 Iul. 2026 • 2 adulți",
            status: "upcoming",
            statusLabel: "Urmează",
            image: "../../assets/About1.jpg"
        },
        {
            id: 2,
            name: "Lacul Liniștit Glamping",
            dates: "15 Mai - 17 Mai 2026 • 2 adulți",
            status: "completed",
            statusLabel: "Finalizată",
            image: "../../assets/About2.jpg"
        },
        {
            id: 3,
            name: "Poiana Ursului Auto-Camp",
            dates: "01 Mai - 03 Mai 2026 • 4 adulți",
            status: "cancelled",
            statusLabel: "Anulată",
            image: "../../assets/About1.jpg"
        }
    ];

    const bookingsContainer = document.getElementById('bookings-container');

    function renderBookings(bookingsArray) {
        if (!bookingsContainer) return;
        bookingsContainer.innerHTML = '';

        bookingsArray.forEach(booking => {
            const cardHTML = `
                <div class="booking-card status-${booking.status}" onclick="openBookingDetails(${booking.id})">
                    <img src="${booking.image}" alt="${booking.name}" class="booking-img">
                    <div class="booking-info">
                        <h3>${booking.name}</h3>
                        <p class="booking-dates">${booking.dates}</p>
                        <span class="booking-status">${booking.statusLabel}</span>
                    </div>
                    <div class="booking-arrow">↗</div>
                </div>
            `;
            bookingsContainer.innerHTML += cardHTML;
        });
    }

    renderBookings(mockBookings);

    // ==========================================
    // 6. BOOKING FILTERS LOGIC
    // ==========================================
    const filterBtns = document.querySelectorAll('.history-filters .filter-btn');

    if (filterBtns.length > 0) {
        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                filterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const filterValue = btn.getAttribute('data-filter');
                let filteredBookings = (filterValue === 'all')
                    ? mockBookings
                    : mockBookings.filter(booking => booking.status === filterValue);

                renderBookings(filteredBookings);
            });
        });
    }

    // ==========================================
    // 7. SPA DETAILS VIEW & NAVIGATION
    // ==========================================
    window.openBookingDetails = function(id) {
        const currentBooking = mockBookings.find(b => b.id === id);

        if (currentBooking) {
            document.getElementById('bd-image').src = currentBooking.image;
            document.getElementById('bd-name').textContent = currentBooking.name;
            document.getElementById('bd-dates').textContent = currentBooking.dates;

            const statusBadge = document.getElementById('bd-status');
            statusBadge.textContent = currentBooking.statusLabel;

            // Reset colors
            statusBadge.style.backgroundColor = '';
            if (currentBooking.status === 'upcoming') statusBadge.style.backgroundColor = '#84AC00';
            if (currentBooking.status === 'completed') statusBadge.style.backgroundColor = '#2D4C36';
            if (currentBooking.status === 'cancelled') statusBadge.style.backgroundColor = '#EF6A00';
            statusBadge.style.color = 'white';

            // Review section visibility
            const reviewSection = document.getElementById('review-section');
            if (reviewSection) {
                reviewSection.style.display = (currentBooking.status === 'completed') ? 'flex' : 'none';
            }

            // Tab switching
            document.querySelectorAll('.tab-section').forEach(section => section.classList.remove('active-section'));
            document.getElementById('booking-details').classList.add('active-section');

            // Sidebar sync
            document.querySelectorAll('.account-tabs .nav-item').forEach(b => b.classList.remove('active'));
            const historyBtn = document.querySelector('.account-tabs .nav-item[data-tab="history"]');
            if (historyBtn) historyBtn.classList.add('active');
        }
    };

    const backBtn = document.getElementById('back-to-history');
    if (backBtn) {
        backBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const historyBtn = document.querySelector('.account-tabs .nav-item[data-tab="history"]');
            if (historyBtn) historyBtn.click();
        });
    }

    // ==========================================
    // 8. INTERACTIVE STAR RATING SYSTEM
    // ==========================================
    const stars = document.querySelectorAll('.stars span');
    let selectedRating = 0;

    if (stars.length > 0) {
        stars.forEach(star => {
            star.addEventListener('mouseover', function() {
                const value = this.getAttribute('data-value');
                stars.forEach(s => {
                    s.style.color = s.getAttribute('data-value') <= value ? '#EF6A00' : '#ccc';
                });
            });

            star.addEventListener('mouseout', function() {
                stars.forEach(s => {
                    s.style.color = s.getAttribute('data-value') <= selectedRating ? '#EF6A00' : '#ccc';
                });
            });

            star.addEventListener('click', function() {
                selectedRating = this.getAttribute('data-value');
            });
        });
    }
    let mockWishlist = [
        {
            id: 101,
            name: "Transfăgărășan Off-Camping",
            address: "📍 Comuna Arefu, Argeș, România",
            image: "../../assets/About1.jpg" // Pune o poza reala din assets
        },
        {
            id: 102,
            name: "Cheile Turzii Camping",
            address: "📍 Petreștii de Jos, Cluj, România",
            image: "../../assets/About2.jpg"
        }
    ];

    const wishlistContainer = document.getElementById('wishlist-container');

    function renderWishlist() {
        if (!wishlistContainer) return;

        wishlistContainer.innerHTML = '';

        // Daca wishlist-ul e gol, aratam un mesaj dragut
        if (mockWishlist.length === 0) {
            wishlistContainer.innerHTML = '<p style="color: #666; font-style: italic;">Nu ai nicio locație salvată momentan. Explorează și adaugă la favorite!</p>';
            return;
        }

        mockWishlist.forEach(item => {
            const cardHTML = `
                <div class="wishlist-card" onclick="goToCampingPage(${item.id})">
                    <img src="${item.image}" alt="${item.name}" class="wishlist-img">
                    <div class="wishlist-info">
                        <h3>${item.name}</h3>
                        <p class="wishlist-address">${item.address}</p>
                    </div>

                    <div class="wishlist-heart" onclick="removeFromWishlist(event, ${item.id})" title="Sterge din Wishlist">
                        ❤️
                    </div>
                </div>
            `;
            wishlistContainer.innerHTML += cardHTML;
        });
    }

    // Funcția 1: Redirecționare către pagina campingului
    window.goToCampingPage = function(id) {
        // Aici pui calea reala catre pagina voastra de produs. Am pus un exemplu.
        window.location.href = `../../camping-details.html?id=${id}`;
    };

    // Funcția 2: Ștergere din Wishlist
    window.removeFromWishlist = function(event, id) {
        // TRUCUL MAGIC: Oprește click-ul să ajungă la onclick-ul de pe .wishlist-card
        event.stopPropagation();

        // Scoatem item-ul din array
        mockWishlist = mockWishlist.filter(item => item.id !== id);

        // Desenăm din nou lista actualizata
        renderWishlist();
    };

    // Apelam funcția la încărcarea paginii
    renderWishlist();
    // ==========================================
    // 10. ACCORDION SYSTEM PENTRU PROFIL
    // ==========================================
    const accordions = document.querySelectorAll('.accordion-header');

    if (accordions.length > 0) {
        accordions.forEach(acc => {
            acc.addEventListener('click', function() {
                // Rotim iconita
                const icon = this.querySelector('.acc-icon');

                // Gasim continutul imediat urmator (div-ul cu checkbox-uri)
                const content = this.nextElementSibling;

                if (content.classList.contains('show')) {
                    // Daca e deschis, il inchidem
                    content.classList.remove('show');
                    icon.style.transform = 'rotate(0deg)';
                } else {
                    // Daca e inchis, il deschidem
                    content.classList.add('show');
                    icon.style.transform = 'rotate(180deg)';
                }
            });
        });
    }
    // ==========================================
    // 11. MOBILE HAMBURGER MENU (OFF-CANVAS)
    // ==========================================
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const sidebar = document.querySelector('.account-sidebar');

    if (hamburgerBtn && sidebar) {
        // 1. Creăm acel fundal întunecat (overlay) dinamic din JavaScript
        const overlay = document.createElement('div');
        overlay.className = 'mobile-overlay';
        document.body.appendChild(overlay);

        // Funcția de deschidere/închidere
        function toggleMobileMenu() {
            sidebar.classList.toggle('open');

            if (sidebar.classList.contains('open')) {
                overlay.classList.add('active');
            } else {
                overlay.classList.remove('active');
            }
        }

        // 2. Click pe butonul Hamburger
        hamburgerBtn.addEventListener('click', toggleMobileMenu);

        // 3. Click pe fundalul întunecat închide meniul
        overlay.addEventListener('click', toggleMobileMenu);

        // 4. Click pe ORICE buton din meniu închide sidebar-ul
        // (Pentru că e Single Page App, vrem să vedem pagina nou selectată)
        const sidebarLinks = sidebar.querySelectorAll('.nav-item');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                // Dacă suntem pe mobil (lățime <= 700), închidem meniul automat
                if (window.innerWidth <= 700) {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('active');
                }
            });
        });
    }
});