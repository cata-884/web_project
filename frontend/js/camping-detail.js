let currentCamping = null;
let currentPricePerNight = 0;
let userBookings = [];

function openLightbox(type, url) {
    const lb = document.getElementById('media-lightbox');
    const content = document.getElementById('media-lightbox-content');
    if (!lb || !content) return;

    if (type === 'image') {
        content.innerHTML = `<img src="${url}" alt="media">`;
    } else if (type === 'video') {
        content.innerHTML = `<video src="${url}" controls autoplay></video>`;
    }

    lb.classList.add('open');
}

function closeLightbox() {
    const lb = document.getElementById('media-lightbox');
    const content = document.getElementById('media-lightbox-content');
    if (!lb) return;
    lb.classList.remove('open');
    content.innerHTML = '';
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('media-lightbox')?.addEventListener('click', (e) => {
        if (e.target.id === 'media-lightbox' || e.target.id === 'media-lightbox-close') closeLightbox();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeLightbox();
    });
});

const TYPE_LABELS = {
    tent: 'Cort',
    glamping: 'Glamping',
    rv: 'Autorulotă',
    cabin: 'Căsuță',
    wild: 'Sălbatic',
};

document.addEventListener('DOMContentLoaded', async () => {
    const params = new URLSearchParams(window.location.search);
    const slug = params.get('slug');
    const id = params.get('id');

    if (!slug && !id) {
        document.getElementById('detail-main').innerHTML = '<h2 style="color:red">Camping invalid.</h2>';
        return;
    }

    try {
        const query = slug ? `slug=${slug}` : `id=${id}`;
        // Temporarily handling GET with slug/id via search since backend is generic.
        if (id) {
            const res = await api.get(`/api/campings/${id}`);
            currentCamping = res.camping;
        } else {
            // Fetch list and find the one with the slug
            const res = await api.get(`/api/campings?limit=100`);
            currentCamping = res.campings.find(c => c.slug === slug);
            if (currentCamping) {
                // Fetch full details
                const fullRes = await api.get(`/api/campings/${currentCamping.id}`);
                currentCamping = fullRes.camping;
            }
        }

        if (!currentCamping) throw new Error("Camping not found");

        currentPricePerNight = parseFloat(currentCamping.price_per_night);
        renderCampingDetails();

        // Check if user has a completed booking to show review form
        checkReviewEligibility();

    } catch (err) {
        document.getElementById('detail-main').innerHTML =
            `<div style="padding:40px;text-align:center">
                <h2 style="color:#EF6A00">Eroare la încărcarea campingului</h2>
                <p style="color:#666;margin-top:8px">${err.message || 'Te rugăm să încerci din nou.'}</p>
                <a href="campings.html" style="display:inline-block;margin-top:16px" class="btn-dark">← Înapoi la lista</a>
            </div>`;
    }
});

function renderCampingDetails() {
    const main = document.getElementById('detail-main');
    const media = currentCamping.media || [];
    const mainImg = media.length > 0 ? media[0].url : '../assets/About1.jpg';

    let thumbnailsHTML = '';
    media.forEach((m, idx) => {
        thumbnailsHTML += `<img src="${m.url}" class="${idx === 0 ? 'active' : ''}" onclick="changeMainImage(this, '${m.url}')" alt="Thumbnail">`;
    });

    if (thumbnailsHTML === '') {
        thumbnailsHTML = `<img src="../assets/About1.jpg" class="active" alt="Thumbnail">`;
    }

    const ratingStr = currentCamping.rating_avg ? `${parseFloat(currentCamping.rating_avg).toFixed(1)} / 5` : 'Fara recenzii';

    main.innerHTML = `
        <div class="gallery-container">
            <img src="${mainImg}" id="main-gallery-img" class="main-image" alt="${currentCamping.name}">
            <div class="thumbnail-list">
                ${thumbnailsHTML}
            </div>
        </div>

        <div class="content-split">
            <div class="camping-info">
                <h1>${currentCamping.name}</h1>
                <div class="rating-location">
                    <span class="rating-badge">★ ${ratingStr}</span>
                    <span>📍 ${currentCamping.address || currentCamping.region || 'Locatie necunoscuta'}</span>
                    <span>🏕️ Tip: ${currentCamping.type}</span>
                </div>

                <div class="camping-description">
                    ${currentCamping.description || 'Nicio descriere disponibila.'}
                </div>

                <div id="camping-map" class="camping-mini-map"></div>

                <div id="nearby-section" class="nearby-section" style="display:none">
                    <h3>În apropiere <small>(raza 5 km)</small></h3>
                    <div id="nearby-pois" class="poi-chips"></div>
                </div>

                <div class="reviews-section" id="reviews-section">
                    <h3>Recenzii</h3>
                    <div id="review-form-container" class="review-form-container">
                        <h3>Scrie o recenzie</h3>
                        <div class="star-select" id="star-select">
                            <span data-val="1">★</span><span data-val="2">★</span><span data-val="3">★</span><span data-val="4">★</span><span data-val="5">★</span>
                        </div>
                        <textarea id="review-text" placeholder="Cum a fost experienta ta?"></textarea>
                        <label class="review-media-label">
                            Ataseaza fisiere (imagini, audio, video)
                            <input type="file" id="review-files" multiple accept="image/*,audio/*,video/*">
                        </label>
                        <div id="review-file-preview" class="review-file-preview"></div>
                        <button class="btn-dark" onclick="submitReview()">Trimite Recenzia</button>
                    </div>
                    <div id="reviews-list">
                        <p>Nu exista recenzii inca.</p>
                    </div>
                </div>
            </div>

            <aside>
                <div class="booking-card">
                    <h3>Rezerva acum</h3>
                    <div class="booking-meta">
                        <span>${TYPE_LABELS[currentCamping.type] || currentCamping.type}</span>
                        ${currentCamping.capacity ? `<span>Max ${currentCamping.capacity} persoane</span>` : ''}
                    </div>
                    <div class="form-group">
                        <label>Data Check-in</label>
                        <input type="date" id="book-checkin" onchange="calculatePrice()">
                    </div>
                    <div class="form-group">
                        <label>Data Check-out</label>
                        <input type="date" id="book-checkout" onchange="calculatePrice()">
                    </div>
                    <div class="form-group">
                        <label>Numar oaspeti</label>
                        <input type="number" id="book-guests" min="1" max="20" value="2">
                    </div>
                    
                    <div class="price-calculation" id="price-calc-display" style="display: none;">
                        <div class="calc-row">
                            <span id="calc-nights">0 nopti x ${currentPricePerNight} RON</span>
                            <span id="calc-base-price">0 RON</span>
                        </div>
                        <div class="calc-total">
                            <span>Total: </span>
                            <span id="calc-total-price">0 RON</span>
                        </div>
                    </div>

                    <button class="btn-dark" style="width: 100%; margin-top: 16px;" onclick="handleBooking()">Confirma Rezervarea</button>
                </div>
            </aside>
        </div>
    `;

    // Setup star logic + file preview
    setTimeout(() => {
        const stars = document.querySelectorAll('#star-select span');
        let selected = 0;
        stars.forEach(s => {
            s.addEventListener('click', () => {
                selected = s.dataset.val;
                window.currentReviewRating = selected;
                stars.forEach(st => {
                    st.classList.toggle('selected', st.dataset.val <= selected);
                });
            });
        });

        const filesInput = document.getElementById('review-files');
        const previewEl  = document.getElementById('review-file-preview');
        if (filesInput && previewEl) {
            filesInput.addEventListener('change', () => {
                previewEl.innerHTML = '';
                Array.from(filesInput.files).forEach(f => {
                    const chip = document.createElement('span');
                    chip.className = 'review-file-chip';
                    chip.textContent = f.name;
                    previewEl.appendChild(chip);
                });
            });
        }
    }, 100);

    loadReviews();
    initMiniMap();
}

window.changeMainImage = function (thumbElement, url) {
    document.getElementById('main-gallery-img').src = url;
    document.querySelectorAll('.thumbnail-list img').forEach(img => img.classList.remove('active'));
    thumbElement.classList.add('active');
};

function calculatePrice() {
    const ci = document.getElementById('book-checkin').value;
    const co = document.getElementById('book-checkout').value;

    if (ci && co) {
        const date1 = new Date(ci);
        const date2 = new Date(co);
        const diffTime = Math.abs(date2 - date1);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays > 0 && date2 > date1) {
            document.getElementById('price-calc-display').style.display = 'block';
            document.getElementById('calc-nights').textContent = `${diffDays} nopti x ${currentPricePerNight} RON`;

            const total = diffDays * currentPricePerNight;
            document.getElementById('calc-base-price').textContent = `${total} RON`;
            document.getElementById('calc-total-price').textContent = `${total} RON`;
        } else {
            document.getElementById('price-calc-display').style.display = 'none';
        }
    }
}

async function handleBooking() {
    const ci = document.getElementById('book-checkin').value;
    const co = document.getElementById('book-checkout').value;

    if (!ci || !co) {
        alert("Selecteaza datele!");
        return;
    }

    if (new Date(ci) >= new Date(co)) {
        alert("Check-out trebuie sa fie dupa check-in!");
        return;
    }

    try {
        await api.post('/api/bookings', {
            camping_id: currentCamping.id,
            check_in: ci,
            check_out: co,
            total_price: parseFloat(document.getElementById('calc-total-price').textContent),
            guests: parseInt(document.getElementById('book-guests').value),
        });
        alert("Rezervare creata cu succes!");
        window.location.href = "account/account.html";
    } catch (err) {
        alert(err.message || "Eroare la rezervare. Esti autentificat?");
    }
}

async function checkReviewEligibility() {
    const container = document.getElementById('review-form-container');
    if (!container) return;

    const token = localStorage.getItem('cat_token');
    if (!token) {
        container.innerHTML = `<p class="review-login-prompt">
            <a href="auth.html">Autentifica-te</a> pentru a lasa o recenzie.
        </p>`;
        container.style.display = 'block';
        return;
    }

    try {
        const res = await api.get(`/api/campings/${currentCamping.id}/reviews`);
        const userRaw = localStorage.getItem('cat_user');
        const userId = userRaw ? JSON.parse(userRaw).id : null;
        const alreadyReviewed = userId && res.reviews && res.reviews.some(r => String(r.user_id) === String(userId));

        if (alreadyReviewed) {
            container.innerHTML = `<p class="review-already">Ai lasat deja o recenzie pentru acest camping.</p>`;
            container.style.display = 'block';
        } else {
            container.style.display = 'block';
        }
    } catch (e) {
        container.style.display = 'block';
    }
}

const reviewDataMap = {};

async function loadReviews() {
    try {
        const res = await api.get(`/api/campings/${currentCamping.id}/reviews`);
        const list = document.getElementById('reviews-list');
        const userRaw = localStorage.getItem('cat_user');
        const currentUserId = userRaw ? String(JSON.parse(userRaw).id) : null;

        if (res.reviews && res.reviews.length > 0) {
            list.innerHTML = '';
            res.reviews.forEach(r => {
                reviewDataMap[r.id] = { rating: r.rating, content: r.content || '', media: r.media || [] };

                const isOwn = currentUserId && String(r.user_id) === currentUserId;

                const mediaHTML = (r.media || []).map(m => {
                    if (m.type === 'image') return `<img src="${m.url}" class="review-media-img" alt="media" data-url="${m.url}">`;
                    if (m.type === 'video') return `<div class="review-media-video-wrap"><video src="${m.url}" class="review-media-video" data-url="${m.url}" preload="metadata" muted></video></div>`;
                    if (m.type === 'audio') return `<audio src="${m.url}" controls class="review-media-audio"></audio>`;
                    return '';
                }).join('');

                const actionsHTML = isOwn ? `
                    <div class="review-actions">
                        <button class="btn-review-edit" data-id="${r.id}">Editeaza</button>
                        <button class="btn-review-delete" data-id="${r.id}">Sterge</button>
                    </div>` : '';

                list.innerHTML += `
                    <div class="review-item" id="review-${r.id}">
                        <div class="review-header">
                            <span class="review-author">${r.username || 'Utilizator'}</span>
                            <span class="review-rating">★ ${r.rating}</span>
                            ${actionsHTML}
                        </div>
                        <div class="review-body" id="review-body-${r.id}">
                            ${r.content ? `<p>${r.content}</p>` : ''}
                            ${mediaHTML ? `<div class="review-media-list">${mediaHTML}</div>` : ''}
                        </div>
                        <div class="review-edit-form" id="review-edit-${r.id}" style="display:none"></div>
                    </div>
                `;
            });

            list.querySelectorAll('.btn-review-edit').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = parseInt(btn.dataset.id);
                    const d = reviewDataMap[id];
                    openEditReview(id, d.rating, d.content);
                });
            });
            list.querySelectorAll('.btn-review-delete').forEach(btn => {
                btn.addEventListener('click', () => deleteReview(parseInt(btn.dataset.id)));
            });

            list.querySelectorAll('.review-media-img').forEach(img => {
                img.addEventListener('click', () => openLightbox('image', img.dataset.url));
            });
            list.querySelectorAll('.review-media-video').forEach(vid => {
                vid.addEventListener('loadedmetadata', () => { vid.currentTime = 0.1; });
                vid.parentElement.addEventListener('click', () => openLightbox('video', vid.dataset.url));
            });
        }
    } catch (e) {
        // Ignored
    }
}

window.openEditReview = function (id) {
    const d = reviewDataMap[id];
    if (!d) return;
    const editEl = document.getElementById(`review-edit-${id}`);
    const bodyEl = document.getElementById(`review-body-${id}`);
    if (!editEl) return;

    editEl.style.display = 'block';
    bodyEl.style.display = 'none';

    let starsHTML = '';
    for (let i = 1; i <= 5; i++) {
        starsHTML += `<span class="${i <= d.rating ? 'selected' : ''}" data-val="${i}">★</span>`;
    }

    const existingMediaHTML = d.media.map(m => {
        let preview = '';
        if (m.type === 'image') preview = `<img src="${m.url}" class="edit-media-thumb">`;
        else if (m.type === 'audio') preview = `<audio controls src="${m.url}" class="edit-media-audio"></audio>`;
        else if (m.type === 'video') preview = `<video controls src="${m.url}" class="edit-media-video"></video>`;
        return `<div class="edit-media-item" id="edit-media-item-${m.id}">
            ${preview}
            <button class="btn-review-delete edit-media-delete" data-media-id="${m.id}">✕</button>
        </div>`;
    }).join('');

    editEl.innerHTML = `
        <div class="star-select" id="edit-stars-${id}">${starsHTML}</div>
        <textarea id="edit-text-${id}" class="edit-review-textarea">${d.content}</textarea>
        ${d.media.length ? `<div class="edit-media-list" id="edit-media-list-${id}">${existingMediaHTML}</div>` : ''}
        <label class="review-media-label">
            Adauga fisiere noi
            <input type="file" id="edit-files-${id}" multiple accept="image/*,audio/*,video/*">
        </label>
        <div class="edit-review-actions">
            <button class="btn-dark" id="edit-save-${id}">Salveaza</button>
            <button class="btn-review-cancel" id="edit-cancel-${id}">Anuleaza</button>
        </div>
    `;

    let editRating = d.rating;
    editEl._editRating = editRating;

    editEl.querySelectorAll(`#edit-stars-${id} span`).forEach(s => {
        s.style.cssText = 'font-size:1.5rem;cursor:pointer;color:' + (parseInt(s.dataset.val) <= editRating ? 'var(--color-accent)' : '#ccc');
        s.addEventListener('click', () => {
            editRating = parseInt(s.dataset.val);
            editEl._editRating = editRating;
            editEl.querySelectorAll(`#edit-stars-${id} span`).forEach(st => {
                st.style.color = parseInt(st.dataset.val) <= editRating ? 'var(--color-accent)' : '#ccc';
            });
        });
    });

    editEl.querySelectorAll('.edit-media-delete').forEach(btn => {
        btn.addEventListener('click', async () => {
            const mid = parseInt(btn.dataset.mediaId);
            try {
                await api.delete(`/api/media/review/${mid}`);
                document.getElementById(`edit-media-item-${mid}`)?.remove();
                reviewDataMap[id].media = reviewDataMap[id].media.filter(m => m.id !== mid);
            } catch (err) {
                alert(err.message || 'Eroare la stergere media.');
            }
        });
    });

    document.getElementById(`edit-save-${id}`).addEventListener('click', () => saveEditReview(id));
    document.getElementById(`edit-cancel-${id}`).addEventListener('click', () => cancelEditReview(id));
};

window.cancelEditReview = function (id) {
    document.getElementById(`review-edit-${id}`).style.display = 'none';
    document.getElementById(`review-body-${id}`).style.display = 'block';
};

window.saveEditReview = async function (id) {
    const editEl = document.getElementById(`review-edit-${id}`);
    const rating = editEl._editRating;
    const content = document.getElementById(`edit-text-${id}`).value;
    const files = document.getElementById(`edit-files-${id}`)?.files || [];

    try {
        await api.patch(`/api/reviews/${id}`, { rating, content });

        if (files.length > 0) {
            const errors = await uploadMediaFiles(id, files);
            if (errors.length) {
                alert("Salvat, dar unele fisiere au esuat:\n" + errors.join('\n'));
            }
        }

        loadReviews();
    } catch (err) {
        alert(err.message || 'Eroare la salvare.');
    }
};

window.deleteReview = async function (id) {
    if (!confirm('Stergi aceasta recenzie?')) return;
    try {
        await api.delete(`/api/reviews/${id}`);
        loadReviews();
        checkReviewEligibility();
    } catch (err) {
        alert(err.message || 'Eroare la stergere.');
    }
};

async function uploadMediaFiles(reviewId, files) {
    const token = localStorage.getItem('cat_token');
    const errors = [];
    for (const file of files) {
        const fd = new FormData();
        fd.append('file', file);
        try {
            const res = await fetch(`/cat/public/api/reviews/${reviewId}/media`, {
                method: 'POST',
                headers: token ? { 'Authorization': `Bearer ${token}` } : {},
                body: fd,
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                errors.push(`${file.name}: ${data.error || res.statusText}`);
            }
        } catch (e) {
            errors.push(`${file.name}: eroare retea`);
        }
    }
    return errors;
}

window.submitReview = async function () {
    const rating = window.currentReviewRating;
    const comment = document.getElementById('review-text').value;
    const files = document.getElementById('review-files')?.files || [];

    if (!rating) {
        alert("Selecteaza o nota!");
        return;
    }

    try {
        const res = await api.post(`/api/campings/${currentCamping.id}/reviews`, {
            rating: parseInt(rating),
            content: comment,
        });

        const reviewId = res.review?.id;
        if (reviewId && files.length > 0) {
            const errors = await uploadMediaFiles(reviewId, files);
            if (errors.length) {
                alert("Recenzie adaugata, dar unele fisiere au esuat:\n" + errors.join('\n'));
            }
        }

        window.location.reload();
    } catch (err) {
        alert(err.message || "Eroare la recenzie.");
    }
};

function initMiniMap() {
    const mapEl = document.getElementById('camping-map');
    if (!mapEl || !currentCamping) return;

    const lat = parseFloat(currentCamping.latitude);
    const lng = parseFloat(currentCamping.longitude);

    // If no valid coordinates, hide the map
    if (isNaN(lat) || isNaN(lng)) {
        mapEl.style.display = 'none';
        return;
    }

    const miniMap = L.map('camping-map', {
        center: [lat, lng],
        zoom: 13,
        scrollWheelZoom: false
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(miniMap);

    const marker = L.marker([lat, lng]).addTo(miniMap);
    marker.bindPopup(`<strong>${currentCamping.name}</strong><br>${currentCamping.address || currentCamping.region || ''}`).openPopup();

    setTimeout(() => { miniMap.invalidateSize(); }, 200);

    loadNearbyPOIs(miniMap, lat, lng);
}

const POI_CONFIG = {
    'natural=peak': { emoji: '⛰️', label: 'Vârf' },
    'amenity=drinking_water': { emoji: '💧', label: 'Apă potabilă' },
    'tourism=viewpoint': { emoji: '🔭', label: 'Belvedere' },
    'amenity=parking': { emoji: '🅿️', label: 'Parcare' },
    'leisure=picnic_table': { emoji: '🍽️', label: 'Picnic' },
};

function classifyPOI(tags) {
    if (!tags) return null;
    if (tags.natural === 'peak') return 'natural=peak';
    if (tags.amenity === 'drinking_water') return 'amenity=drinking_water';
    if (tags.tourism === 'viewpoint') return 'tourism=viewpoint';
    if (tags.amenity === 'parking') return 'amenity=parking';
    if (tags.leisure === 'picnic_table') return 'leisure=picnic_table';
    return null;
}

function escapeHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

async function loadNearbyPOIs(map, lat, lng) {
    const radius = 5000;
    const query = `[out:json][timeout:25];(node["natural"="peak"](around:${radius},${lat},${lng});node["amenity"="drinking_water"](around:${radius},${lat},${lng});node["tourism"="viewpoint"](around:${radius},${lat},${lng});node["amenity"="parking"](around:${radius},${lat},${lng});node["leisure"="picnic_table"](around:${radius},${lat},${lng}););out body;`;

    try {
        const res = await fetch('https://overpass-api.de/api/interpreter', {
            method: 'POST',
            body: query,
        });
        if (!res.ok) return;
        const data = await res.json();

        const groups = {};

        (data.elements || []).forEach(el => {
            const key = classifyPOI(el.tags);
            if (!key || !el.lat || !el.lon) return;

            const cfg = POI_CONFIG[key];
            const name = el.tags?.name || cfg.label;

            const icon = L.divIcon({
                className: 'poi-emoji-icon',
                html: `<span title="${escapeHtml(name)}">${cfg.emoji}</span>`,
                iconSize: [24, 24],
                iconAnchor: [12, 12],
                popupAnchor: [0, -14],
            });

            L.marker([el.lat, el.lon], { icon })
                .bindPopup(`<strong>${cfg.emoji} ${escapeHtml(name)}</strong><br><small>${cfg.label}</small>`)
                .addTo(map);

            if (!groups[key]) groups[key] = { emoji: cfg.emoji, label: cfg.label, count: 0 };
            groups[key].count++;
        });

        if (Object.keys(groups).length) {
            const pois = document.getElementById('nearby-pois');
            const section = document.getElementById('nearby-section');
            if (pois && section) {
                pois.innerHTML = Object.values(groups)
                    .map(g => `<span class="poi-chip">${g.emoji} ${g.label} <b>${g.count}</b></span>`)
                    .join('');
                section.style.display = 'block';
            }
        }
    } catch (_) {
        // Overpass unavailable — skip silently
    }
}
