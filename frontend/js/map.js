/** @type {import('leaflet')} */
const L = window.L;

const FALLBACK_IMG = '../assets/About1.jpg';

document.addEventListener('error', (e) => {
    const img = e.target;
    if (img instanceof HTMLImageElement && img.dataset.fallback && img.src !== img.dataset.fallback) {
        img.src = img.dataset.fallback;
    }
}, true);

const TYPE_EMOJI = {
    tent: '️',
    wild: '',
    rv: '',
    cabin: '️',
    glamping: ''
};

const TYPE_LABEL = {
    tent: 'Cort',
    wild: 'Sălbatic',
    rv: 'Autorulotă',
    cabin: 'Căsuță',
    glamping: 'Glamping'
};

let map;
let clusterGroup;
/** @type {import('./types.js').MapMarker[]} */
let allMarkers = [];
/** @type {import('./types.js').MapMarker[]} */
let filteredMarkers = [];

document.addEventListener('DOMContentLoaded', () => {
    initMap();
    initFilters();
    initLocateMe();
    initMobileSidebar();
});

function initMap() {
    map = L.map('map', {
        center: [45.9432, 24.9668],
        zoom: 7,
        zoomControl: false
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    L.control.zoom({ position: 'topright' }).addTo(map);

    clusterGroup = (L).markerClusterGroup({
        maxClusterRadius: 50,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        disableClusteringAtZoom: 16
    });
    map.addLayer(clusterGroup);

    // Debounced fetch on map move
    let moveTimeout;
    map.on('moveend', () => {
        clearTimeout(moveTimeout);
        moveTimeout = setTimeout(fetchMarkers, 300);
    });

    fetchMarkers();
}

async function fetchMarkers() {
    const bounds = map.getBounds();
    const bbox = [
        bounds.getSouth().toFixed(6),
        bounds.getWest().toFixed(6),
        bounds.getNorth().toFixed(6),
        bounds.getEast().toFixed(6)
    ].join(',');

    showResultsLoading();

    try {
        /** @type {{ markers?: import('./types.js').MapMarker[] }} */
        const data = await api.get(`/api/campings/map?bbox=${bbox}`);
        allMarkers = data.markers || [];
        applyFiltersAndRender();
    } catch (err) {
        console.error('Failed to fetch map markers:', err);
        allMarkers = [];
        applyFiltersAndRender();
    }
}

function getActiveFilters() {
    const checkedTypes = Array.from(
        document.querySelectorAll('.type-checkboxes input:checked')
    ).map(el => el.value);

    const maxPrice = parseInt(document.getElementById('price-slider').value, 10);
    const minRating = parseFloat(document.getElementById('rating-filter').value) || 0;

    return { types: checkedTypes, maxPrice, minRating };
}

function applyFiltersAndRender() {
    const { types, maxPrice, minRating } = getActiveFilters();

    filteredMarkers = allMarkers.filter(m => {
        if (types.length > 0 && !types.includes(m.type)) return false;
        if (parseFloat(m.price) > maxPrice) return false;
        return !(minRating > 0 && (parseFloat(m.rating) || 0) < minRating);

    });

    renderClusterMarkers();
    renderResultsList();
}

function renderClusterMarkers() {
    clusterGroup.clearLayers();

    filteredMarkers.forEach(m => {
        const emoji = TYPE_EMOJI[m.type] || '';

        const icon = L.divIcon({
            className: 'emoji-marker-wrapper',
            html: `<div class="emoji-marker">${emoji}</div>`,
            iconSize: [36, 36],
            iconAnchor: [18, 18],
            popupAnchor: [0, -20]
        });

        const imgSrc = m.image_url || FALLBACK_IMG;
        const ratingStr = m.rating ? ` ${m.rating.toFixed(1)}` : 'Nou';
        const priceStr = m.price ? `${m.price.toFixed(0)} RON / noapte` : '';

        const popupContent = `
            <div class="camping-popup">
                <img src="${imgSrc}" alt="${m.name}" data-fallback="${FALLBACK_IMG}">
                <h4>${m.name}</h4>
                <div class="popup-meta">
                    <span>${emoji} ${TYPE_LABEL[m.type] || m.type}</span>
                    <span class="rating-stars">${ratingStr}</span>
                </div>
                <div class="popup-price">${priceStr}</div>
                <a href="camping.html?id=${m.id}" class="popup-btn">Vezi Detalii →</a>
            </div>
        `;

        const marker = L.marker([m.lat, m.lng], { icon })
            .bindPopup(popupContent, { maxWidth: 260, minWidth: 220 });

        marker._campingData = m;
        clusterGroup.addLayer(marker);
    });
}

function renderResultsList() {
    const listEl = document.getElementById('results-list');
    const countEl = document.getElementById('visible-count');

    const visibleItems = filteredMarkers.slice(0, 20);
    countEl.textContent = String(filteredMarkers.length);

    if (visibleItems.length === 0) {
        listEl.innerHTML = `
            <div class="results-empty">
                <span>️</span>
                Niciun camping în zona vizibilă.<br>Mută harta sau schimbă filtrele.
            </div>
        `;
        return;
    }

    listEl.innerHTML = visibleItems.map(m => {
        const emoji = TYPE_EMOJI[m.type] || '';
        const ratingStr = m.rating ? ` ${m.rating.toFixed(1)}` : 'Nou';
        const imgSrc = m.image_url || FALLBACK_IMG;

        return `
            <li class="result-card" data-id="${m.id}" data-lat="${m.lat}" data-lng="${m.lng}">
                <img src="${imgSrc}" alt="${m.name}" class="result-card-img" data-fallback="${FALLBACK_IMG}">
                <div class="result-card-info">
                    <h4>${m.name}</h4>
                    <div class="result-card-meta">
                        <span class="type-emoji">${emoji}</span>
                        <span class="rating-stars">${ratingStr}</span>
                    </div>
                    <div class="result-card-price">${m.price ? m.price.toFixed(0) + ' RON / noapte' : ''}</div>
                </div>
            </li>
        `;
    }).join('');

    // Bind click: pan map to marker and open popup
    listEl.querySelectorAll('.result-card').forEach(card => {
        card.addEventListener('click', () => {
            const lat = parseFloat(card.dataset.lat);
            const lng = parseFloat(card.dataset.lng);
            const id = parseInt(card.dataset.id, 10);

            map.flyTo([lat, lng], 14, { duration: 0.8 });

            setTimeout(() => {
                clusterGroup.eachLayer(layer => {
                    if (layer._campingData && layer._campingData.id === id) {
                        clusterGroup.zoomToShowLayer(layer, () => {
                            layer.openPopup();
                        });
                    }
                });
            }, 900);
        });
    });
}

function showResultsLoading() {
    const listEl = document.getElementById('results-list');
    listEl.innerHTML = '<div class="results-loading"></div>';
}

function initFilters() {
    document.querySelectorAll('.type-checkboxes input').forEach(cb => {
        cb.addEventListener('change', applyFiltersAndRender);
    });

    const priceSlider = document.getElementById('price-slider');
    const priceDisplay = document.getElementById('price-display');
    priceSlider.addEventListener('input', () => {
        priceDisplay.textContent = `0 — ${priceSlider.value} RON`;
        applyFiltersAndRender();
    });

    document.getElementById('rating-filter').addEventListener('change', applyFiltersAndRender);
}

function initLocateMe() {
    const btn = document.getElementById('locate-me');
    if (!btn) return;

    btn.addEventListener('click', () => {
        if (!navigator.geolocation) {
            showToast('Geolocația nu este suportată de browser.', 'warning');
            return;
        }

        btn.textContent = '';
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                map.flyTo([pos.coords.latitude, pos.coords.longitude], 13, { duration: 1 });
                btn.textContent = '';
            },
            () => {
                showToast('Nu s-a putut obține locația.', 'error');
                btn.textContent = '';
            },
            { enableHighAccuracy: true, timeout: 8000 }
        );
    });
}

function initMobileSidebar() {
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.map-sidebar');
    const overlay = document.getElementById('map-overlay');

    if (!toggle) return;

    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('visible');
    });

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('visible');
        });
    }
}
