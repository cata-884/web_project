document.addEventListener('DOMContentLoaded', () => {

    // ==========================================
    // 1. LOGICA PENTRU SLIDER-UL DE CARDURI (Plante)
    // ==========================================
    const cards = document.querySelectorAll('.about-cards-stack .feature-card');
    const sliderButtons = document.querySelectorAll('.about-cards-stack .card-btn');
    let positions = ['pos-0', 'pos-1', 'pos-2'];

    // Funcționează doar dacă găsește cardurile pe pagină
    if (sliderButtons.length > 0) {
        sliderButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Curățăm clasele vechi
                cards.forEach(card => card.classList.remove('pos-0', 'pos-1', 'pos-2'));
                // Rotim pozițiile
                positions.unshift(positions.pop());
                // Le adăugăm la loc
                cards.forEach((card, index) => card.classList.add(positions[index]));
            });
        });
    }

    // ==========================================
    // 2. LOGICA PENTRU TAB-URILE ABOUT US (Băieții)
    // ==========================================
    const btnLuciu = document.getElementById('btn-luciu');
    const btnCatalin = document.getElementById('btn-catalin');
    const contentLuciu = document.getElementById('content-luciu');
    const contentCatalin = document.getElementById('content-catalin');

    // Dacă găsește butoanele în HTML, activează logica
    if (btnLuciu && btnCatalin && contentLuciu && contentCatalin) {

        btnCatalin.addEventListener('click', () => {
            // Butoane
            btnCatalin.classList.add('active');
            btnLuciu.classList.remove('active');
            // Conținut
            contentCatalin.classList.add('active-content');
            contentLuciu.classList.remove('active-content');
        });

        btnLuciu.addEventListener('click', () => {
            // Butoane
            btnLuciu.classList.add('active');
            btnCatalin.classList.remove('active');
            // Conținut
            contentLuciu.classList.add('active-content');
            contentCatalin.classList.remove('active-content');
        });
    } else {
        console.log("Sunt pe o pagină unde nu există tab-urile About Us (sau lipsesc ID-urile).");
    }

});