let currentPage = 1;
const limit = 12;
let activeZones = [];

function debounce(fn, delay) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); };
}

document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('cat_token')) {
        const authLink = document.querySelector('.nav-group a[href="auth.html"]');
        const hartaLink = document.querySelector('.nav-group a[href="map.html"]');
        const rightNavGroup = document.querySelector('.nav-group:last-of-type');

        if (authLink) authLink.remove();
        if (hartaLink && rightNavGroup) rightNavGroup.prepend(hartaLink);

        const btnRecommend = document.getElementById('btn-recommend');
        if (btnRecommend) btnRecommend.style.display = 'block';
    }

    const filtersForm = document.getElementById('filters-form');
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    const searchInput = document.getElementById('filter-search');

    filtersForm.addEventListener('submit', (e) => {
        e.preventDefault();
        currentPage = 1;
        loadCampings();
    });

    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            currentPage = 1;
            loadCampings();
        }, 400));
    }

    const btnRecommend = document.getElementById('btn-recommend');
    if (btnRecommend) {
        btnRecommend.addEventListener('click', async () => {
            btnRecommend.disabled = true;
            const orig = btnRecommend.textContent;
            btnRecommend.textContent = '...';
            try {
                const prefs = await api.get('/api/preferences');

                // aplica tipuri de camping
                document.querySelectorAll('input[name="type"]').forEach(cb => {
                    cb.checked = (prefs.camping_types || []).includes(cb.value);
                });

                // stocheaza zonele pentru a fi trimise la backend
                activeZones = prefs.preferred_zones || [];

                currentPage = 1;
                loadCampings();
            } catch (err) {
                console.error('Preferinte indisponibile', err);
            } finally {
                btnRecommend.disabled = false;
                btnRecommend.textContent = orig;
            }
        });
    }

    // la orice submit manual, resetam zonele active din recomandare
    filtersForm.addEventListener('submit', () => { activeZones = []; });

    btnPrev.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            loadCampings();
        }
    });

    btnNext.addEventListener('click', () => {
        currentPage++;
        loadCampings();
    });

    loadCampings();
});

async function loadCampings() {
    const grid = document.getElementById('campings-grid');
    grid.innerHTML = `
        <div class="camping-card skeleton"><div class="card-image skeleton"></div><div class="card-content"><div class="skeleton-text"></div><div class="skeleton-text"></div><div class="skeleton-text" style="width: 50%;"></div></div></div>
        <div class="camping-card skeleton"><div class="card-image skeleton"></div><div class="card-content"><div class="skeleton-text"></div><div class="skeleton-text"></div><div class="skeleton-text" style="width: 50%;"></div></div></div>
        <div class="camping-card skeleton"><div class="card-image skeleton"></div><div class="card-content"><div class="skeleton-text"></div><div class="skeleton-text"></div><div class="skeleton-text" style="width: 50%;"></div></div></div>
    `;

    // Gather filters
    const search = document.getElementById('filter-search').value;
    const region = document.getElementById('filter-region').value;
    const minPrice = document.getElementById('filter-min-price').value;
    const maxPrice = document.getElementById('filter-max-price').value;
    const minRating = document.getElementById('filter-min-rating').value;

    // Checkboxes for type
    const types = Array.from(document.querySelectorAll('input[name="type"]:checked')).map(el => el.value);

    // Build URLSearchParams
    const params = new URLSearchParams();
    params.append('limit', limit);
    params.append('offset', (currentPage - 1) * limit);

    if (search) params.append('search', search);
    if (region) params.append('region', region);
    if (minPrice) params.append('min_price', minPrice);
    if (maxPrice) params.append('max_price', maxPrice);
    if (minRating) params.append('min_rating', minRating);
    types.forEach(t => params.append('type[]', t));
    activeZones.forEach(z => params.append('zone[]', z));

    try {
        const response = await api.get(`/api/campings?${params.toString()}`);
        renderCampings(response);
    } catch (err) {
        console.error("Failed to load campings", err);
        grid.innerHTML = `<p style="color: red;">${t('campings.load_err')}</p>`;
    }
}

function renderCampings(data) {
    const grid = document.getElementById('campings-grid');
    const resultsCount = document.getElementById('results-count');
    const pageInfo = document.getElementById('page-info');
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');

    grid.innerHTML = '';
    resultsCount.textContent = data.total || 0;

    if (!data.campings || data.campings.length === 0) {
        grid.innerHTML = `<p>${t('campings.no_results')}</p>`;
    } else {
        data.campings.forEach(c => {
            const defaultImg = '../assets/About1.jpg'; // fallback image
            const ratingStr = c.rating_avg ? `${parseFloat(c.rating_avg).toFixed(1)} ` : 'Nou';

            const card = document.createElement('div');
            card.className = 'camping-card';
            card.innerHTML = `
                <img src="${defaultImg}" alt="${c.name}" class="card-image">
                <div class="card-content">
                    <span class="card-type">${c.type}</span>
                    <h3 class="card-title">${c.name}</h3>
                    <p class="card-region"> ${c.region || 'Locație necunoscută'}</p>
                    <div class="card-bottom">
                        <div class="card-price">${c.price_per_night} RON <span>/ noapte</span></div>
                        <div class="card-rating">${ratingStr}</div>
                    </div>
                </div>
<a href="camping.html?slug=${c.slug}" class="btn-details">Vezi Detalii</a>
            `;
            grid.appendChild(card);
        });
    }

    // Pagination controls
    pageInfo.textContent = `${t('campings.page')} ${currentPage}`;
    btnPrev.disabled = currentPage === 1;
    btnNext.disabled = (currentPage * limit) >= (data.total || 0);
}
