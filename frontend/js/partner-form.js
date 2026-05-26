// =================================================================
// Logica și Validarea pentru Formularul CaT Partner
// =================================================================

// --- FUNCȚIA DE VALIDARE A UNUI PAS ---
// --- FUNCȚIA DE VALIDARE A UNUI PAS ---
function validateStep(stepElement) {
    let isValid = true;

    const allInputs = stepElement.querySelectorAll('input');
    allInputs.forEach(input => input.classList.remove('input-error'));

    // 1. Verificăm text și email
    const textInputs = stepElement.querySelectorAll('input[type="text"], input[type="email"]');
    textInputs.forEach(input => {
        if (input.value.trim() === '') {
            input.classList.add('input-error');
            isValid = false;
        }
        if (input.type === 'email' && input.value.trim() !== '') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value)) {
                input.classList.add('input-error');
                isValid = false;
            }
        }
    });

    // 2. Verificăm fișierele
    const fileInputs = stepElement.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        if (input.files.length === 0) {
            const dropZone = input.closest('.upload-dashed-box');
            if(dropZone) {
                dropZone.classList.add('input-error');
                input.addEventListener('change', () => dropZone.classList.remove('input-error'), {once: true});
            }
            isValid = false;
        }
    });

    // 3. Validare Specifică Pasul 2 (Business Type)
    if (stepElement.id === 'step-2') {
        const radioButtons = stepElement.querySelectorAll('input[name="business_type"]');
        let isSelected = false;

        radioButtons.forEach(radio => {
            if (radio.checked) isSelected = true;
        });

        if (!isSelected) {
            // Căutăm containerul mare al dropdown-ului, nu doar butonul
            const businessSelector = stepElement.querySelector('.business-type-selector');
            if (businessSelector) {
                // Îi adăugăm clasa de eroare containerului (va face tot chenarul roșu)
                businessSelector.classList.add('input-error');

                // Scoatem imediat marginea roșie când utilizatorul dă click să aleagă o opțiune
                businessSelector.addEventListener('click', () => {
                    businessSelector.classList.remove('input-error');
                }, {once: true});
            }
            isValid = false;
        }
    }

    // 4. Validare Specifică Pasul 3 (Termeni și Condiții)
    if (stepElement.id === 'step-3') {
        const checkTerms = document.getElementById('agree-terms');
        const checkTruth = document.getElementById('declare-truth');

        if (!checkTerms.checked || !checkTruth.checked) {
            alert("Please accept the terms and declare the accuracy of the information to proceed.");
            isValid = false;
        }
    }

    return isValid;
}

// --- 1. BUTOANELE NEXT (Trecerea între pași) ---
document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('btn-next')) {
        e.preventDefault();

        // Găsim pasul curent în care ne aflăm
        const currentStep = e.target.closest('.partner-step');

        // Dacă NU trece validarea, ne oprim aici
        if (!validateStep(currentStep)) {
            return;
        }

        // Dacă trece validarea, mergem la pasul următor
        const nextStepId = e.target.getAttribute('data-next');
        const allSteps = document.querySelectorAll('.partner-step');
        allSteps.forEach(step => step.style.display = 'none');

        const targetStep = document.getElementById('step-' + nextStepId);
        if (targetStep) targetStep.style.display = 'block';

        const stepItems = document.querySelectorAll('.step-item');
        stepItems.forEach(item => {
            const itemStep = parseInt(item.getAttribute('data-step'));
            if(itemStep <= parseInt(nextStepId)) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    }
});

// --- 2. BUTONUL FINISH (Trimiterea finală către Server) ---
document.addEventListener('click', async function(e) {
    if (e.target && e.target.classList.contains('btn-submit')) {
        e.preventDefault();

        const currentStep = document.getElementById('step-3');

        // 1. Validăm ultimul pas (să aibă bifele puse și câmpurile pline)
        if (!validateStep(currentStep)) {
            return;
        }

        // 2. Schimbăm starea butonului ca utilizatorul să aștepte
        const submitBtn = e.target;
        const originalText = submitBtn.innerText;
        submitBtn.innerText = 'Sending...';
        submitBtn.disabled = true;

        try {
            // 3. Creăm "coletul" care poate ține și texte, și poze
           const formData = new FormData();

            // Extragem datele userului logat și adăugăm user_id-ul în colet
            const catUser = JSON.parse(localStorage.getItem('cat_user') || '{}');
            formData.append('user_id', catUser.id || 1); // 1 este fallback de siguranță


            // Adunăm datele de la PASUL 1
            formData.append('last_name', document.getElementById('partner-last-name').value);
            formData.append('first_name', document.getElementById('partner-first-name').value);
            formData.append('id_document', document.getElementById('partner-id-upload').files[0]);

            // Adunăm datele de la PASUL 2
            const businessType = document.querySelector('input[name="business_type"]:checked').value;
            formData.append('business_type', businessType);
            formData.append('company_name', document.getElementById('partner-company-name').value);
            formData.append('registration_number', document.getElementById('partner-reg-number').value);
            formData.append('address_street', document.getElementById('partner-street').value);
            formData.append('address_number', document.getElementById('partner-street-number').value);
            formData.append('address_city', document.getElementById('partner-city').value);
            formData.append('address_zip', document.getElementById('partner-zip').value);
            formData.append('registration_document', document.getElementById('partner-reg-upload').files[0]);

            // Adunăm datele de la PASUL 3
            formData.append('contact_phone', document.getElementById('partner-phone').value);
            formData.append('contact_email', document.getElementById('partner-email').value);

            // 4. Expediem coletul către calea exactă a serverului
            const token = localStorage.getItem('cat_token');
           const response = await fetch('/cat/public/api/submit_partner.php', {
    method: 'POST',
    headers: {
        ...(token ? { 'Authorization': `Bearer ${token}` } : {})
    },
    body: formData
});
            // Citim răspunsul de la server (ex: { "success": true })
            const result = await response.json();

         // 5. Dacă totul e OK, mergem direct la noul Dashboard
            if (response.ok && result.success) {

                // 1. Ascundem ABSOLUT TOATE secțiunile de tip tab (ca să eliberăm tot ecranul)
                const allTabs = document.querySelectorAll('.tab-section');
                allTabs.forEach(tab => {
                    tab.style.display = 'none';
                    tab.classList.remove('active-section'); // Curățăm și clasa
                });

                // 2. Afișăm direct panoul de control (Dashboard-ul tău nou)
                const dashboardTab = document.getElementById('dashboard-tab');
                if (dashboardTab) {
                    dashboardTab.style.display = 'block';
                    dashboardTab.classList.add('active-section'); // Îl facem pe el tab-ul activ
                }

            } else {
                // Dacă backend-ul ne trimite o eroare logică
                alert("A apărut o eroare: " + (result.message || "Te rugăm să încerci din nou."));
            }

       } catch (error) {
            // Dacă pică netul sau serverul e închis
            console.error('Eroare la trimitere:', error);
            alert("A apărut o eroare de rețea. Te rugăm să verifici conexiunea!");
        } finally {
            // Indiferent ce se întâmplă, la final deblocăm butonul
            submitBtn.innerText = originalText;
            submitBtn.disabled = false;
        }
    }
});

document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('dropdown-trigger')) {
        e.preventDefault();
        const content = e.target.nextElementSibling;
        if (content && content.classList.contains('dropdown-content')) {
            content.classList.toggle('show');
        }
    }
});

   // --- 4. Navigarea prin Stepper (doar pentru a te întoarce) ---
document.addEventListener('click', function(e) {
    const stepItem = e.target.closest('.step-item');
    if (stepItem) {
        const targetStepId = parseInt(stepItem.getAttribute('data-step'));

        // Aflăm în ce pas ne aflăm acum
        let currentStepId = 1;
        const allSteps = document.querySelectorAll('.partner-step');
        allSteps.forEach(step => {
            // Dacă formularul este vizibil pe ecran
            if (step.style.display === 'block' || step.style.display === 'flex') {
                currentStepId = parseInt(step.id.replace('step-', ''));
            }
        });
// Permitem navigarea doar dacă vrea să meargă ÎNAPOI (la un pas completat deja)
        if (targetStepId < currentStepId) {
            allSteps.forEach(step => step.style.display = 'none');
            const targetStep = document.getElementById('step-' + targetStepId);
            if (targetStep) {
                targetStep.style.display = 'block';
            }
        }
    }
});

// --- VERIFICARE STATUS PARTENER LA ÎNCĂRCAREA PAGINII ---
document.addEventListener('DOMContentLoaded', async () => {
    const catUser = JSON.parse(localStorage.getItem('cat_user') || '{}');

    if (catUser.id) {
        try {
            const response = await fetch(`/cat/public/api/check_partner.php?user_id=${catUser.id}`);
            const data = await response.json();

            if (data.success && data.has_applied) {
                const sidebarBtn = document.querySelector('.nav-item[data-tab="partner"]');

                if (sidebarBtn) {
                    // Schimbăm DOAR destinația, ca să deschidă Dashboard-ul
                    sidebarBtn.setAttribute('data-tab', 'dashboard-tab');

                    // AM ȘTERS linia care modifica innerHTML-ul.
                    // Astfel, butonul rămâne "CaT Partner", cu săgeata la locul ei și centrat perfect!
                }
            }
        } catch (error) {
            console.error("Eroare la verificarea statusului de partener:", error);
        }
    }
});