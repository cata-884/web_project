/* ============================================================
   map.js — Leaflet interactive map controller
   Fetches /api/campings/map?bbox=... on pan/zoom (debounced)
   Client-side filtering by type, price, rating
   Renders marker clusters + sidebar result list
   ============================================================ */

const TYPE_ICON = {
    tent: 'C',
    wild: 'S',
    rv: 'A',
    cabin: 'Ca',
    glamping: 'G'
};

const TYPE_LABEL = {
    tent: 'Cort',
    wild: 'Salbatic',
    rv: 'Autorulota',
    cabin: 'Casuta',
    glamping: 'Glamping'
};

let map;
let clusterGroup;
let allMarkers = [];      // raw data from last API call
let filteredMarkers = [];  // after local filters

document.addEventListener('DOMContentLoaded', () => {
    initMap();
    initFilters();
    initLocateMe();
    initMobileSidebar();
});

/* ---- MAP INIT ---- */
function initMap() {
    map = L.map('map', {
        center: [45.9432, 24.9668], // Romania center
        zoom: 7,
        zoomControl: false
    });

    // OSM tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    // Zoom control top-right
    L.control.zoom({ position: 'topright' }).addTo(map);

    // Marker cluster group
    clusterGroup = L.markerClusterGroup({
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

    // Initial fetch
    fetchMarkers();
}

/* ---- FETCH MARKERS FROM API ---- */
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
        const data = await api.get(`/api/campings/map?bbox=${bbox}`);
        allMarkers = data.markers || [];
        applyFiltersAndRender();
    } catch (err) {
        console.error('Failed to fetch map markers:', err);
        allMarkers = [];
        applyFiltersAndRender();
    }
}

/* ---- LOCAL FILTERS ---- */
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
        if (minRating > 0 && (parseFloat(m.rating) || 0) < minRating) return false;
        return true;
    });

    renderClusterMarkers();
    renderResultsList();
}

/* ---- RENDER MARKERS ON MAP ---- */
function renderClusterMarkers() {
    clusterGroup.clearLayers();

    filteredMarkers.forEach(m => {
        const icon_label = TYPE_ICON[m.type] || '?';

        const icon = L.divIcon({
            className: 'emoji-marker-wrapper',
            html: `<div class="emoji-marker">${icon_label}</div>`,
            iconSize: [36, 36],
            iconAnchor: [18, 18],
            popupAnchor: [0, -20]
        });

        const fallbackImg = '../assets/About1.jpg';
        const imgSrc = m.image_url || fallbackImg;
        const ratingStr = m.rating ? `★ ${parseFloat(m.rating).toFixed(1)}` : 'Nou';
        const priceStr = m.price ? `${parseFloat(m.price).toFixed(0)} RON / noapte` : '';

        const popupContent = `
            <div class="camping-popup">
                <img src="${imgSrc}" alt="${m.name}" onerror="this.src='${fallbackImg}'">
                <h4>${m.name}</h4>
                <div class="popup-meta">
                    <span>${TYPE_LABEL[m.type] || m.type}</span>
                    <span class="rating-stars">${ratingStr}</span>
                </div>
                <div class="popup-price">${priceStr}</div>
                <a href="camping.html?id=${m.id}" class="popup-btn">Vezi Detalii →</a>
            </div>
        `;

        const marker = L.marker([parseFloat(m.lat), parseFloat(m.lng)], { icon })
            .bindPopup(popupContent, { maxWidth: 260, minWidth: 220 });

        marker._campingData = m;
        clusterGroup.addLayer(marker);
    });
}

/* ---- RENDER SIDEBAR RESULTS LIST ---- */
function renderResultsList() {
    const listEl = document.getElementById('results-list');
    const countEl = document.getElementById('visible-count');

    const visibleItems = filteredMarkers.slice(0, 20);
    countEl.textContent = filteredMarkers.length;

    if (visibleItems.length === 0) {
        listEl.innerHTML = `
            <div class="results-empty">
                Niciun camping in zona vizibila.<br>Muta harta sau schimba filtrele.
            </div>
        `;
        return;
    }

    const fallbackImg = '../assets/About1.jpg';

    listEl.innerHTML = visibleItems.map(m => {
        const icon_label = TYPE_ICON[m.type] || '?';
        const ratingStr = m.rating ? `★ ${parseFloat(m.rating).toFixed(1)}` : 'Nou';
        const imgSrc = m.image_url || fallbackImg;

        return `
            <li class="result-card" data-id="${m.id}" data-lat="${m.lat}" data-lng="${m.lng}">
                <img src="${imgSrc}" alt="${m.name}" class="result-card-img" onerror="this.src='${fallbackImg}'">
                <div class="result-card-info">
                    <h4>${m.name}</h4>
                    <div class="result-card-meta">
                        <span class="type-label">${TYPE_LABEL[m.type] || m.type}</span>
                        <span class="rating-stars">${ratingStr}</span>
                    </div>
                    <div class="result-card-price">${m.price ? parseFloat(m.price).toFixed(0) + ' RON / noapte' : ''}</div>
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

            // Find matching cluster marker and open popup
            setTimeout(() => {
                clusterGroup.eachLayer(layer => {
                    if (layer._campingData && layer._campingData.id == id) {
                        // Spiderfy cluster if needed, then open
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

/* ---- FILTERS INIT ---- */
function initFilters() {
    // Type checkboxes
    document.querySelectorAll('.type-checkboxes input').forEach(cb => {
        cb.addEventListener('change', applyFiltersAndRender);
    });

    // Price slider
    const priceSlider = document.getElementById('price-slider');
    const priceDisplay = document.getElementById('price-display');
    priceSlider.addEventListener('input', () => {
        priceDisplay.textContent = `0 — ${priceSlider.value} RON`;
        applyFiltersAndRender();
    });

    // Rating select
    document.getElementById('rating-filter').addEventListener('change', applyFiltersAndRender);
}

/* ---- LOCATE ME ---- */
function initLocateMe() {
    const btn = document.getElementById('locate-me');
    if (!btn) return;

    btn.addEventListener('click', () => {
        if (!navigator.geolocation) {
            alert('Geolocatia nu este suportata de browser.');
            return;
        }

        btn.textContent = '...';
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                map.flyTo([pos.coords.latitude, pos.coords.longitude], 13, { duration: 1 });
                btn.textContent = '+';
            },
            () => {
                alert('Nu s-a putut obtine locatia.');
                btn.textContent = '+';
            },
            { enableHighAccuracy: true, timeout: 8000 }
        );
    });
}

/* ---- MOBILE SIDEBAR ---- */
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
