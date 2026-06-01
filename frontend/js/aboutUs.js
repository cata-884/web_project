 document.getElementById('btn-luciu')?.addEventListener('click', () => {
            document.getElementById('content-luciu').classList.add('active-content');
            document.getElementById('content-catalin').classList.remove('active-content');
            document.getElementById('btn-luciu').classList.add('active');
            document.getElementById('btn-catalin').classList.remove('active');
        });
        document.getElementById('btn-catalin')?.addEventListener('click', () => {
            document.getElementById('content-catalin').classList.add('active-content');
            document.getElementById('content-luciu').classList.remove('active-content');
            document.getElementById('btn-catalin').classList.add('active');
            document.getElementById('btn-luciu').classList.remove('active');
        });