  //Definim functia GLOBALA imbinata
  function loadMyCampsites() {
    const container = document.getElementById('my-campsites-container');
    if (!container) return;

    const token = localStorage.getItem('cat_token');

    fetch('/cat/public/api/get_my_campsites.php', {
        method: 'GET',
        headers: { 'Authorization': `Bearer ${token}` }
    })
    .then(response => response.json())
    .then(res => {
        if (!res.success) {
            console.error("Eroare API:", res.message);
            return;
        }

        const createCard = container.querySelector('.create-card');
        container.innerHTML = '';
        if (createCard) container.appendChild(createCard);

        if (!res.data || res.data.length === 0) return;

        res.data.forEach(camping => {
            //Variabile de siguranta
            const name = camping.name || 'Camping fără nume';
            const descText = camping.description || 'Fără descriere.';
            const region = camping.region || 'România';
            const address = camping.address || '';
            const type = camping.type || 'Nespecificat';
            const coverImg = camping.cover_url ? `/cat/public/${camping.cover_url}` : '../../assets/images/camping-placeholder.jpg';

            //Status pentru dunga
            const st = { '-1': 'cs-rejected', '0': 'cs-pending', '1': 'cs-approved', '2': 'cs-rejected' };
            const statusClass = st[String(camping.approval_status)] || 'cs-pending';

            //Butoane (doar daca sunt necesare)
            let feedbackMsg = '';
            let resubmitBtn = '';

            if (camping.approval_status == 2 && camping.admin_feedback) {
                feedbackMsg = `<p class="camping-admin-feedback" style="color:red; font-size:12px;">${camping.admin_feedback}</p>`;
            }
            if (camping.approval_status == -1 || camping.approval_status == 2) {
                resubmitBtn = `<button class="btn-resubmit" onclick="event.stopPropagation(); resubmitCamping(${camping.id})">Retrimite</button>`;
            }

            //HTML
            const cardHTML = `
                <div class="cat-card ${statusClass}" data-id="${camping.id}">
                    <div class="cat-card-img-wrapper">
                        <img src="${coverImg}" alt="${name}" onerror="this.onerror=null; this.src='../../assets/images/camping-placeholder.jpg';">
                    </div>
                    <div class="cat-card-content">
                        <h3 class="cat-card-title">${name}</h3>
                        <p class="cat-card-desc">${descText}</p>
                        <p class="cat-card-detail"><strong>Locație:</strong> ${region}, ${address}</p>
                        <p class="cat-card-detail"><strong>Tip:</strong> ${type}</p>
                        ${feedbackMsg}
                        ${resubmitBtn}
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', cardHTML);
        });
    })
    .catch(err => console.error("Eroare rețea Fetch:", err));
}

        //Apelam la DOMContentLoaded
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('input, textarea').forEach(el => {
                el.addEventListener('input', () => {
                    if (el.value.trim() !== '') el.style.borderColor = '#ccc';
                });
            });

            initMapLogic();
            loadMyCampsites();


const avatarInput = document.getElementById('avatar-upload');
const avatarImg = document.getElementById('profile-avatar-img');

if (avatarInput && avatarImg) {
    avatarInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const file = this.files[0];

            //PREVIZUALIZARE INSTANTANEE
            // Schimbam imediat ambele poze pe ecran (mare si mica) pana se incarca pe server
            const reader = new FileReader();
            reader.onload = function(e) {
                avatarImg.src = e.target.result;
                const pillAvatar = document.getElementById('user-pill-avatar');
                if (pillAvatar) pillAvatar.src = e.target.result;
            }
            reader.readAsDataURL(file);

            //TRIMITEM PE SERVER
            const formData = new FormData();
            formData.append('avatar', file);

            const token = localStorage.getItem('cat_token');

            fetch('/cat/public/api/upload_avatar.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {

                    // TRUCUL ANTI-CACHE: Adaugam milisecundele curente la finalul linkului (?t=1234567)
                    // Astfel, browserul este fortat sa reincarce poza proaspata de pe server!
                    const timestamp = new Date().getTime();
                    const caleaPozeiNoi = '/cat/public/' + data.avatar_url + '?t=' + timestamp;

                    // Actualizam linkurile finale sigure de pe server
                    avatarImg.src = caleaPozeiNoi;

                    const pillAvatar = document.getElementById('user-pill-avatar');
                    if (pillAvatar) {
                        pillAvatar.src = caleaPozeiNoi;
                    }

                    // Salvam in memoria browserului (salvam linkul curat, fara timestamp)
                    try {
                        let userData = JSON.parse(localStorage.getItem('cat_user') || '{}');
                        userData.avatar_url = data.avatar_url;
                        localStorage.setItem('cat_user', JSON.stringify(userData));
                    } catch (e) {
                        console.warn("Eroare localStorage:", e);
                    }

                } else {
                    alert("Eroare la încărcare: " + data.message);
                }
            })
            .catch(err => {
                console.error("Eroare upload avatar:", err);
                alert("Eroare de rețea. Încearcă din nou.");
            });
        }
    });
}
initProfile();
        });


     function initProfile() {
    const token = localStorage.getItem('cat_token');
    if (!token) return; // Daca nu e logat, nu avem ce incarca

    fetch('/cat/public/api/get_profile.php', {
        method: 'GET',
        headers: { 'Authorization': `Bearer ${token}` }
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            const user = res.data;

            // Cream link-ul O SINGURA DATA ca sa fim siguri ca e identic
          // Construim calea corecta prin puntea de la Docker
const caleaPozei = user.avatar_url ? '/cat/public/' + user.avatar_url : '../../assets/profil.jpg';

//Schimbam poza MARE din profil
if (document.getElementById('profile-avatar-img')) {
    document.getElementById('profile-avatar-img').src = caleaPozei;
}

//Schimbam poza MICA din coltul dreapta-sus (cea din div-ul trimis de tine)
if (document.getElementById('user-pill-avatar')) {
    document.getElementById('user-pill-avatar').src = caleaPozei;
}

            //Completam numele si emailul in dreapta sus
            if (document.getElementById('user-pill-name')) {
                document.getElementById('user-pill-name').textContent = user.full_name || user.username || 'Utilizator';
            }
            if (document.getElementById('user-pill-email')) {
                document.getElementById('user-pill-email').textContent = user.email || '';
            }

            //Completam campurile din formular
            if (document.getElementById('profile-username')) {
                document.getElementById('profile-username').value = user.username || '';
            }
            if (document.getElementById('profile-fullname')) {
                document.getElementById('profile-fullname').value = user.full_name || '';
            }
            if (document.getElementById('profile-email')) {
                document.getElementById('profile-email').value = user.email || '';
            }
        } else {
            console.error("Eroare la incarcarea profilului:", res.message);
        }
    })
    .catch(err => console.error("Eroare retea profil:", err));
}

        //Manager Global Click-uri
        document.addEventListener('click', function (e) {

            // A. Click pe un card pentru deschiderea detaliilor
            const card = e.target.closest('.cat-card');
            // Daca apasa pe butonul de retrimite, nu deschidem detaliile
            if (card && !e.target.closest('.btn-resubmit')) {
                const campsiteId = card.getAttribute('data-id');
                openCampsiteDetails(campsiteId);
            }

            // B. Navigare NEXT (Stepper Formular)
            if (e.target.classList.contains('campsite-next')) {
                const currentStepElement = e.target.closest('.campsite-step');
                if (!validateCurrentStep(currentStepElement)) {
                    alert("Te rugăm să completezi toate câmpurile obligatorii!");
                    return;
                }
                const nextStep = e.target.getAttribute('data-next');
                document.querySelectorAll('.campsite-step').forEach(s => s.style.display = 'none');
                const target = document.getElementById('c-step-' + nextStep);
                if (target) target.style.display = 'block';
                updateCampsiteStepper(nextStep);
            }

            // C. Butonul FINISH Formular
            if (e.target.classList.contains('campsite-finish')) {
                e.preventDefault();

                const currentStepElement = e.target.closest('.campsite-step');
                if (!validateCurrentStep(currentStepElement)) {
                    alert("Te rugăm să adaugi pozele obligatorii!");
                    return;
                }

                const formData = new FormData();
                formData.append('name', document.getElementById('c-name').value);
                formData.append('short_desc', document.getElementById('c-short-desc').value);
                formData.append('full_desc', document.getElementById('c-full-desc').value);
                formData.append('street', document.getElementById('c-street').value);
                formData.append('number', document.getElementById('c-number').value);
                formData.append('city', document.getElementById('c-city').value);
                formData.append('zip', document.getElementById('c-zip').value);
                formData.append('lat', document.getElementById('c-lat').value);
                formData.append('lng', document.getElementById('c-lng').value);
                formData.append('capacity', document.getElementById('campsite-capacity').value);
                formData.append('price_per_night', document.getElementById('campsite-price').value);

                document.querySelectorAll('input[name="environment"]:checked').forEach(el => formData.append('environments[]', el.value));
                document.querySelectorAll('input[name="facility"]:checked').forEach(el => formData.append('facilities[]', el.value));

                const coverInput = document.getElementById('c-cover-upload');
                if (coverInput && coverInput.files.length > 0) formData.append('cover_photo', coverInput.files[0]);

                const galleryInput = document.getElementById('c-gallery-upload');
                if (galleryInput) {
                    for (let i = 0; i < galleryInput.files.length; i++) {
                        formData.append('gallery_photos[]', galleryInput.files[i]);
                    }
                }

                // ... (codul unde strangi datele in formData ramane la fel) ...

// CITIM ANCORA
const editCampsiteId = document.getElementById('edit-campsite-id').value;

// Decidem ruta: daca avem ID in ancora, facem Update. Daca nu, facem Submit.
const apiUrl = editCampsiteId ? '/cat/public/api/update_campsite.php' : '/cat/public/api/submit_campsite.php';

// Daca facem editare, trebuie sa trimitem si ID-ul catre PHP
if (editCampsiteId) {
    formData.append('campsite_id', editCampsiteId);
}

const token = localStorage.getItem('cat_token');

fetch(apiUrl, {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${token}` },
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        alert(editCampsiteId ? "Locația a fost actualizată!" : "Success! Locația de camping a fost trimisă spre aprobare.");

        const form = document.getElementById('campsite-form'); // Daca folosesti <form>
        if (form) form.reset();

        // Curatam ancora pentru urmatoarea data
        document.getElementById('edit-campsite-id').value = '';

        document.getElementById('create-campsite-tab').style.display = 'none';
        document.getElementById('dashboard-tab').style.display = 'block';
        loadMyCampsites();
    } else {
        alert("Eroare la salvare: " + data.message);
    }
})

                .catch(error => {
                    console.error('Eroare Fetch:', error);
                    alert("A apărut o problemă de conexiune cu serverul.");
                });
            }

            // D. Navigare BULINE (Inapoi in formular)
            const stepItem = e.target.closest('.campsite-stepper .step-item');
            if (stepItem) {
                const targetStepId = parseInt(stepItem.getAttribute('data-c-step'));
                const activeStep = document.querySelector('.campsite-step[style*="block"]');
                if (activeStep) {
                    let currentStepId = parseInt(activeStep.id.replace('c-step-', ''));
                    if (targetStepId < currentStepId) {
                        document.querySelectorAll('.campsite-step').forEach(s => s.style.display = 'none');
                        document.getElementById('c-step-' + targetStepId).style.display = 'block';
                        updateCampsiteStepper(targetStepId);
                    }
                }
            }

            // E. Butonul Edit din pagina de detalii
if (e.target.id === 'btn-edit-campsite') {
    if (!currentCampsiteData) return;

    //Schimbam view-ul: ascundem detaliile, aratam formularul
    document.getElementById('campsite-details-view').style.display = 'none';
    document.getElementById('create-campsite-tab').style.display = 'block';

    // Resetam pasii formularului la Pasul 1
    resetCampsiteForm();

    //Setam ANCORA cu ID-ul locatiei
    document.getElementById('edit-campsite-id').value = currentCampsiteData.id;

    //Populam input-urile cu datele existente
    document.getElementById('c-name').value = currentCampsiteData.name || '';
    document.getElementById('c-full-desc').value = currentCampsiteData.description || '';
    document.getElementById('c-lat').value = currentCampsiteData.latitude || '';
    document.getElementById('c-lng').value = currentCampsiteData.longitude || '';
    document.getElementById('campsite-price').value = currentCampsiteData.price_per_night || '';
    document.getElementById('campsite-capacity').value = currentCampsiteData.capacity || '';
    document.getElementById('c-city').value = currentCampsiteData.region || '';

    /* Atenție la o problemă de logică:
       În PHP, tu ai salvat strada, numărul și codul poștal ca un singur string (ex: "Strada X nr. 10, Oras, Zip").
       Din acest motiv, nu le putem separa perfect înapoi în cele 4 input-uri.
       Recomandare pe viitor: salvează strada, numărul și codul poștal în coloane separate în baza de date!
       Momentan, vom pune toată adresa în 'c-street' ca să nu se piardă.
    */
    document.getElementById('c-street').value = currentCampsiteData.address || '';
    document.getElementById('c-number').value = '-';
    document.getElementById('c-zip').value = '-';

    //Bifam Checkbox-urile pentru Mediu si Facilitati
    document.querySelectorAll('input[name="environment"]').forEach(cb => {
        cb.checked = currentCampsiteData.environments.includes(cb.value);
    });

    document.querySelectorAll('input[name="facility"]').forEach(cb => {
        cb.checked = currentCampsiteData.facilities.includes(cb.value);
    });
}

// Toggle Publish
if (e.target.id === 'btn-publish') {
    updatePublishStatus(true);
}

// Toggle Unpublish
if (e.target.id === 'btn-unpublish') {
    updatePublishStatus(false);
}


// F. Butonul "Messages" din pagina de detalii
if (e.target.id === 'btn-messages') {
    if (!currentCampsiteData) return;

    //Schimbam view-ul
    document.getElementById('campsite-details-view').style.display = 'none';
    document.getElementById('campsite-messages-view').style.display = 'block';

    //Extragem mesajul din datele locatiei curente
    const container = document.getElementById('messages-container');
    const feedbackText = currentCampsiteData.admin_feedback;

    //Verificam daca avem text si nu e doar null sau gol
    if (feedbackText && feedbackText.trim() !== '') {
        // Afisam cartonasul (momentan e unul singur in DB, dar designul permite mai multe)
        container.innerHTML = `
            <div class="message-card">
                <span class="message-sender-info">Echipa de Aprobare CaT</span>
                <p class="message-body">${feedbackText}</p>
            </div>
        `;
    } else {
        // Nu exista mesaje
        container.innerHTML = `
            <p class="no-messages-text">Nu ai niciun mesaj nou pentru această locație.</p>
        `;
    }
}

// G. Butonul de intoarcere de la mesaje la detalii
if (e.target.id === 'btn-back-from-messages' || e.target.closest('#btn-back-from-messages')) {
    document.getElementById('campsite-messages-view').style.display = 'none';
    document.getElementById('campsite-details-view').style.display = 'block';
}

// H. Butonul Delete din pagina de detalii
if (e.target.id === 'btn-delete-campsite') {
    if (!currentCampsiteData) return;

    // Confirmare clasica de la browser
    const isSure = confirm("Ești absolut sigur că vrei să ștergi locația '" + currentCampsiteData.name + "'? Acțiunea este ireversibilă și va șterge toate pozele și datele asociate.");

    if (isSure) {
        const token = localStorage.getItem('cat_token');

        fetch('/cat/public/api/delete_campsite.php', {
            method: 'POST', // Chiar daca logic e un DELETE, POST cu JSON e mai stabil in PHP standard
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                campsite_id: currentCampsiteData.id
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Locația a fost ștearsă.");

                // Ne intoarcem la dashboard si reincarcam grila de carduri
                document.getElementById('campsite-details-view').style.display = 'none';
                document.getElementById('dashboard-tab').style.display = 'block';
                currentCampsiteData = null; // Curatam variabila
                loadMyCampsites(); // Reincarcam lista de locatii actualizata
            } else {
                alert("Eroare la ștergere: " + data.message);
            }
        })
        .catch(err => {
            console.error("Eroare Fetch:", err);
            alert("A apărut o problemă de conexiune la ștergere.");
        });
    }
}
        });

        let currentCampsiteData = null;
        //Functia noastra de deschidere a detaliilor
        function openCampsiteDetails(id) {
            const token = localStorage.getItem('cat_token');

            fetch(`/cat/public/api/get_campsite_details.php?id=${id}`, {
                method: 'GET',
                headers: { 'Authorization': `Bearer ${token}` }
            })
            .then(res => res.json())
            .then(res => {
                if (!res.success) {
                    alert(res.message);
                    return;
                }

                const data = res.data;
                currentCampsiteData = data;

                const btnPublish = document.getElementById('btn-publish');
const btnUnpublish = document.getElementById('btn-unpublish');

// Setam corect clasa 'active' in functie de DB
if (data.is_published === true || data.is_published === 'true' || data.is_published === 1) {
    btnPublish.classList.add('active');
    btnUnpublish.classList.remove('active');
} else {
    btnUnpublish.classList.add('active');
    btnPublish.classList.remove('active');
}

                document.getElementById('dashboard-tab').style.display = 'none';
                document.getElementById('campsite-details-view').style.display = 'block';

                document.getElementById('detail-title').textContent = data.name;
                document.getElementById('detail-address').textContent = `${data.address || ''}, ${data.region || ''}`;
                document.getElementById('detail-description').textContent = data.description || 'Fără descriere disponibilă.';
                document.getElementById('detail-type').textContent = data.type;
                document.getElementById('detail-price').textContent = data.price_per_night ? `${data.price_per_night} RON` : 'Nespecificat';
                document.getElementById('detail-capacity').textContent = data.capacity ? `${data.capacity} pers.` : 'Nespecificat';

                const badge = document.getElementById('detail-status-badge');
                const feedbackAlert = document.getElementById('admin-feedback-alert');

                if (data.approval_status == 1) {
                    badge.textContent = 'Aprobat';
                    badge.className = 'status-badge badge-approved';
                    feedbackAlert.style.display = 'none';
                } else if (data.approval_status == 2) {
                    badge.textContent = 'Respins';
                    badge.className = 'status-badge badge-rejected';
                    feedbackAlert.style.display = 'block';
                    document.getElementById('feedback-text').textContent = data.admin_feedback || 'Niciun motiv specificat.';
                } else {
                    badge.textContent = 'În așteptare';
                    badge.className = 'status-badge badge-pending';
                    feedbackAlert.style.display = 'none';
                }

                const facContainer = document.getElementById('detail-facilities');
                facContainer.innerHTML = data.facilities.length
                    ? data.facilities.map(f => `<span class="tag tag-facility">${f}</span>`).join('')
                    : '<span class="text-muted">Nicio facilitate bifată</span>';

                const envContainer = document.getElementById('detail-environments');
                envContainer.innerHTML = data.environments.length
                    ? data.environments.map(e => `<span class="tag tag-environment">${e}</span>`).join('')
                    : '<span class="text-muted">Niciun mediu bifat</span>';

                const heroImg = document.getElementById('detail-hero-img');
                const thumbContainer = document.getElementById('detail-thumbnails');

                if (data.media && data.media.length > 0) {
                    heroImg.src = `/cat/public/${data.media[0].url}`;
                    thumbContainer.innerHTML = data.media.map(m => `
                        <img src="/cat/public/${m.url}" class="thumb-img" onclick="document.getElementById('detail-hero-img').src=this.src" alt="Thumbnail">
                    `).join('');
                } else {
                    heroImg.src = '../../assets/images/camping-placeholder.jpg';
                    thumbContainer.innerHTML = '';
                }
            })
            .catch(err => console.error("Eroare la preluare detalii locație:", err));
        }


        function updatePublishStatus(publishTarget) {
    if (!currentCampsiteData) return;

    const token = localStorage.getItem('cat_token');

    fetch('/cat/public/api/toggle_publish.php', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            campsite_id: currentCampsiteData.id,
            is_published: publishTarget
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Daca a mers, mutam vizual clasa 'active' pe butonul corect
            const btnPublish = document.getElementById('btn-publish');
            const btnUnpublish = document.getElementById('btn-unpublish');

            if (data.is_published) {
                btnPublish.classList.add('active');
                btnUnpublish.classList.remove('active');
            } else {
                btnUnpublish.classList.add('active');
                btnPublish.classList.remove('active');
            }

            // Salvam si in variabila globala ca sa nu fie nevoie de refresh
            currentCampsiteData.is_published = data.is_published;
        } else {
            alert("Eroare la modificare: " + data.message);
        }
    })
    .catch(err => {
        console.error("Eroare Fetch:", err);
        alert("A apărut o problemă de conexiune cu serverul.");
    });
}


        // Buton back details
        document.getElementById('btn-back-to-grid').addEventListener('click', function() {
            document.getElementById('campsite-details-view').style.display = 'none';
            document.getElementById('dashboard-tab').style.display = 'block';
        });

        //Functii Ajutatoare Formular & Coleg
        function openCreateCampsiteForm() {
    document.getElementById('dashboard-tab').style.display = 'none';
    document.getElementById('create-campsite-tab').style.display = 'block';

    // GOLIM ANCORA si formularul
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
            if(!stepElement) return false;
            let isValid = true;
            stepElement.querySelectorAll('input[type="text"][required], input[type="number"][required], textarea[required], select[required]').forEach(input => {
                if (input.value.trim() === '') {
                    input.style.borderColor = 'red';
                    isValid = false;
                } else {
                    input.style.borderColor = '#ccc';
                }
            });
            stepElement.querySelectorAll('input[type="file"][required]').forEach(input => {
                const dashedBox = input.closest('.upload-dashed-box');
                if (input.files.length === 0) {
                    if (dashedBox) dashedBox.style.borderColor = 'red';
                    isValid = false;
                } else {
                    if (dashedBox) dashedBox.style.borderColor = '#ccc';
                }
            });
            stepElement.querySelectorAll('.req-group').forEach(group => {
                const checkboxes = group.querySelectorAll('input[type="checkbox"]');
                if (checkboxes.length > 0) {
                    if (group.querySelectorAll('input[type="checkbox"]:checked').length === 0) {
                        group.style.border = '1px solid red';
                        isValid = false;
                    } else {
                        group.style.border = 'none';
                    }
                } else {
                    const inputs = group.querySelectorAll('select, input[type="number"]');
                    let groupValid = true;
                    inputs.forEach(input => {
                        if (input.value.trim() === '') {
                            input.style.borderColor = 'red';
                            groupValid = false;
                        } else {
                            input.style.borderColor = '#ccc';
                        }
                    });
                    if (!groupValid) isValid = false;
                }
            });
            return isValid;
        }

        // Vizualizare upload poze coleg
        const cCoverUpload = document.getElementById('c-cover-upload');
        if (cCoverUpload) {
            cCoverUpload.addEventListener('change', function () {
                const count = this.files.length;
                const label = document.getElementById('cover-filename');
                if (label) label.textContent = count > 0 ? `${count} poză selectată` : '';
            });
        }

        const cGalleryUpload = document.getElementById('c-gallery-upload');
        if (cGalleryUpload) {
            cGalleryUpload.addEventListener('change', function () {
                const count = this.files.length;
                const label = document.getElementById('gallery-count');
                if (label) label.textContent = count > 0 ? `${count} poze selectate` : '';
            });
        }

        async function resubmitCamping(id) {
            const ok = confirm('Cererea va fi trimisa din nou spre aprobare.');
            if (!ok) return;
            try {
                const token = localStorage.getItem('cat_token');
                const res = await fetch(`/cat/public/api/campings/${id}/resubmit`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: '{}'
                });
                const data = await res.json();
                if (data.ok) {
                    alert('Cererea a fost retrimisa spre aprobare.');
                    loadMyCampsites();
                } else {
                    alert(data.error || 'Eroare la retrimitere.');
                }
            } catch (e) {
                alert('Eroare de retea.');
            }
        }

        function initMapLogic() {
            const btnPinMap = document.getElementById('btn-pin-map');
            const mapModal = document.getElementById('map-modal');
            const closeMapBtn = document.getElementById('close-map-modal');
            const confirmLocationBtn = document.getElementById('confirm-location-btn');
            const coordsDisplay = document.getElementById('selected-coords');
            const inputLat = document.getElementById('c-lat');
            const inputLng = document.getElementById('c-lng');
            let pickerMap = null, pickerMarker = null, tempLat = null, tempLng = null;

            if (btnPinMap && mapModal) {
                btnPinMap.addEventListener('click', () => {
                    mapModal.style.display = 'flex';
                    if (!pickerMap) {
                        pickerMap = L.map('picker-map').setView([45.9432, 24.9668], 6);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(pickerMap);
                        pickerMap.on('click', function (e) {
                            tempLat = e.latlng.lat.toFixed(6);
                            tempLng = e.latlng.lng.toFixed(6);
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
                            inputLat.value = tempLat; inputLng.value = tempLng;
                            mapModal.style.display = 'none';
                        } else alert("Te rog dă click pe hartă!");
                    });
                }
            }
        }
