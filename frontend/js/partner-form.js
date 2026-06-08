// Trimite un document (PDF/imagine) si returneaza URL-ul salvat pe server
async function uploadPartnerDocument(file) {
    const token = localStorage.getItem('cat_token');
    const fd    = new FormData();
    fd.append('file', file);
    const res = await fetch(API_BASE + '/api/organizers/documents', {
        method:  'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body:    fd,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || 'Eroare la încărcarea documentului');
    return data.url;
}

function validateStep(stepElement) {
    let isValid = true;

    const allInputs = stepElement.querySelectorAll('input');
    allInputs.forEach(input => input.classList.remove('input-error'));

    stepElement.querySelectorAll('input[type="text"], input[type="email"]').forEach(input => {
        if (input.value.trim() === '') { input.classList.add('input-error'); isValid = false; }
        if (input.type === 'email' && input.value.trim() !== '') {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) {
                input.classList.add('input-error'); isValid = false;
            }
        }
    });

    stepElement.querySelectorAll('input[type="file"]').forEach(input => {
        if (input.files.length === 0) {
            const dropZone = input.closest('.upload-dashed-box');
            if (dropZone) {
                dropZone.classList.add('input-error');
                input.addEventListener('change', () => dropZone.classList.remove('input-error'), { once: true });
            }
            isValid = false;
        }
    });

    if (stepElement.id === 'step-2') {
        const radioButtons = stepElement.querySelectorAll('input[name="business_type"]');
        const isSelected   = [...radioButtons].some(r => r.checked);
        if (!isSelected) {
            const businessSelector = stepElement.querySelector('.business-type-selector');
            if (businessSelector) {
                businessSelector.classList.add('input-error');
                businessSelector.addEventListener('click', () => businessSelector.classList.remove('input-error'), { once: true });
            }
            isValid = false;
        }
    }

    if (stepElement.id === 'step-3') {
        const checkTerms = document.getElementById('agree-terms');
        const checkTruth = document.getElementById('declare-truth');
        if (!checkTerms.checked || !checkTruth.checked) {
            showToast('Te rugăm să accepți termenii și să declari acuratețea informațiilor.', 'warning');
            isValid = false;
        }
    }

    return isValid;
}

// Navigare NEXT între pași
document.addEventListener('click', function (e) {
    if (!e.target?.classList.contains('btn-next')) return;
    e.preventDefault();

    const currentStep = e.target.closest('.partner-step');
    if (!validateStep(currentStep)) return;

    const nextStepId = e.target.getAttribute('data-next');
    document.querySelectorAll('.partner-step').forEach(s => s.style.display = 'none');
    const target = document.getElementById('step-' + nextStepId);
    if (target) target.style.display = 'block';

    document.querySelectorAll('.step-item').forEach(item => {
        item.classList.toggle('active', parseInt(item.getAttribute('data-step')) <= parseInt(nextStepId));
    });
});

// Trimitere finală
document.addEventListener('click', async function (e) {
    if (!e.target?.classList.contains('btn-submit')) return;
    e.preventDefault();

    const currentStep = document.getElementById('step-3');
    if (!validateStep(currentStep)) return;

    const submitBtn  = e.target;
    const origText   = submitBtn.innerText;
    submitBtn.innerText = 'Se trimite...';
    submitBtn.disabled  = true;

    try {
        // 1. Încarcă documentele și obține URL-urile
        const idFile  = document.getElementById('partner-id-upload').files[0];
        const regFile = document.getElementById('partner-reg-upload').files[0];

        const [idCardUrl, authorizationUrl] = await Promise.all([
            uploadPartnerDocument(idFile),
            uploadPartnerDocument(regFile),
        ]);

        // 2. Construieste legal_name din câmpurile formularului
        const firstName   = document.getElementById('partner-first-name').value.trim();
        const lastName    = document.getElementById('partner-last-name').value.trim();
        const companyName = document.getElementById('partner-company-name').value.trim();
        const legalName   = companyName || `${firstName} ${lastName}`.trim();

        // 3. Trimite cererea de parteneriat
        await api.post('/api/organizers/apply', {
            legal_name:        legalName,
            cui:               document.getElementById('partner-reg-number').value.trim() || null,
            id_card_url:       idCardUrl,
            authorization_url: authorizationUrl,
        });

        // 4. Afișează dashboard-ul
        document.querySelectorAll('.tab-section').forEach(tab => {
            tab.style.display = 'none';
            tab.classList.remove('active-section');
        });
        const dashboardTab = document.getElementById('dashboard-tab');
        if (dashboardTab) {
            dashboardTab.style.display = 'block';
            dashboardTab.classList.add('active-section');
        }

    } catch (err) {
        showToast(err.message || 'A apărut o eroare. Te rugăm să încerci din nou.', 'error');
    } finally {
        submitBtn.innerText = origText;
        submitBtn.disabled  = false;
    }
});

// Dropdown business type
document.addEventListener('click', function (e) {
    if (!e.target?.classList.contains('dropdown-trigger')) return;
    e.preventDefault();
    const content = e.target.nextElementSibling;
    if (content?.classList.contains('dropdown-content')) content.classList.toggle('show');
});

// Navigare stepper (înapoi)
document.addEventListener('click', function (e) {
    const stepItem = e.target.closest('.step-item');
    if (!stepItem) return;

    const targetId = parseInt(stepItem.getAttribute('data-step'));
    let currentId  = 1;
    document.querySelectorAll('.partner-step').forEach(step => {
        if (step.style.display === 'block' || step.style.display === 'flex')
            currentId = parseInt(step.id.replace('step-', ''));
    });

    if (targetId < currentId) {
        document.querySelectorAll('.partner-step').forEach(s => s.style.display = 'none');
        const target = document.getElementById('step-' + targetId);
        if (target) target.style.display = 'block';
    }
});

// Verificare status partener la încărcarea paginii
document.addEventListener('DOMContentLoaded', async () => {
    if (!localStorage.getItem('cat_token')) return;

    try {
        /** @type {{ application?: object }} */
        const data = await api.get('/api/organizers/my-application');
        if (data?.application) {
            const btn = document.querySelector('.nav-item[data-tab="partner"]');
            if (btn) btn.setAttribute('data-tab', 'dashboard-tab');
        }
    } catch (err) {
        if (err.response?.status !== 404)
            console.error('Eroare la verificarea statusului de partener:', err);
        // 404 = nicio cerere depusă = afișăm formularul
    }
});
