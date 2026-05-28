// Functia care deschide formularul din Dashboard
function openCreateCampsiteForm() {
    //Ascundem Dashboard-ul
    document.getElementById('dashboard-tab').style.display = 'none';
    document.getElementById('dashboard-tab').classList.remove('active-section');

    //Afisam formularul nou
    const formTab = document.getElementById('create-campsite-tab');
    formTab.style.display = 'block';
    formTab.classList.add('active-section');

    // Resetam formularul la pasul 1 (in caz ca a mai fost deschis)
    resetCampsiteForm();
}

function resetCampsiteForm() {
    document.querySelectorAll('.campsite-step').forEach(s => s.style.display = 'none');
    document.getElementById('c-step-1').style.display = 'block';
    updateCampsiteStepper(1);
}

// Navigarea intre pasi
document.addEventListener('click', function(e) {
    // Butonul NEXT
    if (e.target.classList.contains('campsite-next')) {
        const nextStep = e.target.getAttribute('data-next');

        document.querySelectorAll('.campsite-step').forEach(s => s.style.display = 'none');
        document.getElementById('c-step-' + nextStep).style.display = 'block';

        updateCampsiteStepper(nextStep);
    }

    // Butonul FINISH
    if (e.target.classList.contains('campsite-finish')) {
        showToast("Locatia de camping a fost creata cu succes!", "success");
        // Aici poti adauga logica de salvare in BD si intoarcerea la Dashboard
        location.reload();
    }
});

function updateCampsiteStepper(stepNumber) {
    const items = document.querySelectorAll('.campsite-stepper .step-item');
    items.forEach(item => {
        const itemStep = item.getAttribute('data-c-step');
        if (itemStep <= stepNumber) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}