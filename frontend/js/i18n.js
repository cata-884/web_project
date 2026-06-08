(function () {
    const DEFAULT = 'ro';

    // Determine translations base path from this script's own URL
    const _scriptSrc = document.currentScript ? document.currentScript.src : '';
    const _base = _scriptSrc.substring(0, _scriptSrc.lastIndexOf('/js/')) + '/translations';

    let _cache = {};   // { ro: {...}, en: {...} }
    let _current = {}; // active translation object

    function getLang() {
        return localStorage.getItem('cat_lang') || DEFAULT;
    }

    function loadSync(lang) {
        if (_cache[lang]) return _cache[lang];
        try {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `${_base}/${lang}.json`, false); // synchronous
            xhr.send();
            if (xhr.status === 200) {
                _cache[lang] = JSON.parse(xhr.responseText);
            } else {
                _cache[lang] = {};
            }
        } catch (e) {
            _cache[lang] = {};
        }
        return _cache[lang];
    }

    function t(key) {
        const parts = key.split('.');
        let val = _current;
        for (const p of parts) val = val?.[p];
        if (val !== undefined) return val;
        // fallback to default lang
        let fb = _cache[DEFAULT] || {};
        for (const p of parts) fb = fb?.[p];
        return fb ?? key;
    }

    function applyAll() {
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const v = t(el.getAttribute('data-i18n'));
            if (v) el.textContent = v;
        });
        document.querySelectorAll('[data-i18n-html]').forEach(el => {
            const v = t(el.getAttribute('data-i18n-html'));
            if (v) el.innerHTML = v;
        });
        document.querySelectorAll('[data-i18n-ph]').forEach(el => {
            const v = t(el.getAttribute('data-i18n-ph'));
            if (v) el.placeholder = v;
        });
        document.querySelectorAll('[data-i18n-title]').forEach(el => {
            const v = t(el.getAttribute('data-i18n-title'));
            if (v) el.title = v;
        });
        const titleKey = document.body.getAttribute('data-page-title');
        if (titleKey) document.title = t(titleKey);
    }

    function updateToggles() {
        const lang = getLang();
        document.querySelectorAll('.lang-toggle-btn').forEach(btn => {
            btn.textContent = lang === 'ro' ? 'EN' : 'RO';
        });
    }

    function injectToggle() {
        document.querySelectorAll('.lang-toggle-container').forEach(container => {
            if (container.querySelector('.lang-toggle-btn')) return;
            const btn = document.createElement('button');
            btn.className = container.classList.contains('dark') ? 'lang-toggle-btn dark' : 'lang-toggle-btn';
            btn.type = 'button';
            container.appendChild(btn);
        });
        updateToggles();
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('lang-toggle-btn')) {
                setLang(getLang() === 'ro' ? 'en' : 'ro');
            }
        });
    }

    function setLang(lang) {
        localStorage.setItem('cat_lang', lang);
        _current = loadSync(lang);
        document.documentElement.lang = lang;
        applyAll();
        updateToggles();
    }

    // Load active language synchronously at script parse time so t() works immediately
    _current = loadSync(getLang());
    // Also preload default as fallback if needed
    if (getLang() !== DEFAULT) loadSync(DEFAULT);

    document.addEventListener('DOMContentLoaded', function () {
        document.documentElement.lang = getLang();
        injectToggle();
        applyAll();
    });

    window.i18n = { t };
    window.t = t;
})();
