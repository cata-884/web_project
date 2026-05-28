let adminCampingFilter = '';
let adminUserOffset = 0;
const ADMIN_USER_LIMIT = 20;
let pendingFeedbackId = null;
let pendingBanId = null;

function STATUS_META() {
    return {
        '-1': { label: t('admin.status_label_rejected'), cls: 'a-badge--1', card: 'st--1' },
        '0':  { label: t('admin.status_label_pending'),  cls: 'a-badge-0',  card: 'st-0'  },
        '1':  { label: t('admin.status_label_approved'), cls: 'a-badge-1',  card: 'st-1'  },
        '2':  { label: t('admin.status_label_feedback'), cls: 'a-badge-2',  card: 'st-2'  },
    };
}

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
    grid.innerHTML = `<p class="admin-loading">${t('admin.loading')}</p>`;

    try {
        const qs = status !== '' ? `?status=${encodeURIComponent(status)}` : '';
        // Apelul corect pentru campinguri
        const res = await api.get(`/api/admin/campings${qs}`);

        const campings = res?.campings ?? [];

        if (!campings.length) {
            grid.innerHTML = '<p class="admin-empty">Nicio cerere gasita pentru aceasta categorie.</p>';
            return;
        }
        grid.innerHTML = campings.map(renderCampingCard).join('');
    } catch (err) {
        grid.innerHTML = `<p class="admin-empty" style="color:var(--ar)">${t('admin.err_prefix')}${esc(err.message)}</p>`;
    }
}

function renderCampingCard(c) {
    const meta = STATUS_META();
    const st = meta[String(c.approval_status)] ?? meta['0'];
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
        showToast(t('admin.err_prefix') + (err.message ?? t('admin.err_generic')), 'error');
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
    if (reset) grid.innerHTML = `<p class="admin-loading">${t('admin.loading')}</p>`;

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
        grid.innerHTML = `<p class="admin-empty" style="color:var(--ar)">${t('admin.err_prefix')}${esc(err.message)}</p>`;
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
        showToast(t('admin.unban_ok'), 'success');
        loadAdminUsers(true);
    } catch (err) {
        showToast(t('admin.err_prefix') + (err.message ?? t('admin.err_generic')), 'error');
    }
}

async function loadAdminStats() {
    const container = document.getElementById('admin-stats-summary');
    if (!container) return;
    container.innerHTML = `<p class="admin-loading">${t('admin.loading')}</p>`;

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
        container.innerHTML = `<p class="admin-empty" style="color:var(--ar)">${t('admin.err_prefix')}${esc(err.message)}</p>`;
        showToast(t('admin.stats_err'), 'error');
    }

    loadAdminChart();
}


async function loadAdminMessages() {
    const grid = document.getElementById('admin-messages-grid');
    if (!grid) return;

    grid.innerHTML = '<p class="admin-loading">Se încarcă mesajele...</p>';

    try {
        const res = await api.get('/api/admin/messages');
        const messages = res?.messages ?? [];

        if (messages.length > 0) {
            grid.innerHTML = messages.map(m => {
                // Luăm prima literă din nume pentru pătratul gri
                const initial = esc(m.name)[0].toUpperCase();

                return `
                <div class="admin-message-card expandable-card" onclick="this.classList.toggle('expanded')">
                    <div class="msg-header-row">
                        <div class="msg-avatar">${initial}</div>

                        <div class="msg-info">
                            <h3 class="msg-title">${esc(m.name)}</h3>
                            <p class="msg-meta">${esc(m.email)} ${m.phone ? `&bull; ${esc(m.phone)}` : ''}</p>
                            <span class="msg-date-badge">${fmtDate(m.created_at)} &bull; MESAJ CONTACT</span>
                        </div>

                        <div class="msg-expand-icon"></div>
                    </div>

                    <div class="msg-body">
                        <p class="msg-text">${esc(m.message).replace(/\n/g, '<br>')}</p>
                    </div>
                </div>
            `}).join('');
        } else {
            grid.innerHTML = '<p class="admin-empty">Nu există mesaje în baza de date.</p>';
        }
    } catch (err) {
        grid.innerHTML = `<p class="admin-empty" style="color:var(--ar)">Eroare: ${err.message}</p>`;
        console.error(err);
    }
}

async function loadAdminChart() {
    const container = document.getElementById('admin-chart-container');
    if (!container) return;
    container.innerHTML = `<p class="admin-loading">${t('admin.loading_chart')}</p>`;

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
        container.innerHTML = `<p class="admin-empty" style="color:var(--ar)">${t('admin.err_prefix')}${esc(err.message)}</p>`;
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
            showToast(t('admin.err_prefix') + (j.error ?? `HTTP ${res.status}`), 'error');
            return;
        }
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `raport-cat-${new Date().toISOString().slice(0,10)}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
        showToast(t('admin.pdf_ok'), 'success');
    } catch (err) {
        showToast(t('admin.err_prefix') + err.message, 'error');
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
        if (text.length < 10) { showToast(t('admin.feedback_min'), 'warning'); return; }
        try {
            await api.post(`/api/admin/campings/${pendingFeedbackId}/reject-feedback`, { feedback: text });
            document.getElementById('admin-feedback-modal').style.display = 'none';
            showToast(t('admin.feedback_ok'), 'success');
            loadAdminCampings(adminCampingFilter);
        } catch (err) {
            showToast(t('admin.err_prefix') + (err.message ?? t('admin.err_generic')), 'error');
        }
    });

    document.getElementById('admin-ban-cancel')?.addEventListener('click', () => {
        document.getElementById('admin-ban-modal').style.display = 'none';
    });
    document.getElementById('admin-ban-confirm')?.addEventListener('click', async () => {
        const reason = document.getElementById('admin-ban-reason').value.trim();
        const daysVal = document.getElementById('admin-ban-days').value.trim();
        const days = daysVal ? parseInt(daysVal) : null;

        if (reason.length < 3) { showToast(t('admin.ban_reason_min'), 'warning'); return; }
        if (daysVal && (!days || days < 1)) { showToast(t('admin.ban_duration_invalid'), 'warning'); return; }

        try {
            await api.post(`/api/admin/users/${pendingBanId}/ban`, { reason, days });
            document.getElementById('admin-ban-modal').style.display = 'none';
            showToast(t('admin.ban_ok'), 'success');
            loadAdminUsers(true);
        } catch (err) {
            showToast(t('admin.err_prefix') + (err.message ?? t('admin.err_generic')), 'error');
        }
    });

    // Inchide modals cu click pe overlay
    ['admin-feedback-modal', 'admin-ban-modal'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', e => {
            if (e.target === e.currentTarget) e.currentTarget.style.display = 'none';
        });
    });

    // Import — drag & drop + file select
    const dropZone  = document.getElementById('import-drop-zone');
    const fileInput = document.getElementById('import-file-input');
    const submitBtn = document.getElementById('import-submit-btn');
    const dropLabel = document.getElementById('import-drop-label');

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length) {
                dropLabel.textContent = fileInput.files[0].name;
                submitBtn.disabled = false;
            }
        });
    }

    if (dropZone) {
        dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            const f = e.dataTransfer.files[0];
            if (f) {
                const dt = new DataTransfer();
                dt.items.add(f);
                fileInput.files = dt.files;
                dropLabel.textContent = f.name;
                submitBtn.disabled = false;
            }
        });
    }
});

// ===== EXPORT =====

function adminExport(entity, format) {
    const token = localStorage.getItem('cat_token');
    if (!token) { showToast(t('admin.unauth'), 'error'); return; }
    const url = `${API_BASE}/api/admin/export/${entity}.${format}?token=${encodeURIComponent(token)}`;
    const a = document.createElement('a');
    a.href = url;
    a.download = '';
    document.body.appendChild(a);
    a.click();
    a.remove();
}

// ===== IMPORT =====

async function adminImport() {
    const fileInput = document.getElementById('import-file-input');
    const resultEl  = document.getElementById('import-result');
    const submitBtn = document.getElementById('import-submit-btn');

    if (!fileInput?.files.length) { showToast(t('admin.import_no_file'), 'error'); return; }

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    submitBtn.disabled = true;
    submitBtn.textContent = t('admin.import_loading');
    resultEl.style.display = 'none';

    try {
        const token = localStorage.getItem('cat_token');
        const resp  = await fetch(`${API_BASE}/api/admin/import/campings`, {
            method: 'POST',
            headers: { Authorization: 'Bearer ' + token },
            body: formData,
        });
        const data = await resp.json();

        if (!resp.ok) {
            resultEl.innerHTML = `<span class="io-err">${t('admin.err_prefix')}${esc(data.error)}</span>`;
        } else {
            let html = `<span class="io-ok"> ${data.inserted} / ${data.total} ${t('admin.import_rows_ok')}</span>`;
            if (data.errors?.length) {
                html += '<ul class="import-errors">' +
                    data.errors.map(e => `<li>Rand ${e.row}: ${esc(e.error)}</li>`).join('') +
                    '</ul>';
            }
            resultEl.innerHTML = html;
        }
        resultEl.style.display = 'block';
    } catch (err) {
        resultEl.innerHTML = `<span class="io-err">${t('admin.import_net_err')}${esc(err.message)}</span>`;
        resultEl.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = t('admin.import_btn');
    }
}
