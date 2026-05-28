// Logica si Validarea pentru Formularul CaT Partner

// functia de validare a unui pas
// functia de validare a unui pas
function validateStep(stepElement) {
    let isValid = true;

    const allInputs = stepElement.querySelectorAll('input');
    allInputs.forEach(input => input.classList.remove('input-error'));

    //Verificam text si email
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

    //Verificam fisierele
    const fileInputs = stepElement.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        if (input.files.length === 0) {
            const dropZone = input.closest('.upload-dashed-box');
            if (dropZone) {
                dropZone.classList.add('input-error');
                input.addEventListener('change', () => dropZone.classList.remove('input-error'), { once: true });
            }
            isValid = false;
        }
    });

    //Validare Specifica Pasul 2 (Business Type)
    if (stepElement.id === 'step-2') {
        const radioButtons = stepElement.querySelectorAll('input[name="business_type"]');
        let isSelected = false;

        radioButtons.forEach(radio => {
            if (radio.checked) isSelected = true;
        });

        if (!isSelected) {
            // Cautam containerul mare al dropdown-ului, nu doar butonul
            const businessSelector = stepElement.querySelector('.business-type-selector');
            if (businessSelector) {
                // Ii adaugam clasa de eroare containerului (va face tot chenarul rosu)
                businessSelector.classList.add('input-error');

                // Scoatem imediat marginea rosie cand utilizatorul da click sa aleaga o optiune
                businessSelector.addEventListener('click', () => {
                    businessSelector.classList.remove('input-error');
                }, { once: true });
            }
            isValid = false;
        }
    }

    //Validare Specifica Pasul 3 (Termeni si Conditii)
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

//butoanele next (trecerea intre pasi)
document.addEventListener('click', function (e) {
    if (e.target && e.target.classList.contains('btn-next')) {
        e.preventDefault();

        // Gasim pasul curent in care ne aflam
        const currentStep = e.target.closest('.partner-step');

        // Daca NU trece validarea, ne oprim aici
        if (!validateStep(currentStep)) {
            return;
        }

        // Daca trece validarea, mergem la pasul urmator
        const nextStepId = e.target.getAttribute('data-next');
        const allSteps = document.querySelectorAll('.partner-step');
        allSteps.forEach(step => step.style.display = 'none');

        const targetStep = document.getElementById('step-' + nextStepId);
        if (targetStep) targetStep.style.display = 'block';

        const stepItems = document.querySelectorAll('.step-item');
        stepItems.forEach(item => {
            const itemStep = parseInt(item.getAttribute('data-step'));
            if (itemStep <= parseInt(nextStepId)) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    }
});

//butonul finish (trimiterea finala catre server)
document.addEventListener('click', async function (e) {
    if (e.target && e.target.classList.contains('btn-submit')) {
        e.preventDefault();

        const currentStep = document.getElementById('step-3');

        //Validam ultimul pas (sa aiba bifele puse si campurile pline)
        if (!validateStep(currentStep)) {
            return;
        }

        //Schimbam starea butonului ca utilizatorul sa astepte
        const submitBtn = e.target;
        const originalText = submitBtn.innerText;
        submitBtn.innerText = 'Sending...';
        submitBtn.disabled = true;

        try {
            //Cream "coletul" care poate tine si texte, si poze
            const formData = new FormData();

            // Extragem datele userului logat si adaugam user_id-ul in colet
            const catUser = JSON.parse(localStorage.getItem('cat_user') || '{}');
            formData.append('user_id', catUser.id || 1); // 1 este fallback de siguranta


            // Adunam datele de la PASUL 1
            formData.append('last_name', document.getElementById('partner-last-name').value);
            formData.append('first_name', document.getElementById('partner-first-name').value);
            formData.append('id_document', document.getElementById('partner-id-upload').files[0]);

            // Adunam datele de la PASUL 2
            const businessType = document.querySelector('input[name="business_type"]:checked').value;
            formData.append('business_type', businessType);
            formData.append('company_name', document.getElementById('partner-company-name').value);
            formData.append('registration_number', document.getElementById('partner-reg-number').value);
            formData.append('address_street', document.getElementById('partner-street').value);
            formData.append('address_number', document.getElementById('partner-street-number').value);
            formData.append('address_city', document.getElementById('partner-city').value);
            formData.append('address_zip', document.getElementById('partner-zip').value);
            formData.append('registration_document', document.getElementById('partner-reg-upload').files[0]);

            // Adunam datele de la PASUL 3
            formData.append('contact_phone', document.getElementById('partner-phone').value);
            formData.append('contact_email', document.getElementById('partner-email').value);

            //Expediem coletul catre calea exacta a serverului
            const token = localStorage.getItem('cat_token');
            const response = await fetch('/cat/public/api/submit_partner.php', {
                method: 'POST',
                headers: {
                    ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                },
                body: formData
            });
            // Citim raspunsul de la server (ex: { "success": true })
            const result = await response.json();

            //Daca totul e OK, mergem direct la noul Dashboard
            if (response.ok && result.success) {

                //Ascundem ABSOLUT TOATE sectiunile de tip tab (ca sa eliberam tot ecranul)
                const allTabs = document.querySelectorAll('.tab-section');
                allTabs.forEach(tab => {
                    tab.style.display = 'none';
                    tab.classList.remove('active-section'); // Curatam si clasa
                });

                //Afisam direct panoul de control (Dashboard-ul tau nou)
                const dashboardTab = document.getElementById('dashboard-tab');
                if (dashboardTab) {
                    dashboardTab.style.display = 'block';
                    dashboardTab.classList.add('active-section'); // Il facem pe el tab-ul activ
                }

            } else {
                // Daca backend-ul ne trimite o eroare logica
                alert("A apărut o eroare: " + (result.message || "Te rugăm să încerci din nou."));
            }

        } catch (error) {
            // Daca pica netul sau serverul e inchis
            console.error('Eroare la trimitere:', error);
            alert("A apărut o eroare de rețea. Te rugăm să verifici conexiunea!");
        } finally {
            // Indiferent ce se intampla, la final deblocam butonul
            submitBtn.innerText = originalText;
            submitBtn.disabled = false;
        }
    }
});

document.addEventListener('click', function (e) {
    if (e.target && e.target.classList.contains('dropdown-trigger')) {
        e.preventDefault();
        const content = e.target.nextElementSibling;
        if (content && content.classList.contains('dropdown-content')) {
            content.classList.toggle('show');
        }
    }
});

//navigarea prin stepper (doar pentru a te intoarce)
document.addEventListener('click', function (e) {
    const stepItem = e.target.closest('.step-item');
    if (stepItem) {
        const targetStepId = parseInt(stepItem.getAttribute('data-step'));

        // Aflam in ce pas ne aflam acum
        let currentStepId = 1;
        const allSteps = document.querySelectorAll('.partner-step');
        allSteps.forEach(step => {
            // Daca formularul este vizibil pe ecran
            if (step.style.display === 'block' || step.style.display === 'flex') {
                currentStepId = parseInt(step.id.replace('step-', ''));
            }
        });
        // Permitem navigarea doar daca vrea sa mearga INAPOI (la un pas completat deja)
        if (targetStepId < currentStepId) {
            allSteps.forEach(step => step.style.display = 'none');
            const targetStep = document.getElementById('step-' + targetStepId);
            if (targetStep) {
                targetStep.style.display = 'block';
            }
        }
    }
});

// verificare status partener la incarcarea paginii
document.addEventListener('DOMContentLoaded', async () => {
    const catUser = JSON.parse(localStorage.getItem('cat_user') || '{}');

    if (catUser.id) {
        try {
            const response = await fetch(`/cat/public/api/check_partner.php?user_id=${catUser.id}`);
            const data = await response.json();

            if (data.success && data.has_applied) {
                const sidebarBtn = document.querySelector('.nav-item[data-tab="partner"]');

                if (sidebarBtn) {
                    // Schimbam DOAR destinatia, ca sa deschida Dashboard-ul
                    sidebarBtn.setAttribute('data-tab', 'dashboard-tab');

                    // AM STERS linia care modifica innerHTML-ul.
                    // Astfel, butonul ramane "CaT Partner", cu sageata la locul ei si centrat perfect!
                }
            }
        } catch (error) {
            console.error("Eroare la verificarea statusului de partener:", error);
        }
    }
});