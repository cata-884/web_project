document.addEventListener('DOMContentLoaded', () => {

    // --- Aici facem schimbarea cartonaselor ---
    const cards = document.querySelectorAll('.about-cards-stack .feature-card');
    const sliderButtons = document.querySelectorAll('.about-cards-stack .card-btn');
    let positions = ['pos-0', 'pos-1', 'pos-2'];

    if (sliderButtons.length > 0) {
        sliderButtons.forEach(button => {
            button.addEventListener('click', () => {
                cards.forEach(card => card.classList.remove('pos-0', 'pos-1', 'pos-2'));
                positions.unshift(positions.pop());
                cards.forEach((card, index) => card.classList.add(positions[index]));
            });
        });
    }

    // --- Aici facem schimbarea tab-urilor in About Us ---
    const btnLuciu = document.getElementById('btn-luciu');
    const btnCatalin = document.getElementById('btn-catalin');
    const contentLuciu = document.getElementById('content-luciu');
    const contentCatalin = document.getElementById('content-catalin');

    if (btnLuciu && btnCatalin && contentLuciu && contentCatalin) {
        btnCatalin.addEventListener('click', () => {
            btnCatalin.classList.add('active');
            btnLuciu.classList.remove('active');
            contentCatalin.classList.add('active-content');
            contentLuciu.classList.remove('active-content');
        });

        btnLuciu.addEventListener('click', () => {
            btnLuciu.classList.add('active');
            btnCatalin.classList.remove('active');
            contentLuciu.classList.add('active-content');
            contentCatalin.classList.remove('active-content');
        });
    }

    // --- Aici facem schimbarea temei (Toggle Dark/White) ---
    const themeButtons = document.querySelectorAll('.theme-toggle .toggle-btn');

    if (themeButtons.length > 0) {
        themeButtons.forEach(button => {
            button.addEventListener('click', () => {
                themeButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
            });
        });
    }

});