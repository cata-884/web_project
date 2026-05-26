// Funcția care deschide formularul din Dashboard
function openCreateCampsiteForm() {
    // 1. Ascundem Dashboard-ul
    document.getElementById('dashboard-tab').style.display = 'none';
    document.getElementById('dashboard-tab').classList.remove('active-section');

    // 2. Afișăm formularul nou
    const formTab = document.getElementById('create-campsite-tab');
    formTab.style.display = 'block';
    formTab.classList.add('active-section');

    // Resetăm formularul la pasul 1 (în caz că a mai fost deschis)
    resetCampsiteForm();
}

function resetCampsiteForm() {
    document.querySelectorAll('.campsite-step').forEach(s => s.style.display = 'none');
    document.getElementById('c-step-1').style.display = 'block';
    updateCampsiteStepper(1);
}

// Navigarea între pași
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
        alert("Success! Your new campsite has been created.");
        // Aici poți adăuga logica de salvare în BD și întoarcerea la Dashboard
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