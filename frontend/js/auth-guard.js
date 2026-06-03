(function () {
    if (!localStorage.getItem('cat_token')) {
        const path = window.location.pathname;
        // Previne bucla pe paginile de auth/register
        if (!path.includes('auth.html') && !path.includes('register.html')) {
            // Calculeaza prefixul relativ dinamic catre pages/auth.html bazat pe adancimea URL-ului
            const parts = path.split('/').filter(Boolean);
            const frontendIdx = parts.indexOf('frontend');

            let prefix;
            if (frontendIdx !== -1) {
                const depth = parts.length - frontendIdx - 1;
                prefix = depth > 1 ? '../'.repeat(depth - 1) : '';
            } else {
                // Fallback safe in caz ca structura e atipica (de exemplu rulat direct sub index)
                prefix = '../';
            }

            window.location.href = `${prefix}pages/auth.html`;
        }
    }
})();
