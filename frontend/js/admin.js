let adminCampingFilter = '';
let adminUserOffset = 0;
const ADMIN_USER_LIMIT = 20;
let pendingFeedbackId = null;
let pendingBanId = null;

const STATUS_META = {
    '-1': { label: 'Respins', cls: 'a-badge--1', card: 'st--1' },
    '0': { label: 'In Asteptare', cls: 'a-badge-0', card: 'st-0' },
    '1': { label: 'Aprobat', cls: 'a-badge-1', card: 'st-1' },
    '2': { label: 'Respins cu Feedback', cls: 'a-badge-2', card: 'st-2' },
};

function esc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
}

async function loadAdminCampings(status = '') {
    const grid = document.getElementById('admin-campings-grid');
    if (!grid) return;
    grid.innerHTML = '<p class="admin-loading">Se incarca...</p>';

    try {
        const qs = status !== '' ? `?status=${encodeURIComponent(status)}` : '';
// În admin.js, în funcția loadAdminMessages:
// În admin.js
const res = await api.get('/api/admin/messages');
        const campings = res?.campings ?? [];

        if (!campings.length) {
            grid.innerHTML = '<p class="admin-empty">Nicio cerere gasita pentru aceasta categorie.</p>';
            return;
        }
        grid.innerHTML = campings.map(renderCampingCard).join('');
    } catch (err) {
        grid.innerHTML = `<p class="admin-empty" style="color:var(--ar)">Eroare: ${esc(err.message)}</p>`;
    }
}

function renderCampingCard(c) {
    const st = STATUS_META[String(c.approval_status)] ?? STATUS_META['0'];
    const isPending = Number(c.approval_status) === 0;

    const docLinks = [];
    if (c.id_document_path) {
        docLinks.push(`<a class="admin-doc-link" href="${esc(c.id_document_path)}" download>↓ Document Identitate</a>`);
    }
    if (c.registration_document_path) {
        docLinks.push(`<a class="admin-doc-link" href="${esc(c.registration_document_path)}" download>↓ Certificat Inregistrare</a>`);
    }

    const feedbackBlock = (Number(c.approval_status) === 2 && c.admin_feedback)
        ? `<div class="admin-feedback-block"><strong>Feedback trimis:</strong> ${esc(c.admin_feedback)}</div>`
        : '';

    const actions = isPending ? `
        <div class="admin-card-actions">
            <button class="btn-aa" onclick="adminCampingAction(${c.id},'approve')">Aproba</button>
            <button class="btn-ar" onclick="adminCampingAction(${c.id},'reject')">Respinge</button>
            <button class="btn-af" onclick="openFeedbackModal(${c.id})">Respinge cu Feedback</button>
        </div>` : '';

    const addrParts = [c.address_street, c.address_number, c.address_city, c.address_zip].filter(Boolean);

    return `
    <div class="admin-camping-card ${st.card}">
        <div class="admin-card-header">
            <h3>${esc(c.name)}</h3>
            <span class="a-badge ${st.cls}">${st.label}</span>
        </div>
        <div class="admin-card-meta">
            <span>${esc(c.type)} &bull; ${esc(c.region)}</span>
            ${c.price_per_night ? `<span>${Number(c.price_per_night).toFixed(0)} RON/noapte</span>` : ''}
            <span>Trimis: ${fmtDate(c.created_at)}</span>
        </div>

        <div class="admin-info-row">
            <span class="admin-info-label">Utilizator</span>
            ${esc(c.full_name || c.username)} — <em>${esc(c.email)}</em>
        </div>
        ${c.company_name ? `
        <div class="admin-info-row">
            <span class="admin-info-label">Firma</span>
            ${esc(c.company_name)}
            ${c.business_type ? `(${esc(c.business_type)})` : ''}
            ${c.registration_number ? `&bull; CIF: ${esc(c.registration_number)}` : ''}
        </div>` : ''}
        ${addrParts.length ? `
        <div class="admin-info-row">
            <span class="admin-info-label">Adresa firma</span>
            ${esc(addrParts.join(', '))}
        </div>` : ''}
        ${(c.contact_phone || c.contact_email) ? `
        <div class="admin-info-row">
            <span class="admin-info-label">Contact</span>
            ${[c.contact_phone, c.contact_email].filter(Boolean).map(esc).join(' &bull; ')}
        </div>` : ''}

        ${docLinks.length ? `<div class="admin-docs">${docLinks.join('')}</div>` : ''}
        ${feedbackBlock}
        ${actions}
    </div>`;
}

async function adminCampingAction(id, action) {
    const labels = { approve: 'aprobat', reject: 'respins' };
    const ok = await showConfirm(`Confirmi ca vrei sa fie ${labels[action]} aceasta cerere?`, {
        title: action === 'approve' ? 'Aproba cererea' : 'Respinge cererea',
        confirmText: action === 'approve' ? 'Aproba' : 'Respinge',
        type: action === 'approve' ? 'success' : 'error',
    });
    if (!ok) return;
    try {
        await api.post(`/api/admin/campings/${id}/${action}`, {});
        loadAdminCampings(adminCampingFilter);
    } catch (err) {
        showToast('Eroare: ' + (err.message ?? 'Ceva a mers gresit'), 'error');
    }
}

function openFeedbackModal(campingId) {
    pendingFeedbackId = campingId;
    document.getElementById('admin-feedback-text').value = '';
    document.getElementById('admin-feedback-modal').style.display = 'flex';
}

async function loadAdminUsers(reset = false) {
    if (reset) adminUserOffset = 0;
    const grid = document.getElementById('admin-users-grid');
    if (!grid) return;
    if (reset) grid.innerHTML = '<p class="admin-loading">Se incarca...</p>';

    const search = document.getElementById('admin-user-search')?.value.trim() ?? '';
    const banned = document.getElementById('admin-user-filter')?.value ?? '';

    try {
        let qs = `?limit=${ADMIN_USER_LIMIT}&offset=${adminUserOffset}`;
        if (search) qs += `&search=${encodeURIComponent(search)}`;
        if (banned) qs += `&banned=${banned}`;

        const res = await api.get(`/api/admin/users${qs}`);
        const users = res?.users ?? [];
        const total = res?.total ?? 0;

        if (!users.length && reset) {
            grid.innerHTML = '<p class="admin-empty">Niciun utilizator gasit.</p>';
            return;
        }

        const html = users.map(renderUserCard).join('');
        if (reset) {
            grid.innerHTML = html;
        } else {
            grid.insertAdjacentHTML('beforeend', html);
        }

        document.getElementById('admin-users-more')?.remove();
        const loaded = adminUserOffset + users.length;
        if (loaded < total) {
            grid.insertAdjacentHTML('afterend', `
                <div class="admin-load-more" id="admin-users-more">
                    <span>${loaded} din ${total}</span>
                    <button onclick="adminUserOffset += ADMIN_USER_LIMIT; loadAdminUsers()">Incarca mai multi</button>
                </div>`);
        }
    } catch (err) {
        grid.innerHTML = `<p class="admin-empty" style="color:var(--ar)">Eroare: ${esc(err.message)}</p>`;
    }
}

function renderUserCard(u) {
    const initial = (u.username ?? '?')[0].toUpperCase();
    const roleClass = { user: 'a-role-user', organizer: 'a-role-organizer', admin: 'a-role-admin' }[u.role] ?? 'a-role-user';
    const roleLabel = { user: 'User', organizer: 'Organizer', admin: 'Admin' }[u.role] ?? u.role;
    const isBanned = u.is_banned === true || u.is_banned === 't' || u.is_banned === '1';

    const banBtn = (u.role !== 'admin')
        ? isBanned
            ? `<button class="btn-unban" onclick="adminUnbanUser(${u.id})">Ridica Ban</button>`
            : `<button class="btn-ban"   onclick="openBanModal(${u.id})">Baneaza</button>`
        : '';

    return `
    <div class="admin-user-card ${isBanned ? 'banned' : ''}">
        <div class="admin-user-avatar">
            ${u.avatar_url
            ? `<img src="${esc(u.avatar_url)}" alt="${esc(u.username)}" loading="lazy">`
            : initial}
        </div>
        <div class="admin-user-info">
            <h4>${esc(u.full_name || u.username)}</h4>
            <p>${esc(u.email)} &bull; @${esc(u.username)} &bull; ${fmtDate(u.created_at)}</p>
        </div>
        <span class="a-role ${roleClass}">${roleLabel}</span>
        <div class="admin-user-actions">${banBtn}</div>
    </div>`;
}

function openBanModal(userId) {
    pendingBanId = userId;
    document.getElementById('admin-ban-reason').value = '';
    document.getElementById('admin-ban-days').value = '';
    document.getElementById('admin-ban-modal').style.display = 'flex';
}

async function adminUnbanUser(userId) {
    const ok = await showConfirm('Ridici ban-ul acestui utilizator?', {
        title: 'Ridica ban', confirmText: 'Ridica ban', type: 'warning',
    });
    if (!ok) return;
    try {
        await api.post(`/api/admin/users/${userId}/unban`, {});
        showToast('Ban ridicat cu succes.', 'success');
        loadAdminUsers(true);
    } catch (err) {
        showToast('Eroare: ' + (err.message ?? 'Ceva a mers gresit'), 'error');
    }
}

async function loadAdminStats() {
    const container = document.getElementById('admin-stats-summary');
    if (!container) return;
    container.innerHTML = '<p class="admin-loading">Se incarca...</p>';

    try {
        const d = await api.get('/api/admin/stats/summary');
        if (!d) return;
        const bookings = d.bookings_by_status ?? {};
        const totalBookings = Object.values(bookings).reduce((a, b) => a + Number(b), 0);

        container.innerHTML = `
            <div class="stat-card">
                <div class="stat-val">${d.nr_users ?? 0}</div>
                <div class="stat-lbl">Utilizatori</div>
            </div>
            <div class="stat-card">
                <div class="stat-val">${d.nr_campings ?? 0}</div>
                <div class="stat-lbl">Campinguri Active</div>
            </div>
            <div class="stat-card">
                <div class="stat-val">${d.nr_pending ?? 0}</div>
                <div class="stat-lbl">Cereri in Asteptare</div>
            </div>
            <div class="stat-card">
                <div class="stat-val">${totalBookings}</div>
                <div class="stat-lbl">Rezervari Total</div>
            </div>
            <div class="stat-card stat-card--revenue">
                <div class="stat-val">${Number(d.total_revenue ?? 0).toLocaleString('ro-RO', { minimumFractionDigits: 0 })} RON</div>
                <div class="stat-lbl">Venituri Confirmate</div>
            </div>
            ${(d.top_regions ?? []).length ? `
            <div class="stat-card stat-card--wide">
                <div class="stat-lbl" style="margin-bottom:8px">Top Regiuni</div>
                ${d.top_regions.map(r => `
                    <div class="stat-region-row">
                        <span>${esc(r.region)}</span>
                        <span class="stat-region-cnt">${r.cnt}</span>
                    </div>`).join('')}
            </div>` : ''}
        `;
    } catch (err) {
        container.innerHTML = `<p class="admin-empty" style="color:var(--ar)">Eroare: ${esc(err.message)}</p>`;
        showToast('Eroare la incarcarea statisticilor.', 'error');
    }

    loadAdminChart();
}


async function loadAdminMessages() {
    const grid = document.getElementById('admin-messages-grid');
    if (!grid) {
        console.error("Eroare: Elementul #admin-messages-grid nu a fost găsit în pagină!");
        return;
    }
    grid.innerHTML = '<p>Se încarcă...</p>';

    try {
        // Punem calea directă către fișierul tău PHP
        const response = await fetch('/cat/public/api/admin/get_messages.php');
        const data = await response.json();

        if (data.success && data.messages.length > 0) {
            grid.innerHTML = data.messages.map(m => `
                <div class="admin-message-card">
                    <div class="msg-header">
                        <strong>${esc(m.name)}</strong>
                        <span>${esc(m.email)}</span>
                    </div>
                    <div class="msg-body">
                        <p>${esc(m.message)}</p>
                    </div>
                    <div class="msg-footer">
                        <span>${fmtDate(m.created_at)}</span>
                    </div>
                </div>
            `).join('');
        } else {
            grid.innerHTML = '<p>Nu există mesaje.</p>';
        }
    } catch (err) {
        grid.innerHTML = `<p>Eroare: ${err.message}</p>`;
        console.error(err);
    }
}

async function loadAdminChart() {
    const container = document.getElementById('admin-chart-container');
    if (!container) return;
    container.innerHTML = '<p class="admin-loading">Se incarca graficul...</p>';

    const type = document.getElementById('admin-chart-type')?.value ?? 'bookings_per_month';
    const token = localStorage.getItem('cat_token') ?? '';

    try {
        const res = await fetch(`/cat/public/api/admin/stats/chart.svg?type=${encodeURIComponent(type)}`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const svg = await res.text();
        container.innerHTML = svg;
    } catch (err) {
        container.innerHTML = `<p class="admin-empty" style="color:var(--ar)">Eroare grafic: ${esc(err.message)}</p>`;
    }
}

async function adminDownloadPdf() {
    const token = localStorage.getItem('cat_token') ?? '';
    try {
        const res = await fetch('/cat/public/api/admin/stats/report.pdf', {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        if (!res.ok) {
            const j = await res.json().catch(() => ({}));
            showToast('Eroare: ' + (j.error ?? `HTTP ${res.status}`), 'error');
            return;
        }
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `raport-cat-${new Date().toISOString().slice(0,10)}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
        showToast('Raport descarcat.', 'success');
    } catch (err) {
        showToast('Eroare: ' + err.message, 'error');
    }
}

document.addEventListener('DOMContentLoaded', () => {

    // Arata butonul admin in sidebar daca user e admin
    const catUser = JSON.parse(localStorage.getItem('cat_user') ?? '{}');
    if (catUser.role === 'admin') {
        const adminBtn = document.getElementById('admin-nav-btn');
        if (adminBtn) adminBtn.style.display = 'flex';
    }

    // Incarca datele cand tab-ul admin e deschis
    document.getElementById('admin-nav-btn')?.addEventListener('click', () => {
        // reseteaza la primul panel
        document.querySelectorAll('.admin-panel-tab').forEach((b, i) => b.classList.toggle('active', i === 0));
        document.getElementById('admin-panel-campings').style.display = 'block';
        document.getElementById('admin-panel-users').style.display = 'none';
        loadAdminCampings('');
    });

 // Sub-nav (Cereri Camping / Utilizatori / Statistici / Mesaje)
    document.querySelectorAll('.admin-panel-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            // 1. Resetăm active pe butoane
            document.querySelectorAll('.admin-panel-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // 2. Ascundem toate panelurile
            document.querySelectorAll('.admin-panel').forEach(p => p.style.display = 'none');

            // 3. Afișăm panelul corect
            const panel = document.getElementById('admin-panel-' + btn.dataset.panel);
            if (panel) panel.style.display = 'block';

            // 4. Apelăm funcția de încărcare corespunzătoare (O SINGURĂ DATĂ!)
            const target = btn.dataset.panel;
            if (target === 'users') loadAdminUsers(true);
            if (target === 'stats') loadAdminStats();
            if (target === 'messages') loadAdminMessages();
        });
    });

    // Filtre status campings
    document.querySelectorAll('.status-filter').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.status-filter').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            adminCampingFilter = btn.dataset.status ?? '';
            loadAdminCampings(adminCampingFilter);
        });
    });

    // Search useri (debounced)
    let searchTimer;
    document.getElementById('admin-user-search')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadAdminUsers(true), 340);
    });
    document.getElementById('admin-user-filter')?.addEventListener('change', () => loadAdminUsers(true));

    document.getElementById('admin-feedback-cancel')?.addEventListener('click', () => {
        document.getElementById('admin-feedback-modal').style.display = 'none';
    });
    document.getElementById('admin-feedback-confirm')?.addEventListener('click', async () => {
        const text = document.getElementById('admin-feedback-text').value.trim();
        if (text.length < 10) { showToast('Minim 10 caractere.', 'warning'); return; }
        try {
            await api.post(`/api/admin/campings/${pendingFeedbackId}/reject-feedback`, { feedback: text });
            document.getElementById('admin-feedback-modal').style.display = 'none';
            showToast('Feedback trimis cu succes.', 'success');
            loadAdminCampings(adminCampingFilter);
        } catch (err) {
            showToast('Eroare: ' + (err.message ?? 'Ceva a mers gresit'), 'error');
        }
    });

    document.getElementById('admin-ban-cancel')?.addEventListener('click', () => {
        document.getElementById('admin-ban-modal').style.display = 'none';
    });
    document.getElementById('admin-ban-confirm')?.addEventListener('click', async () => {
        const reason = document.getElementById('admin-ban-reason').value.trim();
        const daysVal = document.getElementById('admin-ban-days').value.trim();
        const days = daysVal ? parseInt(daysVal) : null;

        if (reason.length < 3) { showToast('Motivul trebuie sa aiba minim 3 caractere.', 'warning'); return; }
        if (daysVal && (!days || days < 1)) { showToast('Durata invalida.', 'warning'); return; }

        try {
            await api.post(`/api/admin/users/${pendingBanId}/ban`, { reason, days });
            document.getElementById('admin-ban-modal').style.display = 'none';
            showToast('Utilizator banat.', 'success');
            loadAdminUsers(true);
        } catch (err) {
            showToast('Eroare: ' + (err.message ?? 'Ceva a mers gresit'), 'error');
        }
    });

    // Inchide modals cu click pe overlay
    ['admin-feedback-modal', 'admin-ban-modal'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', e => {
            if (e.target === e.currentTarget) e.currentTarget.style.display = 'none';
        });
    });
});
