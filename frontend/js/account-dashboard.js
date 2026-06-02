// Trimite un singur fisier la un endpoint multipart (auth automat din localStorage)
async function uploadFile(endpoint, file) {
    const token = localStorage.getItem('cat_token');
    const fd    = new FormData();
    fd.append('file', file);
    const res = await fetch(API_BASE + endpoint, {
        method:  'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body:    fd,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || 'Eroare upload');
    return data;
}

// Normalizeaza URL-uri media (vechi: relative, noi: absolute)
function mediaUrl(url) {
    if (!url) return '../../assets/images/camping-placeholder.jpg';
    return url.startsWith('/') ? url : '/cat/public/' + url;
}

function loadMyCampsites() {
    const container = document.getElementById('my-campsites-container');
    if (!container) return;

    api.get('/api/campings/mine')
        .then(res => {
            const createCard = container.querySelector('.create-card');
            container.innerHTML = '';
            if (createCard) container.appendChild(createCard);

            if (!res || !res.campings || res.campings.length === 0) return;

            res.campings.forEach(camping => {
                const name       = camping.name   || 'Camping fără nume';
                const region     = camping.region || 'România';
                const address    = camping.address || '';
                const type       = camping.type   || 'Nespecificat';
                const coverImg   = mediaUrl(camping.cover_url);

                const st = { '-1': 'cs-rejected', '0': 'cs-pending', '1': 'cs-approved', '2': 'cs-rejected' };
                const statusClass = st[String(camping.approval_status)] || 'cs-pending';

                let feedbackMsg  = '';
                let resubmitBtn  = '';
                if (camping.approval_status === 2 && camping.admin_feedback) {
                    feedbackMsg = `<p class="camping-admin-feedback" style="color:red;font-size:12px;">${camping.admin_feedback}</p>`;
                }
                if (camping.approval_status === -1 || camping.approval_status === 2) {
                    resubmitBtn = `<button class="btn-resubmit" onclick="event.stopPropagation();resubmitCamping(${camping.id})">Retrimite</button>`;
                }

                container.insertAdjacentHTML('beforeend', `
                    <div class="cat-card ${statusClass}" data-id="${camping.id}">
                        <div class="cat-card-img-wrapper">
                            <img src="${coverImg}" alt="${name}" onerror="this.onerror=null;this.src='../../assets/images/camping-placeholder.jpg'">
                        </div>
                        <div class="cat-card-content">
                            <h3 class="cat-card-title">${name}</h3>
                            <p class="cat-card-detail"><strong>Locație:</strong> ${region}, ${address}</p>
                            <p class="cat-card-detail"><strong>Tip:</strong> ${type}</p>
                            ${feedbackMsg}
                            ${resubmitBtn}
                        </div>
                    </div>
                `);
            });
        })
        .catch(err => console.error('Eroare la încărcarea campingurilor:', err));
}

function initProfile() {
    if (!localStorage.getItem('cat_token')) return;

    api.get('/api/auth/me')
        .then(res => {
            if (!res || !res.user) return;
            const user = res.user;

            const avatarSrc = mediaUrl(user.avatar_url || '');
            const profileImg = document.getElementById('profile-avatar-img');
            const pillAvatar = document.getElementById('user-pill-avatar');
            if (profileImg) profileImg.src = avatarSrc;
            if (pillAvatar) pillAvatar.src  = avatarSrc;

            if (document.getElementById('user-pill-name'))
                document.getElementById('user-pill-name').textContent = user.full_name || user.username || 'Utilizator';
            if (document.getElementById('user-pill-email'))
                document.getElementById('user-pill-email').textContent = user.email || '';
            if (document.getElementById('profile-username'))
                document.getElementById('profile-username').value = user.username || '';
            if (document.getElementById('profile-fullname'))
                document.getElementById('profile-fullname').value = user.full_name || '';
            if (document.getElementById('profile-email'))
                document.getElementById('profile-email').value = user.email || '';
        })
        .catch(err => console.error('Eroare la încărcarea profilului:', err));
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input, textarea').forEach(el => {
        el.addEventListener('input', () => {
            if (el.value.trim() !== '') el.style.borderColor = '#ccc';
        });
    });

    initMapLogic();
    loadMyCampsites();
    initProfile();

    // Avatar upload
    const avatarInput = document.getElementById('avatar-upload');
    const avatarImg   = document.getElementById('profile-avatar-img');

    if (avatarInput && avatarImg) {
        avatarInput.addEventListener('change', async function () {
            if (!this.files || !this.files[0]) return;
            const file = this.files[0];

            // Previzualizare instantanee
            const reader = new FileReader();
            reader.onload = e => {
                avatarImg.src = e.target.result;
                const pillAvatar = document.getElementById('user-pill-avatar');
                if (pillAvatar) pillAvatar.src = e.target.result;
            };
            reader.readAsDataURL(file);

            try {
                const data = await uploadFile('/api/users/me/avatar', file);
                if (data.user) {
                    const url = mediaUrl(data.user.avatar_url);
                    avatarImg.src = url + '?t=' + Date.now();
                    const pillAvatar = document.getElementById('user-pill-avatar');
                    if (pillAvatar) pillAvatar.src = url + '?t=' + Date.now();

                    try {
                        let stored = JSON.parse(localStorage.getItem('cat_user') || '{}');
                        stored.avatar_url = data.user.avatar_url;
                        localStorage.setItem('cat_user', JSON.stringify(stored));
                    } catch (e) { /* ignore */ }
                }
            } catch (err) {
                showToast(err.message || 'Eroare la încărcarea avatarului', 'error');
            }
        });
    }
});

// Manager global click-uri
document.addEventListener('click', async function (e) {

    // A. Click pe card → deschide detalii
    const card = e.target.closest('.cat-card');
    if (card && !e.target.closest('.btn-resubmit')) {
        openCampsiteDetails(card.getAttribute('data-id'));
    }

    // B. Navigare NEXT (stepper formular)
    if (e.target.classList.contains('campsite-next')) {
        const currentStep = e.target.closest('.campsite-step');
        if (!validateCurrentStep(currentStep)) {
            alert('Te rugăm să completezi toate câmpurile obligatorii!');
            return;
        }
        const nextStep = e.target.getAttribute('data-next');
        document.querySelectorAll('.campsite-step').forEach(s => s.style.display = 'none');
        const target = document.getElementById('c-step-' + nextStep);
        if (target) target.style.display = 'block';
        updateCampsiteStepper(nextStep);
    }

    // C. Buton FINISH formular
    if (e.target.classList.contains('campsite-finish')) {
        e.preventDefault();

        const currentStep = e.target.closest('.campsite-step');
        if (!validateCurrentStep(currentStep)) {
            alert('Te rugăm să adaugi pozele obligatorii!');
            return;
        }

        const editId = document.getElementById('edit-campsite-id').value;
        const address = [
            document.getElementById('c-street').value,
            'nr.', document.getElementById('c-number').value + ',',
            document.getElementById('c-city').value + ',',
            document.getElementById('c-zip').value,
        ].join(' ');

        const body = {
            name:            document.getElementById('c-name').value,
            description:     document.getElementById('c-full-desc').value,
            address,
            region:          document.getElementById('c-city').value,
            latitude:        parseFloat(document.getElementById('c-lat').value),
            longitude:       parseFloat(document.getElementById('c-lng').value),
            capacity:        document.getElementById('campsite-capacity').value ? parseInt(document.getElementById('campsite-capacity').value) : null,
            price_per_night: document.getElementById('campsite-price').value    ? parseFloat(document.getElementById('campsite-price').value)   : null,
            environments: [...document.querySelectorAll('input[name="environment"]:checked')].map(el => el.value),
            facilities:   [...document.querySelectorAll('input[name="facility"]:checked')].map(el => el.value),
        };

        try {
            let campingId;

            if (editId) {
                await api.patch('/api/campings/' + editId, body);
                campingId = parseInt(editId);
            } else {
                const res = await api.post('/api/campings', body);
                campingId = res.camping.id;
            }

            // Upload cover photo
            const coverInput = document.getElementById('c-cover-upload');
            if (coverInput && coverInput.files[0]) {
                await uploadFile('/api/campings/' + campingId + '/media', coverInput.files[0]);
            }

            // Upload gallery photos
            const galleryInput = document.getElementById('c-gallery-upload');
            if (galleryInput) {
                for (const file of galleryInput.files) {
                    await uploadFile('/api/campings/' + campingId + '/media', file);
                }
            }

            showToast(editId ? 'Locația a fost actualizată!' : 'Locația a fost trimisă spre aprobare.', 'success');
            document.getElementById('edit-campsite-id').value = '';
            document.getElementById('create-campsite-tab').style.display = 'none';
            document.getElementById('dashboard-tab').style.display = 'block';
            loadMyCampsites();

        } catch (err) {
            showToast(err.message || 'Eroare la salvare', 'error');
        }
    }

    // D. Navigare bulina înapoi în stepper
    const stepItem = e.target.closest('.campsite-stepper .step-item');
    if (stepItem) {
        const targetId = parseInt(stepItem.getAttribute('data-c-step'));
        const active   = document.querySelector('.campsite-step[style*="block"]');
        if (active) {
            const currentId = parseInt(active.id.replace('c-step-', ''));
            if (targetId < currentId) {
                document.querySelectorAll('.campsite-step').forEach(s => s.style.display = 'none');
                document.getElementById('c-step-' + targetId).style.display = 'block';
                updateCampsiteStepper(targetId);
            }
        }
    }

    // E. Buton Edit din detalii
    if (e.target.id === 'btn-edit-campsite') {
        if (!currentCampsiteData) return;
        document.getElementById('campsite-details-view').style.display = 'none';
        document.getElementById('create-campsite-tab').style.display   = 'block';
        resetCampsiteForm();

        document.getElementById('edit-campsite-id').value = currentCampsiteData.id;
        document.getElementById('c-name').value           = currentCampsiteData.name        || '';
        document.getElementById('c-full-desc').value      = currentCampsiteData.description  || '';
        document.getElementById('c-lat').value            = currentCampsiteData.latitude      || '';
        document.getElementById('c-lng').value            = currentCampsiteData.longitude     || '';
        document.getElementById('campsite-price').value   = currentCampsiteData.price_per_night || '';
        document.getElementById('campsite-capacity').value = currentCampsiteData.capacity     || '';
        document.getElementById('c-city').value           = currentCampsiteData.region        || '';
        document.getElementById('c-street').value         = currentCampsiteData.address       || '';
        document.getElementById('c-number').value         = '-';
        document.getElementById('c-zip').value            = '-';

        const envs = currentCampsiteData.environments || [];
        document.querySelectorAll('input[name="environment"]').forEach(cb => {
            cb.checked = envs.includes(cb.value);
        });

        const facs = currentCampsiteData.facilities || [];
        document.querySelectorAll('input[name="facility"]').forEach(cb => {
            cb.checked = facs.includes(cb.value);
        });
    }

    // F. Buton Messages din detalii
    if (e.target.id === 'btn-messages') {
        if (!currentCampsiteData) return;
        document.getElementById('campsite-details-view').style.display  = 'none';
        document.getElementById('campsite-messages-view').style.display = 'block';

        const msgContainer  = document.getElementById('messages-container');
        const feedbackText  = currentCampsiteData.admin_feedback;
        msgContainer.innerHTML = feedbackText && feedbackText.trim()
            ? `<div class="message-card">
                   <span class="message-sender-info">Echipa de Aprobare CaT</span>
                   <p class="message-body">${feedbackText}</p>
               </div>`
            : '<p class="no-messages-text">Nu ai niciun mesaj nou pentru această locație.</p>';
    }

    // G. Înapoi de la mesaje la detalii
    if (e.target.id === 'btn-back-from-messages' || e.target.closest('#btn-back-from-messages')) {
        document.getElementById('campsite-messages-view').style.display = 'none';
        document.getElementById('campsite-details-view').style.display  = 'block';
    }

    // H. Buton Delete din detalii
    if (e.target.id === 'btn-delete-campsite') {
        if (!currentCampsiteData) return;
        const ok = await showConfirm(
            `Ești sigur că vrei să ștergi "${currentCampsiteData.name}"? Acțiunea este ireversibilă.`,
            { title: 'Șterge locația', confirmText: 'Șterge', type: 'error' }
        );
        if (!ok) return;

        try {
            await api.delete('/api/campings/' + currentCampsiteData.id);
            showToast('Locația a fost ștearsă.', 'success');
            document.getElementById('campsite-details-view').style.display = 'none';
            document.getElementById('dashboard-tab').style.display          = 'block';
            currentCampsiteData = null;
            loadMyCampsites();
        } catch (err) {
            showToast(err.message || 'Eroare la ștergere', 'error');
        }
    }
});

let currentCampsiteData = null;

async function openCampsiteDetails(id) {
    try {
        const res            = await api.get('/api/campings/' + id);
        const data           = res.camping;
        currentCampsiteData  = data;

        document.getElementById('dashboard-tab').style.display        = 'none';
        document.getElementById('campsite-details-view').style.display = 'block';

        document.getElementById('detail-title').textContent       = data.name;
        document.getElementById('detail-address').textContent     = [data.address, data.region].filter(Boolean).join(', ');
        document.getElementById('detail-description').textContent  = data.description || 'Fără descriere disponibilă.';
        document.getElementById('detail-type').textContent        = data.type;
        document.getElementById('detail-price').textContent       = data.price_per_night ? data.price_per_night + ' RON' : 'Nespecificat';
        document.getElementById('detail-capacity').textContent    = data.capacity ? data.capacity + ' pers.' : 'Nespecificat';

        const badge        = document.getElementById('detail-status-badge');
        const feedbackAlert = document.getElementById('admin-feedback-alert');
        if (data.approval_status === 1) {
            badge.textContent = 'Aprobat';
            badge.className   = 'status-badge badge-approved';
            feedbackAlert.style.display = 'none';
        } else if (data.approval_status === 2) {
            badge.textContent = 'Respins cu feedback';
            badge.className   = 'status-badge badge-rejected';
            feedbackAlert.style.display = 'block';
            document.getElementById('feedback-text').textContent = data.admin_feedback || 'Niciun motiv specificat.';
        } else if (data.approval_status === -1) {
            badge.textContent = 'Respins';
            badge.className   = 'status-badge badge-rejected';
            feedbackAlert.style.display = 'none';
        } else {
            badge.textContent = 'În așteptare';
            badge.className   = 'status-badge badge-pending';
            feedbackAlert.style.display = 'none';
        }

        const facContainer = document.getElementById('detail-facilities');
        facContainer.innerHTML = (data.facilities || []).length
            ? data.facilities.map(f => `<span class="tag tag-facility">${f}</span>`).join('')
            : '<span class="text-muted">Nicio facilitate bifată</span>';

        const envContainer = document.getElementById('detail-environments');
        envContainer.innerHTML = (data.environments || []).length
            ? data.environments.map(v => `<span class="tag tag-environment">${v}</span>`).join('')
            : '<span class="text-muted">Niciun mediu bifat</span>';

        const heroImg      = document.getElementById('detail-hero-img');
        const thumbContainer = document.getElementById('detail-thumbnails');
        if (data.media && data.media.length > 0) {
            heroImg.src = mediaUrl(data.media[0].url);
            thumbContainer.innerHTML = data.media
                .map(m => `<img src="${mediaUrl(m.url)}" class="thumb-img" onclick="document.getElementById('detail-hero-img').src=this.src" alt="Thumbnail">`)
                .join('');
        } else {
            heroImg.src = '../../assets/images/camping-placeholder.jpg';
            thumbContainer.innerHTML = '';
        }
    } catch (err) {
        showToast(err.message || 'Eroare la preluarea detaliilor', 'error');
    }
}

document.getElementById('btn-back-to-grid').addEventListener('click', function () {
    document.getElementById('campsite-details-view').style.display = 'none';
    document.getElementById('dashboard-tab').style.display          = 'block';
});

function openCreateCampsiteForm() {
    document.getElementById('dashboard-tab').style.display        = 'none';
    document.getElementById('create-campsite-tab').style.display  = 'block';
    document.getElementById('edit-campsite-id').value = '';
    resetCampsiteForm();
}

function resetCampsiteForm() {
    document.querySelectorAll('.campsite-step').forEach(s => s.style.display = 'none');
    const s1 = document.getElementById('c-step-1');
    if (s1) s1.style.display = 'block';
    updateCampsiteStepper(1);
}

function updateCampsiteStepper(stepNumber) {
    document.querySelectorAll('.campsite-stepper .step-item').forEach(item => {
        item.classList.toggle('active', parseInt(item.getAttribute('data-c-step')) <= stepNumber);
    });
}

function validateCurrentStep(stepElement) {
    if (!stepElement) return false;
    let isValid = true;

    stepElement.querySelectorAll('input[type="text"][required], input[type="number"][required], textarea[required], select[required]').forEach(input => {
        if (input.value.trim() === '') { input.style.borderColor = 'red'; isValid = false; }
        else input.style.borderColor = '#ccc';
    });

    stepElement.querySelectorAll('input[type="file"][required]').forEach(input => {
        const box = input.closest('.upload-dashed-box');
        if (input.files.length === 0) { if (box) box.style.borderColor = 'red'; isValid = false; }
        else if (box) box.style.borderColor = '#ccc';
    });

    stepElement.querySelectorAll('.req-group').forEach(group => {
        const checkboxes = group.querySelectorAll('input[type="checkbox"]');
        if (checkboxes.length > 0) {
            if (group.querySelectorAll('input[type="checkbox"]:checked').length === 0) {
                group.style.border = '1px solid red'; isValid = false;
            } else {
                group.style.border = 'none';
            }
        } else {
            group.querySelectorAll('select, input[type="number"]').forEach(input => {
                if (input.value.trim() === '') { input.style.borderColor = 'red'; isValid = false; }
                else input.style.borderColor = '#ccc';
            });
        }
    });

    return isValid;
}

const cCoverUpload = document.getElementById('c-cover-upload');
if (cCoverUpload) {
    cCoverUpload.addEventListener('change', function () {
        const label = document.getElementById('cover-filename');
        if (label) label.textContent = this.files.length > 0 ? `${this.files.length} poză selectată` : '';
    });
}

const cGalleryUpload = document.getElementById('c-gallery-upload');
if (cGalleryUpload) {
    cGalleryUpload.addEventListener('change', function () {
        const label = document.getElementById('gallery-count');
        if (label) label.textContent = this.files.length > 0 ? `${this.files.length} poze selectate` : '';
    });
}

async function resubmitCamping(id) {
    const ok = await showConfirm('Cererea va fi trimisă din nou spre aprobare.', {
        title: 'Retrimite cererea', confirmText: 'Retrimite', type: 'warning'
    });
    if (!ok) return;
    try {
        await api.post('/api/campings/' + id + '/resubmit', {});
        showToast('Cererea a fost retrimisă spre aprobare.', 'success');
        loadMyCampsites();
    } catch (err) {
        showToast(err.message || 'Eroare la retrimitere', 'error');
    }
}

function initMapLogic() {
    const btnPinMap         = document.getElementById('btn-pin-map');
    const mapModal          = document.getElementById('map-modal');
    const closeMapBtn       = document.getElementById('close-map-modal');
    const confirmLocationBtn = document.getElementById('confirm-location-btn');
    const coordsDisplay     = document.getElementById('selected-coords');
    const inputLat          = document.getElementById('c-lat');
    const inputLng          = document.getElementById('c-lng');
    let pickerMap = null, pickerMarker = null, tempLat = null, tempLng = null;

    if (btnPinMap && mapModal) {
        btnPinMap.addEventListener('click', () => {
            mapModal.style.display = 'flex';
            if (!pickerMap) {
                pickerMap = L.map('picker-map').setView([45.9432, 24.9668], 6);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(pickerMap);
                pickerMap.on('click', function (ev) {
                    tempLat = ev.latlng.lat.toFixed(6);
                    tempLng = ev.latlng.lng.toFixed(6);
                    if (pickerMarker) pickerMap.removeLayer(pickerMarker);
                    pickerMarker = L.marker([tempLat, tempLng]).addTo(pickerMap);
                    if (coordsDisplay) coordsDisplay.textContent = `Coordonate: ${tempLat}, ${tempLng}`;
                });
            }
            setTimeout(() => pickerMap.invalidateSize(), 200);
        });
        if (closeMapBtn) closeMapBtn.addEventListener('click', () => mapModal.style.display = 'none');
        if (confirmLocationBtn) {
            confirmLocationBtn.addEventListener('click', () => {
                if (tempLat && tempLng) {
                    inputLat.value = tempLat;
                    inputLng.value = tempLng;
                    mapModal.style.display = 'none';
                } else {
                    alert('Te rog dă click pe hartă!');
                }
            });
        }
    }
}
