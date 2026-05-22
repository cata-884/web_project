let currentCamping = null;
let currentPricePerNight = 0;
let userBookings = [];

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
        // Wait, backend has show(int $id). But we added findBySlug in Model. 
        // We will just fetch all and find by slug if id is missing, or adjust backend.
        // For simplicity, assuming backend supports ?search=slug or we use the specific endpoint.
        // Actually, the easiest way for now without changing backend is fetching campings and matching if slug is used.
        // Wait, the prompt asked for "camping.html?id=X sau ?slug=X". 
        
        // Let's see if we can just get it. If id exists:
        if (id) {
            const res = await api.get(`/api/campings/${id}`);
            currentCamping = res.camping;
        } else {
            // Fetch list and find the one with the slug
            const res = await api.get(`/api/campings?limit=100`);
            currentCamping = res.campings.find(c => c.slug === slug);
            if(currentCamping) {
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
        console.error(err);
        document.getElementById('detail-main').innerHTML = '<h2 style="color:red">Eroare la incarcarea campingului.</h2>';
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

    if(thumbnailsHTML === '') {
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
                    <span>${currentCamping.address || currentCamping.region || 'Locatie necunoscuta'}</span>
                    <span>Tip: ${currentCamping.type}</span>
                </div>

                <div class="camping-description">
                    ${currentCamping.description || 'Nicio descriere disponibila.'}
                </div>

                <div id="camping-map" class="camping-mini-map"></div>

                <div class="reviews-section" id="reviews-section">
                    <h3>Recenzii</h3>
                    <div id="review-form-container" class="review-form-container">
                        <h3>Scrie o recenzie</h3>
                        <div class="star-select" id="star-select">
                            <span data-val="1">★</span><span data-val="2">★</span><span data-val="3">★</span><span data-val="4">★</span><span data-val="5">★</span>
                        </div>
                        <textarea id="review-text" placeholder="Cum a fost experienta ta?"></textarea>
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
                    <div class="form-group">
                        <label>Data Check-in</label>
                        <input type="date" id="book-checkin" onchange="calculatePrice()">
                    </div>
                    <div class="form-group">
                        <label>Data Check-out</label>
                        <input type="date" id="book-checkout" onchange="calculatePrice()">
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

    // Setup star logic
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
    }, 100);

    loadReviews();
    initMiniMap();
}

window.changeMainImage = function(thumbElement, url) {
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
            guests_count: 2, // Hardcoded for now based on prompt/UI
            special_requests: ""
        });
        alert("Rezervare creata cu succes!");
        window.location.href = "account/account.html";
    } catch (err) {
        alert(err.message || "Eroare la rezervare. Esti autentificat?");
    }
}

async function checkReviewEligibility() {
    try {
        const res = await api.get(`/api/bookings?camping_id=${currentCamping.id}`);
        // If the user has a completed booking for this camping
        const hasCompleted = res.bookings && res.bookings.some(b => b.status === 'completed');
        
        if (hasCompleted) {
            document.getElementById('review-form-container').style.display = 'block';
        }
    } catch (e) {
        // Not logged in or error, ignore
    }
}

async function loadReviews() {
    try {
        // Fetch reviews (assuming backend supports it or we fetch and filter)
        const res = await api.get(`/api/reviews?camping_id=${currentCamping.id}`);
        const list = document.getElementById('reviews-list');
        
        if (res.reviews && res.reviews.length > 0) {
            list.innerHTML = '';
            res.reviews.forEach(r => {
                list.innerHTML += `
                    <div class="review-item">
                        <div class="review-header">
                            <span class="review-author">${r.author_name || 'Utilizator'}</span>
                            <span class="review-rating">★ ${r.rating}</span>
                        </div>
                        <p>${r.comment}</p>
                    </div>
                `;
            });
        }
    } catch(e) {
        // Ignored or handle properly
    }
}

window.submitReview = async function() {
    const rating = window.currentReviewRating;
    const comment = document.getElementById('review-text').value;

    if (!rating) {
        alert("Selecteaza o nota!");
        return;
    }

    try {
        await api.post('/api/reviews', {
            camping_id: currentCamping.id,
            rating: parseInt(rating),
            comment: comment
        });
        alert("Recenzie adaugata!");
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

    // Fix Leaflet rendering in hidden/dynamic containers
    setTimeout(() => { miniMap.invalidateSize(); }, 200);
}
