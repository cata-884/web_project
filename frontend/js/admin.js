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

function adminMsg(container, cls, text) {
    container.innerHTML = '';
    const p = document.createElement('p');
    p.className = cls;
    p.textContent = text;
    container.appendChild(p);
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
}

async function loadAdminCampings(status = '') {
    const grid = document.getElementById('admin-campings-grid');
    if (!grid) return;
    adminMsg(grid, 'admin-loading', t('admin.loading'));

    try {
        const qs = status !== '' ? `?status=${encodeURIComponent(status)}` : '';
        const res = await api.get(`/api/admin/campings${qs}`);

        const campings = res?.campings ?? [];

        if (!campings.length) {
            adminMsg(grid, 'admin-empty', 'Nicio cerere gasita pentru aceasta categorie.');
            return;
        }
        const frag = document.createDocumentFragment();
        campings.forEach(c => frag.appendChild(renderCampingCard(c)));
        grid.innerHTML = '';
        grid.appendChild(frag);
    } catch (err) {
        adminMsg(grid, 'admin-empty', t('admin.err_prefix') + (err.message ?? ''));
        grid.querySelector('.admin-empty').style.color = 'var(--ar)';
    }
}

function renderCampingCard(c) {
    const meta = STATUS_META();
    const st = meta[String(c.approval_status)] ?? meta['0'];
    const isPending = Number(c.approval_status) === 0;
    const node = cloneTemplate('tpl-admin-camping-card');
    const el = node.querySelector('.admin-camping-card');

    el.classList.add(st.card);
    el.querySelector('.ac-name').textContent = c.name;
    const badge = el.querySelector('.ac-badge');
    badge.textContent = st.label;
    badge.classList.add(st.cls);

    el.querySelector('.ac-type-region').textContent = `${c.type} • ${c.region}`;
    if (c.price_per_night) {
        const priceEl = el.querySelector('.ac-price');
        priceEl.textContent = `${Number(c.price_per_night).toFixed(0)} RON/noapte`;
        priceEl.style.display = '';
    }
    el.querySelector('.ac-date').textContent = `Trimis: ${fmtDate(c.created_at)}`;

    const userEl = el.querySelector('.ac-user');
    userEl.textContent = `${c.full_name || c.username} — `;
    const em = document.createElement('em');
    em.textContent = c.email;
    userEl.appendChild(em);

    if (c.company_name) {
        const compRow = el.querySelector('.ac-company-row');
        let txt = c.company_name;
        if (c.business_type) txt += ` (${c.business_type})`;
        if (c.registration_number) txt += ` • CIF: ${c.registration_number}`;
        el.querySelector('.ac-company').textContent = txt;
        compRow.style.display = '';
    }

    const addrParts = [c.address_street, c.address_number, c.address_city, c.address_zip].filter(Boolean);
    if (addrParts.length) {
        el.querySelector('.ac-addr').textContent = addrParts.join(', ');
        el.querySelector('.ac-addr-row').style.display = '';
    }

    const contactParts = [c.contact_phone, c.contact_email].filter(Boolean);
    if (contactParts.length) {
        el.querySelector('.ac-contact').textContent = contactParts.join(' • ');
        el.querySelector('.ac-contact-row').style.display = '';
    }

    const docsEl = el.querySelector('.ac-docs');
    const docDefs = [
        { path: c.id_document_path,           label: '↓ Document Identitate' },
        { path: c.registration_document_path, label: '↓ Certificat Inregistrare' },
    ];
    docDefs.forEach(({ path, label }) => {
        if (!path) return;
        const a = cloneTemplate('tpl-admin-doc-link').querySelector('a');
        a.href = path;
        a.textContent = label;
        docsEl.appendChild(a);
    });
    if (docsEl.children.length) docsEl.style.display = '';

    if (Number(c.approval_status) === 2 && c.admin_feedback) {
        const fb = el.querySelector('.ac-feedback');
        const strong = document.createElement('strong');
        strong.textContent = 'Feedback trimis: ';
        fb.appendChild(strong);
        fb.appendChild(document.createTextNode(c.admin_feedback));
        fb.style.display = '';
    }

    if (isPending) {
        const actionsEl = el.querySelector('.ac-actions');
        actionsEl.style.display = '';
        const btnApprove = actionsEl.querySelector('.ac-btn-approve');
        const btnReject  = actionsEl.querySelector('.ac-btn-reject');
        const btnFb      = actionsEl.querySelector('.ac-btn-feedback');
        btnApprove.textContent = 'Aproba';
        btnReject.textContent  = 'Respinge';
        btnFb.textContent      = 'Respinge cu Feedback';
        btnApprove.addEventListener('click', () => adminCampingAction(c.id, 'approve'));
        btnReject.addEventListener('click',  () => adminCampingAction(c.id, 'reject'));
        btnFb.addEventListener('click',      () => openFeedbackModal(c.id));
    }

    return node;
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
    if (reset) adminMsg(grid, 'admin-loading', t('admin.loading'));

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
            adminMsg(grid, 'admin-empty', 'Niciun utilizator gasit.');
            return;
        }

        const frag = document.createDocumentFragment();
        users.forEach(u => frag.appendChild(renderUserCard(u)));
        if (reset) { grid.innerHTML = ''; }
        grid.appendChild(frag);

        document.getElementById('admin-users-more')?.remove();
        const loaded = adminUserOffset + users.length;
        if (loaded < total) {
            const more = cloneTemplate('tpl-admin-load-more');
            more.querySelector('.alm-count').textContent = `${loaded} din ${total}`;
            more.querySelector('.alm-btn').addEventListener('click', () => {
                adminUserOffset += ADMIN_USER_LIMIT;
                loadAdminUsers();
            });
            grid.after(more.querySelector('.admin-load-more'));
        }
    } catch (err) {
        adminMsg(grid, 'admin-empty', t('admin.err_prefix') + (err.message ?? ''));
        grid.querySelector('.admin-empty').style.color = 'var(--ar)';
    }
}

function renderUserCard(u) {
    const initial = (u.username ?? '?')[0].toUpperCase();
    const roleClass = { user: 'a-role-user', organizer: 'a-role-organizer', admin: 'a-role-admin' }[u.role] ?? 'a-role-user';
    const roleLabel = { user: 'User', organizer: 'Organizer', admin: 'Admin' }[u.role] ?? u.role;
    const isBanned = u.is_banned === true || u.is_banned === 't' || u.is_banned === '1';

    const node = cloneTemplate('tpl-admin-user-card');
    const el = node.querySelector('.admin-user-card');
    if (isBanned) el.classList.add('banned');

    const avatarEl = el.querySelector('.au-avatar');
    if (u.avatar_url) {
        const img = document.createElement('img');
        img.src = u.avatar_url;
        img.alt = u.username;
        img.loading = 'lazy';
        avatarEl.appendChild(img);
    } else {
        avatarEl.textContent = initial;
    }

    el.querySelector('.au-name').textContent = u.full_name || u.username;
    el.querySelector('.au-meta').textContent = `${u.email} • @${u.username} • ${fmtDate(u.created_at)}`;

    const roleEl = el.querySelector('.au-role');
    roleEl.textContent = roleLabel;
    roleEl.classList.add(roleClass);

    const banBtn = el.querySelector('.au-ban-btn');
    if (u.role !== 'admin') {
        if (isBanned) {
            banBtn.className = 'btn-unban';
            banBtn.textContent = 'Ridica Ban';
            banBtn.addEventListener('click', () => adminUnbanUser(u.id));
        } else {
            banBtn.className = 'btn-ban';
            banBtn.textContent = 'Baneaza';
            banBtn.addEventListener('click', () => openBanModal(u.id));
        }
    } else {
        banBtn.remove();
    }

    return node;
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
    adminMsg(container, 'admin-loading', t('admin.loading'));

    try {
        const d = await api.get('/api/admin/stats/summary');
        if (!d) return;
        const bookings = d.bookings_by_status ?? {};
        const totalBookings = Object.values(bookings).reduce((a, b) => a + Number(b), 0);

        const frag = document.createDocumentFragment();

        const statDefs = [
            { val: d.nr_users ?? 0,    lbl: 'Utilizatori' },
            { val: d.nr_campings ?? 0, lbl: 'Campinguri Active' },
            { val: d.nr_pending ?? 0,  lbl: 'Cereri in Asteptare' },
            { val: totalBookings,       lbl: 'Rezervari Total' },
            { val: `${Number(d.total_revenue ?? 0).toLocaleString('ro-RO', { minimumFractionDigits: 0 })} RON`,
              lbl: 'Venituri Confirmate', extra: 'stat-card--revenue' },
        ];
        statDefs.forEach(({ val, lbl, extra }) => {
            const node = cloneTemplate('tpl-stat-card');
            const card = node.querySelector('.stat-card');
            if (extra) card.classList.add(extra);
            card.querySelector('.stat-val').textContent = val;
            card.querySelector('.stat-lbl').textContent = lbl;
            frag.appendChild(node);
        });

        if ((d.top_regions ?? []).length) {
            const wideNode = cloneTemplate('tpl-stat-card');
            const wide = wideNode.querySelector('.stat-card');
            wide.classList.add('stat-card--wide');
            const hdr = document.createElement('div');
            hdr.className = 'stat-lbl';
            hdr.style.marginBottom = '8px';
            hdr.textContent = 'Top Regiuni';
            wide.querySelector('.stat-val').replaceWith(hdr);
            d.top_regions.forEach(r => {
                const row = cloneTemplate('tpl-stat-region-row');
                row.querySelector('.sr-name').textContent = r.region;
                row.querySelector('.sr-cnt').textContent  = r.cnt;
                wide.appendChild(row);
            });
            frag.appendChild(wideNode);
        }

        container.innerHTML = '';
        container.appendChild(frag);
    } catch (err) {
        adminMsg(container, 'admin-empty', t('admin.err_prefix') + (err.message ?? ''));
        container.querySelector('.admin-empty').style.color = 'var(--ar)';
        showToast(t('admin.stats_err'), 'error');
    }

    loadAdminChart();
}


async function loadAdminMessages() {
    const grid = document.getElementById('admin-messages-grid');
    if (!grid) return;

    adminMsg(grid, 'admin-loading', 'Se încarcă mesajele...');

    try {
        const res = await api.get('/api/admin/messages');
        const messages = res?.messages ?? [];

        if (messages.length > 0) {
            const frag = document.createDocumentFragment();
            messages.forEach(m => {
                const node = cloneTemplate('tpl-admin-message-card');
                const card = node.querySelector('.admin-message-card');
                card.addEventListener('click', () => card.classList.toggle('expanded'));

                card.querySelector('.am-avatar').textContent = (m.name || '?')[0].toUpperCase();
                card.querySelector('.am-name').textContent   = m.name;
                const metaParts = [m.email, m.phone].filter(Boolean);
                card.querySelector('.am-meta').textContent   = metaParts.join(' • ');
                card.querySelector('.am-date').textContent   = `${fmtDate(m.created_at)} • MESAJ CONTACT`;

                const textEl = card.querySelector('.am-text');
                m.message.split('\n').forEach((line, i) => {
                    if (i > 0) textEl.appendChild(document.createElement('br'));
                    textEl.appendChild(document.createTextNode(line));
                });

                frag.appendChild(node);
            });
            grid.innerHTML = '';
            grid.appendChild(frag);
        } else {
            grid.innerHTML = '';
            const p = document.createElement('p');
            p.className = 'admin-empty';
            p.textContent = 'Nu există mesaje în baza de date.';
            grid.appendChild(p);
        }
    } catch (err) {
        adminMsg(grid, 'admin-empty', `Eroare: ${err.message}`);
        grid.querySelector('.admin-empty').style.color = 'var(--ar)';
        console.error(err);
    }
}

async function loadAdminChart() {
    const container = document.getElementById('admin-chart-container');
    if (!container) return;
    adminMsg(container, 'admin-loading', t('admin.loading_chart'));

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
        adminMsg(container, 'admin-empty', t('admin.err_prefix') + (err.message ?? ''));
        container.querySelector('.admin-empty').style.color = 'var(--ar)';
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
            //Resetam active pe butoane
            document.querySelectorAll('.admin-panel-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            //Ascundem toate panelurile
            document.querySelectorAll('.admin-panel').forEach(p => p.style.display = 'none');

            //Afisam panelul corect
            const panel = document.getElementById('admin-panel-' + btn.dataset.panel);
            if (panel) panel.style.display = 'block';

            //Apelam functia de incarcare corespunzatoare (O SINGURA DATA!)
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

// export

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

// import

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

        resultEl.innerHTML = '';
        if (!resp.ok) {
            const span = document.createElement('span');
            span.className = 'io-err';
            span.textContent = t('admin.err_prefix') + (data.error ?? '');
            resultEl.appendChild(span);
        } else {
            const ok = document.createElement('span');
            ok.className = 'io-ok';
            ok.textContent = ` ${data.inserted} / ${data.total} ${t('admin.import_rows_ok')}`;
            resultEl.appendChild(ok);
            if (data.errors?.length) {
                const ul = document.createElement('ul');
                ul.className = 'import-errors';
                data.errors.forEach(e => {
                    const li = document.createElement('li');
                    li.textContent = `Rand ${e.row}: ${e.error}`;
                    ul.appendChild(li);
                });
                resultEl.appendChild(ul);
            }
        }
        resultEl.style.display = 'block';
    } catch (err) {
        resultEl.innerHTML = '';
        const span = document.createElement('span');
        span.className = 'io-err';
        span.textContent = t('admin.import_net_err') + (err.message ?? '');
        resultEl.appendChild(span);
        resultEl.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = t('admin.import_btn');
    }
}
