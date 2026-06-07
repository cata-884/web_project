let currentCamping = null;
let currentPricePerNight = 0;
let wishlistSectionId = null;
let campingInWishlist = false;

function openLightbox(type, url) {
    const lb = document.getElementById('media-lightbox');
    const content = document.getElementById('media-lightbox-content');
    if (!lb || !content) return;

    content.innerHTML = '';
    if (type === 'image') {
        const img = document.createElement('img');
        img.src = url;
        img.alt = 'media';
        content.appendChild(img);
    } else if (type === 'video') {
        const vid = document.createElement('video');
        vid.src = url;
        vid.controls = true;
        vid.autoplay = true;
        content.appendChild(vid);
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

    const hamburger = document.querySelector('.hamburger-menu');
    const mainNav = document.querySelector('.main-nav');
    if (hamburger && mainNav) {
        hamburger.addEventListener('click', () => mainNav.classList.toggle('nav-open'));
    }
});

const TYPE_LABELS = {
    tent: 'Cort',
    glamping: 'Glamping',
    rv: 'Autorulotă',
    cabin: 'Căsuță',
    wild: 'Sălbatic',
};

document.addEventListener('DOMContentLoaded', async () => {
    //  Logica existenta de meniu
    if (localStorage.getItem('cat_token')) {
        const authLink = document.querySelector('.nav-group a[href="auth.html"]');
        const hartaLink = document.querySelector('.nav-group a[href="map.html"]');
        const rightNavGroup = document.querySelector('.nav-group:last-of-type');

        if (authLink) authLink.remove();
        if (hartaLink && rightNavGroup) rightNavGroup.prepend(hartaLink);
    }

    const params = new URLSearchParams(window.location.search);
    const slug = params.get('slug');
    const id = params.get('id');

    if (!slug && !id) {
        const h2 = document.createElement('h2');
        h2.style.cssText = 'color:red;text-align:center;padding:50px;';
        h2.textContent = 'Camping invalid sau adresă greșită.';
        document.getElementById('detail-main').appendChild(h2);
        return;
    }

    try {
        const res = id
            ? await api.get(`/api/campings/${id}`)
            : await api.get(`/api/campings/by-slug/${encodeURIComponent(slug)}`);
        currentCamping = res.camping;

        if (!currentCamping) throw new Error("Camping not found");

        //  MAGIE PENTRU SEO: Setam titlul si meta descrierea dinamic
        document.title = `${currentCamping.name} | CaT Camping Info`;
        let metaDesc = document.querySelector('meta[name="description"]');
        if (metaDesc && currentCamping.description) {
            metaDesc.content = currentCamping.description.substring(0, 160) + '...';
        }

        // PORNIREA LOGICII TALE EXISTENTE
        currentPricePerNight = parseFloat(currentCamping.price_per_night);
        renderCampingDetails();
        checkReviewEligibility();

    } catch (err) {
        const main = document.getElementById('detail-main');
        main.innerHTML = '';
        const wrap = document.createElement('div');
        wrap.style.cssText = 'padding:40px;text-align:center;';
        const h2 = document.createElement('h2');
        h2.style.color = '#EF6A00';
        h2.textContent = t('camping.load_err');
        const p = document.createElement('p');
        p.style.cssText = 'color:#666;margin-top:8px;';
        p.textContent = `Camping-ul "${slug}" nu există sau nu este public.`;
        const a = document.createElement('a');
        a.href = 'campings.html';
        a.style.cssText = 'display:inline-block;margin-top:16px;';
        a.className = 'btn-dark';
        a.textContent = '← Înapoi la lista de campinguri';
        wrap.append(h2, p, a);
        main.appendChild(wrap);
    }
});
function renderCampingDetails() {
    const main = document.getElementById('detail-main');
    const media = currentCamping.media || [];
    const node = cloneTemplate('tpl-detail-main');

    // Galerie
    const mainImgEl = node.querySelector('#main-gallery-img');
    mainImgEl.src = media.length > 0 ? media[0].url : '../assets/About1.jpg';
    mainImgEl.alt = currentCamping.name;

    const thumbList = node.querySelector('.thumbnail-list');
    const thumbSrcs = media.length > 0 ? media : [{ url: '../assets/About1.jpg' }];
    thumbSrcs.forEach((m, idx) => {
        const thumb = cloneTemplate('tpl-thumbnail').querySelector('img');
        thumb.src = m.url;
        if (idx === 0) thumb.classList.add('active');
        thumb.addEventListener('click', () => changeMainImage(thumb, m.url));
        thumbList.appendChild(thumb);
    });

    // Info
    const ratingStr = currentCamping.rating_avg
        ? `⭐ ${parseFloat(currentCamping.rating_avg).toFixed(1)} / 5` : 'Fara recenzii';
    node.querySelector('.cd-name').textContent    = currentCamping.name;
    node.querySelector('.cd-rating').textContent  = ratingStr;
    node.querySelector('.cd-address').textContent = ` ${currentCamping.address || currentCamping.region || 'Locatie necunoscuta'}`;
    node.querySelector('.cd-type').textContent    = `⛺ Tip: ${currentCamping.type}`;
    node.querySelector('.cd-description').textContent = currentCamping.description || 'Nicio descriere disponibila.';

    // Booking aside
    node.querySelector('.cd-book-title').textContent = t('camping.book_title');
    node.querySelector('.cd-book-type').textContent  = TYPE_LABELS[currentCamping.type] || currentCamping.type;
    const capEl = node.querySelector('.cd-book-capacity');
    if (currentCamping.capacity) {
        capEl.textContent = `Max ${currentCamping.capacity} persoane`;
        capEl.style.display = '';
    }
    node.querySelector('#calc-nights').textContent = `0 nopti x ${currentPricePerNight} RON`;
    node.querySelector('#btn-confirm-booking').textContent = t('camping.confirm_btn');

    // Append în DOM
    main.innerHTML = '';
    main.appendChild(node);

    // Events (după ce elementele sunt în DOM)
    document.getElementById('book-checkin').addEventListener('change', calculatePrice);
    document.getElementById('book-checkout').addEventListener('change', calculatePrice);
    document.getElementById('btn-confirm-booking').addEventListener('click', handleBooking);
    document.getElementById('btn-wishlist').addEventListener('click', toggleWishlist);
    document.getElementById('btn-submit-review').addEventListener('click', submitReview);

    // Star select
    const stars = document.querySelectorAll('#star-select span');
    stars.forEach(s => {
        s.addEventListener('click', () => {
            const val = s.dataset.val;
            window.currentReviewRating = val;
            stars.forEach(st => st.classList.toggle('selected', st.dataset.val <= val));
        });
    });

    // File preview
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

    loadReviews();
    initMiniMap();
    initWishlistBtn();
}

async function initWishlistBtn() {
    const btn = document.getElementById('btn-wishlist');
    if (!btn || !localStorage.getItem('cat_token') || !currentCamping) return;

    btn.style.display = 'block';
    btn.disabled = true;
    btn.textContent = '...';

    try {
        const data = await api.get('/api/sections');
        const favSection = (data?.sections || []).find(s => s.name === 'Favorite');

        if (favSection) {
            wishlistSectionId = favSection.id;
            const campData = await api.get(`/api/sections/${favSection.id}/campings`);
            campingInWishlist = (campData?.campings || []).some(c => c.id === currentCamping.id);
        }
    } catch (_) {}

    updateWishlistBtn();
}

function updateWishlistBtn() {
    const btn = document.getElementById('btn-wishlist');
    if (!btn) return;
    btn.disabled = false;
    if (campingInWishlist) {
        btn.textContent = t('camping.in_wishlist');
        btn.classList.add('btn-wishlist--active');
    } else {
        btn.textContent = t('camping.add_wishlist');
        btn.classList.remove('btn-wishlist--active');
    }
}

window.toggleWishlist = async function () {
    const btn = document.getElementById('btn-wishlist');
    if (!btn || !currentCamping) return;
    btn.disabled = true;

    try {
        if (campingInWishlist && wishlistSectionId) {
            await api.delete(`/api/sections/${wishlistSectionId}/campings/${currentCamping.id}`);
            campingInWishlist = false;
        } else {
            if (!wishlistSectionId) {
                const created = await api.post('/api/sections', { name: 'Favorite', color: '#EF6A00' });
                wishlistSectionId = created.section.id;
            }
            await api.post(`/api/sections/${wishlistSectionId}/campings`, { camping_id: currentCamping.id });
            campingInWishlist = true;
        }
        updateWishlistBtn();
    } catch (err) {
        btn.disabled = false;
        if (typeof showToast !== 'undefined') showToast(err.message || 'Eroare wishlist');
    }
};

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
            document.getElementById('calc-nights').textContent = `${diffDays} ${t('camping.nights_label')} ${currentPricePerNight} RON`;

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
        window.showToast("Selecteaza datele!", 'warning');
        return;
    }

    if (new Date(ci) >= new Date(co)) {
        window.showToast("Check-out trebuie sa fie dupa check-in!", 'warning');
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
        window.showToast(t('camping.book_ok'), 'success');
        window.location.href = "account/account.html";
    } catch (err) {
        window.showToast(err.message || t('camping.book_err'), 'error');
    }
}

async function checkReviewEligibility() {
    const container = document.getElementById('review-form-container');
    if (!container) return;

    const token = localStorage.getItem('cat_token');
    if (!token) {
        container.innerHTML = '';
        const p = document.createElement('p');
        p.className = 'review-login-prompt';
        const a = document.createElement('a');
        a.href = 'auth.html';
        a.textContent = 'Autentifica-te';
        p.appendChild(a);
        p.appendChild(document.createTextNode(' pentru a lasa o recenzie.'));
        container.appendChild(p);
        container.style.display = 'block';
        return;
    }

    try {
        const res = await api.get(`/api/campings/${currentCamping.id}/reviews`);
        const userRaw = localStorage.getItem('cat_user');
        const userId = userRaw ? JSON.parse(userRaw).id : null;
        const alreadyReviewed = userId && res.reviews && res.reviews.some(r => String(r.user_id) === String(userId));

        if (alreadyReviewed) {
            container.innerHTML = '';
            const p = document.createElement('p');
            p.className = 'review-already';
            p.textContent = 'Ai lasat deja o recenzie pentru acest camping.';
            container.appendChild(p);
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
            const frag = document.createDocumentFragment();

            res.reviews.forEach(r => {
                reviewDataMap[r.id] = { rating: r.rating, content: r.content || '', media: r.media || [] };
                const isOwn = currentUserId && String(r.user_id) === currentUserId;

                const node = cloneTemplate('tpl-review-item');
                const el   = node.querySelector('.review-item');
                el.id = `review-${r.id}`;
                node.querySelector('.review-body').id   = `review-body-${r.id}`;
                node.querySelector('.review-edit-form').id = `review-edit-${r.id}`;

                node.querySelector('.review-author').textContent = r.username || 'Utilizator';
                node.querySelector('.review-rating').textContent = `⭐ ${r.rating}`;

                if (r.content) {
                    const pEl = node.querySelector('.rv-content');
                    pEl.textContent = r.content;
                    pEl.style.display = '';
                }

                const mediaListEl = node.querySelector('.rv-media');
                (r.media || []).forEach(m => {
                    if (m.type === 'image') {
                        const img = document.createElement('img');
                        img.src = m.url;
                        img.className = 'review-media-img';
                        img.alt = 'media';
                        img.dataset.url = m.url;
                        img.addEventListener('click', () => openLightbox('image', m.url));
                        mediaListEl.appendChild(img);
                    } else if (m.type === 'video') {
                        const wrap = document.createElement('div');
                        wrap.className = 'review-media-video-wrap';
                        const vid = document.createElement('video');
                        vid.src = m.url;
                        vid.className = 'review-media-video';
                        vid.dataset.url = m.url;
                        vid.preload = 'metadata';
                        vid.muted = true;
                        vid.addEventListener('loadedmetadata', () => { vid.currentTime = 0.1; });
                        wrap.addEventListener('click', () => openLightbox('video', m.url));
                        wrap.appendChild(vid);
                        mediaListEl.appendChild(wrap);
                    } else if (m.type === 'audio') {
                        const aud = document.createElement('audio');
                        aud.src = m.url;
                        aud.controls = true;
                        aud.className = 'review-media-audio';
                        mediaListEl.appendChild(aud);
                    }
                });
                if (mediaListEl.children.length) mediaListEl.style.display = '';

                if (isOwn) {
                    const actionsEl = node.querySelector('.review-actions');
                    actionsEl.style.display = '';
                    actionsEl.querySelector('.btn-review-edit').addEventListener('click', () => {
                        const d = reviewDataMap[r.id];
                        openEditReview(r.id, d.rating, d.content);
                    });
                    actionsEl.querySelector('.btn-review-delete').addEventListener('click', () => deleteReview(r.id));
                }

                frag.appendChild(node);
            });

            list.appendChild(frag);
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

    editEl.innerHTML = '';
    let editRating = d.rating;
    editEl._editRating = editRating;

    // Star select
    const starWrap = document.createElement('div');
    starWrap.className = 'star-select';
    starWrap.id = `edit-stars-${id}`;
    for (let i = 1; i <= 5; i++) {
        const s = document.createElement('span');
        s.dataset.val = i;
        s.textContent = '★';
        s.style.cssText = `font-size:1.5rem;cursor:pointer;color:${i <= editRating ? 'var(--color-accent)' : '#ccc'}`;
        s.addEventListener('click', () => {
            editRating = i;
            editEl._editRating = i;
            starWrap.querySelectorAll('span').forEach(st => {
                st.style.color = parseInt(st.dataset.val) <= i ? 'var(--color-accent)' : '#ccc';
            });
        });
        starWrap.appendChild(s);
    }
    editEl.appendChild(starWrap);

    // Textarea
    const textarea = document.createElement('textarea');
    textarea.id = `edit-text-${id}`;
    textarea.className = 'edit-review-textarea';
    textarea.textContent = d.content;
    editEl.appendChild(textarea);

    // Existing media
    if (d.media.length) {
        const mediaList = document.createElement('div');
        mediaList.className = 'edit-media-list';
        mediaList.id = `edit-media-list-${id}`;
        d.media.forEach(m => {
            const item = cloneTemplate('tpl-edit-media-item').querySelector('.edit-media-item');
            item.id = `edit-media-item-${m.id}`;
            let preview;
            if (m.type === 'image') {
                preview = document.createElement('img');
                preview.src = m.url;
                preview.className = 'edit-media-thumb';
                preview.alt = '';
            } else if (m.type === 'audio') {
                preview = document.createElement('audio');
                preview.controls = true;
                preview.src = m.url;
                preview.className = 'edit-media-audio';
            } else if (m.type === 'video') {
                preview = document.createElement('video');
                preview.controls = true;
                preview.src = m.url;
                preview.className = 'edit-media-video';
            }
            if (preview) item.insertBefore(preview, item.firstChild);
            const delBtn = item.querySelector('.edit-media-delete');
            delBtn.dataset.mediaId = m.id;
            delBtn.addEventListener('click', async () => {
                try {
                    await api.delete(`/api/media/review/${m.id}`);
                    item.remove();
                    reviewDataMap[id].media = reviewDataMap[id].media.filter(x => x.id !== m.id);
                } catch (err) {
                    window.showToast(err.message || t('camping.delete_media_err'), 'error');
                }
            });
            mediaList.appendChild(item);
        });
        editEl.appendChild(mediaList);
    }

    // File input
    const label = document.createElement('label');
    label.className = 'review-media-label';
    label.textContent = 'Adauga fisiere noi';
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.id = `edit-files-${id}`;
    fileInput.multiple = true;
    fileInput.accept = 'image/*,audio/*,video/*';
    label.appendChild(fileInput);
    editEl.appendChild(label);

    // Actions
    const actions = document.createElement('div');
    actions.className = 'edit-review-actions';
    const saveBtn = document.createElement('button');
    saveBtn.className = 'btn-dark';
    saveBtn.textContent = 'Salveaza';
    saveBtn.addEventListener('click', () => saveEditReview(id));
    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'btn-review-cancel';
    cancelBtn.textContent = 'Anuleaza';
    cancelBtn.addEventListener('click', () => cancelEditReview(id));
    actions.append(saveBtn, cancelBtn);
    editEl.appendChild(actions);
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
                window.showToast("Salvat, dar unele fisiere au esuat: " + errors.join(', '), 'warning');
            }
        }

        loadReviews();
    } catch (err) {
        window.showToast(err.message || t('camping.save_review_err'), 'error');
    }
};

window.deleteReview = async function (id) {
    const confirmed = await window.showConfirm(t('camping.delete_review_confirm'), { type: 'danger' });
    if (!confirmed) return;
    try {
        await api.delete(`/api/reviews/${id}`);
        loadReviews();
        checkReviewEligibility();
    } catch (err) {
        window.showToast(err.message || t('camping.delete_review_err'), 'error');
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
        window.showToast("Selecteaza o nota!", 'warning');
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
                window.showToast("Recenzie adaugata, dar unele fisiere au esuat: " + errors.join(', '), 'warning');
            }
        }

        window.location.reload();
    } catch (err) {
        window.showToast(err.message || t('camping.review_err'), 'error');
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
    'natural=peak': { emoji: '️', label: 'Vârf' },
    'amenity=drinking_water': { emoji: '', label: 'Apă potabilă' },
    'tourism=viewpoint': { emoji: '', label: 'Belvedere' },
    'amenity=parking': { emoji: '', label: 'Parcare' },
    'leisure=picnic_table': { emoji: '️', label: 'Picnic' },
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
                pois.innerHTML = '';
                Object.values(groups).forEach(g => {
                    const chip = cloneTemplate('tpl-poi-chip').querySelector('.poi-chip');
                    chip.textContent = `${g.emoji} ${g.label} `;
                    const bold = document.createElement('b');
                    bold.textContent = g.count;
                    chip.appendChild(bold);
                    pois.appendChild(chip);
                });
                section.style.display = 'block';
            }
        }
    } catch (_) {
        // Overpass unavailable — skip silently
    }
}
