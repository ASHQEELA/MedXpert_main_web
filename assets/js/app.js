// Bangla text for messages made in the browser. The server fills window.MX_T when the language is Bangla.
function __(text) { return (window.MX_T && window.MX_T[text]) || text; }

// Translate text the browser builds itself (lists, notes, cart). Runs only when the language is Bangla.
(function () {
    if (!window.MX_T) { return; }
    var DIGITS = '০১২৩৪৫৬৭৮৯';
    function bnDigits(v) { return String(v).replace(/[0-9]/g, function (d) { return DIGITS[d]; }); }
    var DAYS = { Sun: 'রবি', Mon: 'সোম', Tue: 'মঙ্গল', Wed: 'বুধ', Thu: 'বৃহস্পতি', Fri: 'শুক্র', Sat: 'শনি' };
    function translateText(text) {
        var T = window.MX_T, m;
        if (T[text]) { return T[text]; }
        if ((m = /^(\d+) years? experience$/.exec(text))) { return bnDigits(m[1]) + ' বছরের অভিজ্ঞতা'; }
        if ((m = /^(\d+) years old$/.exec(text))) { return 'বয়স ' + bnDigits(m[1]) + ' বছর'; }
        if ((m = /^Last donated (\d{4}-\d{2}-\d{2})$/.exec(text))) { return 'সর্বশেষ রক্তদান: ' + bnDigits(m[1]); }
        if ((m = /^Only (\d+) left$/.exec(text))) { return 'আছে মাত্র ' + bnDigits(m[1]) + 'টি'; }
        if ((m = /^(.+) added to your cart$/.exec(text))) { return m[1] + (T[' added to your cart'] || ' added to your cart'); }
        if ((m = /^(.+) each$/.exec(text)) && /^[^\d]*[\d,.]+$/.test(m[1])) { return m[1] + (T[' each'] || ' each'); }
        if ((m = /^Decrease quantity of (.+)$/.exec(text))) { return m[1] + ' এর পরিমাণ কমান'; }
        if ((m = /^Increase quantity of (.+)$/.exec(text))) { return m[1] + ' এর পরিমাণ বাড়ান'; }
        if ((m = /^Remove (.+)$/.exec(text))) { return m[1] + ' সরান'; }
        if (/^(Sun|Mon|Tue|Wed|Thu|Fri|Sat)(-(Sun|Mon|Tue|Wed|Thu|Fri|Sat))?, \d/.test(text)) {
            return bnDigits(text.replace(/Sun|Mon|Tue|Wed|Thu|Fri|Sat/g, function (d) { return DAYS[d]; }).replace(/ AM/g, ' এএম').replace(/ PM/g, ' পিএম'));
        }
        return null;
    }
    function translateNode(root) {
        if (!root || root.nodeType !== 1) { return; }
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        var node, list = [];
        while ((node = walker.nextNode())) {
            var tag = node.parentNode && node.parentNode.nodeName;
            if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'TEXTAREA') { continue; }
            list.push(node);
        }
        list.forEach(function (n) {
            var raw = n.nodeValue, trimmed = raw.trim();
            if (!trimmed || !/[A-Za-z]{2}/.test(trimmed)) { return; }
            var out = translateText(trimmed);
            if (out) { n.nodeValue = raw.replace(trimmed, out); }
        });
        var els = root.querySelectorAll('[title],[aria-label],[placeholder]');
        for (var i = 0; i < els.length; i++) {
            ['title', 'aria-label', 'placeholder'].forEach(function (a) {
                var v = els[i].getAttribute(a);
                if (v) { var o = translateText(v); if (o) { els[i].setAttribute(a, o); } }
            });
        }
    }
    document.addEventListener('DOMContentLoaded', function () {
        new MutationObserver(function (records) {
            records.forEach(function (r) { r.addedNodes.forEach(function (n) { translateNode(n.nodeType === 1 ? n : null); }); });
        }).observe(document.body, { childList: true, subtree: true });
    });
})();

// MedXpert - Modern JavaScript

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    initAuthTabs();
    initSidebar();
    initModals();
    initSearch();
    initFilters();
    initLists();
    initA11y();
});

// CSRF token for form submissions made with fetch
function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// POST a form and always return a usable result, even when the server sends a non-JSON error
function postJson(url, formData) {
    return fetch(url, { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(response => response.text().then(text => {
            let data = null;
            try { data = JSON.parse(text); } catch (e) { /* not JSON */ }
            if (data && typeof data === 'object') return data;
            if (response.status === 419) {
                return { success: false, message: 'Your session expired. Reload the page and try again.' };
            }
            if (response.status === 401 || response.status === 403) {
                return { success: false, message: 'Please log in again.' };
            }
            if (response.status === 429) {
                return { success: false, message: 'Too many requests. Please wait and try again.' };
            }
            return { success: false, message: 'The server could not finish your request. Please try again.' };
        }));
}

// Cancel an appointment or order, then refresh the list
function cancelItem(url, id, question) {
    if (!window.confirm(question)) return;
    const formData = new FormData();
    formData.set('csrf_token', csrfToken());
    formData.set('id', id);
    postJson(url, formData)
        .then(data => {
            showToast(data.success ? 'success' : 'error', data.message || 'Done');
            if (data.success) setTimeout(() => window.location.reload(), 900);
        })
        .catch(() => showToast('error', 'Check your connection and try again.'));
}

// Auth Page - Tab Switching
function initAuthTabs() {
    const tabs = document.querySelectorAll('.auth-tab');
    const forms = document.querySelectorAll('.auth-form');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const target = this.dataset.tab;
            
            // Remove active class from all tabs and forms
            tabs.forEach(t => t.classList.remove('active'));
            forms.forEach(f => f.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding form
            this.classList.add('active');
            document.getElementById(target).classList.add('active');
        });
    });
}

// Sidebar Toggle for Mobile
function initSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    if (!sidebar) return;

    const isDesktop = () => window.matchMedia('(min-width: 1025px)').matches;

    function syncToggle() {
        if (!toggleBtn) return;
        if (isDesktop()) {
            const collapsed = document.body.classList.contains('sidebar-collapsed');
            toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggleBtn.setAttribute('aria-label', __(collapsed ? 'Expand sidebar' : 'Collapse sidebar'));
            toggleBtn.title = __(collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        } else {
            const open = sidebar.classList.contains('active');
            toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggleBtn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
            toggleBtn.title = open ? 'Close menu' : 'Open menu';
        }
    }

    // Tooltips for the icon-only sidebar
    sidebar.querySelectorAll('.menu-item').forEach(item => {
        const label = item.querySelector('span');
        if (label) item.setAttribute('data-label', label.textContent.trim());
    });

    function applyCollapsed(collapsed, save) {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        sidebar.querySelectorAll('.menu-item[data-label]').forEach(item => {
            if (collapsed) item.setAttribute('title', item.dataset.label);
            else item.removeAttribute('title');
        });
        if (save) {
            try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
        }
        syncToggle();
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            if (isDesktop()) {
                applyCollapsed(!document.body.classList.contains('sidebar-collapsed'), true);
            } else {
                sidebar.classList.toggle('active');
                if (overlay) overlay.classList.toggle('active');
                syncToggle();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            syncToggle();
        });
    }

    // Leaving mobile width closes the slide-out menu
    window.addEventListener('resize', function() {
        if (isDesktop()) {
            sidebar.classList.remove('active');
            if (overlay) overlay.classList.remove('active');
        }
        syncToggle();
    });

    applyCollapsed(document.body.classList.contains('sidebar-collapsed') && isDesktop(), false);
}

// Modal Functions
function initModals() {
    const modals = document.querySelectorAll('.modal');
    const modalTriggers = document.querySelectorAll('[data-modal]');
    const modalCloses = document.querySelectorAll('.modal-close');
    
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            const modalId = this.dataset.modal;
            openModal(modalId);
        });
    });
    
    modalCloses.forEach(close => {
        close.addEventListener('click', function() {
            const modal = this.closest('.modal');
            closeModal(modal.id);
        });
    });
    
    // Close modal on background click
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
}

let lastFocusedBeforeModal = null;

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        lastFocusedBeforeModal = document.activeElement;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        const first = modal.querySelector('input:not([type="hidden"]), select, textarea, button.btn');
        if (first) setTimeout(() => first.focus(), 50);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
        if (lastFocusedBeforeModal && document.contains(lastFocusedBeforeModal)) {
            lastFocusedBeforeModal.focus();
        }
    }
}

// ===== Accessibility helpers =====
let a11yIdCounter = 0;

function initA11y() {
    // Connect each label to the control in the same form group
    document.querySelectorAll('.form-group').forEach(group => {
        const label = group.querySelector('label.form-label');
        const control = group.querySelector('input:not([type="hidden"]), select, textarea');
        if (!label || !control || label.hasAttribute('for')) return;
        if (!control.id) control.id = 'field-' + (++a11yIdCounter);
        label.setAttribute('for', control.id);
    });

    // Search boxes only have a placeholder
    document.querySelectorAll('.search-input').forEach(input => {
        if (!input.getAttribute('aria-label')) {
            input.setAttribute('aria-label', input.getAttribute('placeholder') || 'Search');
        }
    });

    // Modals behave as dialogs
    document.querySelectorAll('.modal').forEach(modal => {
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        const title = modal.querySelector('.modal-header h3');
        if (title) {
            if (!title.id) title.id = 'modal-title-' + (++a11yIdCounter);
            modal.setAttribute('aria-labelledby', title.id);
        }
    });

    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.setAttribute('type', 'button');
        if (!btn.getAttribute('aria-label')) btn.setAttribute('aria-label', __('Close dialog'));
    });

    // Escape closes the open dialog
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        const open = document.querySelector('.modal.active');
        if (open) closeModal(open.id);
    });

    const toggle = document.getElementById('sidebarToggle');
    if (toggle) toggle.setAttribute('aria-controls', 'sidebar');
}

// Live Search
// ===== Helpers =====
function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

function initials(name) {
    const parts = String(name || '').replace(/^(dr\.?|md\.?)\s+/i, '').trim().split(/\s+/).filter(Boolean);
    const first = parts[0] ? parts[0][0] : '?';
    const last = parts.length > 1 ? parts[parts.length - 1][0] : '';
    return esc((first + last).toUpperCase());
}

function formatMoney(amount) {
    return '৳' + Number(amount || 0).toLocaleString('en-US', { maximumFractionDigits: 2 });
}

function avatarHtml(name, imageUrl) {
    return `<div class="avatar" aria-hidden="true"><span>${initials(name)}</span>${
        imageUrl ? `<img src="${esc(imageUrl)}" alt="" loading="lazy" onerror="this.remove()">` : ''
    }</div>`;
}

function showSkeleton(container, count = 6) {
    container.setAttribute('aria-busy', 'true');
    container.innerHTML = Array.from({ length: count }, () => `
        <div class="item-card skeleton-card" aria-hidden="true">
            <div class="skeleton skeleton-avatar"></div>
            <div class="skeleton skeleton-line w-60"></div>
            <div class="skeleton skeleton-line w-40"></div>
            <div class="skeleton skeleton-line"></div>
            <div class="skeleton skeleton-line"></div>
            <div class="skeleton skeleton-btn"></div>
        </div>`).join('');
}

function showState(container, { icon, title, text, actionLabel, action, kind }) {
    container.removeAttribute('aria-busy');
    container.innerHTML = `
        <div class="state-box">
            <div class="state-icon"><i class="fas fa-${icon}"></i></div>
            <h3>${esc(title)}</h3>
            <p>${esc(text)}</p>
            ${action ? `<button type="button" class="btn btn-secondary" data-action="${action}" data-kind="${kind}">${esc(actionLabel)}</button>` : ''}
        </div>`;
}

function setBusy(form, busy, label) {
    label = label ? __(label) : label;
    const btn = form && form.querySelector('[type="submit"]');
    if (!btn) return;
    if (busy) {
        btn.dataset.label = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<span class="btn-spinner"></span> ${esc(label || 'Please wait...')}`;
    } else {
        btn.disabled = false;
        if (btn.dataset.label) btn.innerHTML = btn.dataset.label;
    }
}

// ===== List loading (doctors, donors, medicines) =====
const LIST_CONFIG = {
    doctors: {
        url: '../api/get_doctors.php', container: 'doctorsContainer', key: 'doctors',
        render: renderDoctors, noun: 'doctors', icon: 'user-md', filterParam: null
    },
    donors: {
        url: '../api/get_donors.php', container: 'donorsContainer', key: 'donors',
        render: renderDonors, noun: 'donors', icon: 'tint', filterParam: 'blood_type'
    },
    medicines: {
        url: '../api/get_medicines.php', container: 'medicinesContainer', key: 'medicines',
        render: renderMedicines, noun: 'medicines', icon: 'pills', filterParam: 'category'
    }
};

const listRequestId = {};

function getListFilters(kind) {
    const cfg = LIST_CONFIG[kind];
    const searchEl = document.querySelector(`[data-search="${kind}"]`);
    const term = searchEl ? searchEl.value.trim() : '';
    const filter = cfg.filterParam
        ? (document.querySelector('.filter-btn.active')?.dataset.filter || 'all')
        : 'all';
    return { term, filter };
}

function loadItems(kind) {
    const cfg = LIST_CONFIG[kind];
    const container = document.getElementById(cfg.container);
    if (!container) return;

    const { term, filter } = getListFilters(kind);
    const params = new URLSearchParams();
    if (term) params.set('search', term);
    if (cfg.filterParam && filter !== 'all') params.set(cfg.filterParam, filter);

    const requestId = (listRequestId[kind] || 0) + 1;
    listRequestId[kind] = requestId;
    showSkeleton(container);

    fetch(`${cfg.url}?${params.toString()}`)
        .then(response => {
            if (!response.ok) throw new Error('Server error ' + response.status);
            return response.json();
        })
        .then(data => {
            if (requestId !== listRequestId[kind]) return;
            if (!data.success) throw new Error(data.message || 'Request failed');
            const items = data[cfg.key] || [];
            if (items.length === 0) {
                const filtered = term !== '' || filter !== 'all';
                showState(container, {
                    icon: cfg.icon,
                    title: `No ${cfg.noun} found`,
                    text: filtered ? 'Try a different search or clear your filters.' : `No ${cfg.noun} are listed yet.`,
                    actionLabel: 'Clear filters',
                    action: filtered ? 'clear-filters' : null,
                    kind
                });
                return;
            }
            container.removeAttribute('aria-busy');
            cfg.render(items);
        })
        .catch(error => {
            if (requestId !== listRequestId[kind]) return;
            console.error('Error:', error);
            showState(container, {
                icon: 'exclamation-triangle',
                title: 'Could not load data',
                text: 'Check your connection and try again.',
                actionLabel: 'Try again',
                action: 'retry',
                kind
            });
        });
}

function clearFilters(kind) {
    const searchEl = document.querySelector(`[data-search="${kind}"]`);
    if (searchEl) searchEl.value = '';
    const buttons = document.querySelectorAll('.filter-btn');
    buttons.forEach(b => b.classList.toggle('active', b.dataset.filter === 'all'));
    loadItems(kind);
}

function initSearch() {
    document.querySelectorAll('.search-input').forEach(input => {
        input.addEventListener('input', debounce(function(e) {
            const kind = e.target.dataset.search;
            if (LIST_CONFIG[kind]) loadItems(kind);
        }, 300));
    });
}

function initFilters() {
    const kindByType = { blood: 'donors', medicine: 'medicines' };
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            this.parentElement.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const kind = kindByType[this.dataset.type];
            if (kind) loadItems(kind);
        });
    });
}

function initLists() {
    loadCart();
    saveCart();
    Object.keys(LIST_CONFIG).forEach(kind => {
        if (document.getElementById(LIST_CONFIG[kind].container)) loadItems(kind);
    });

    document.addEventListener('click', function(e) {
        const el = e.target.closest('[data-action]');
        if (!el) return;
        const action = el.dataset.action;
        if (action === 'book') bookAppointment(el.dataset.id);
        else if (action === 'order') addToCart(el.dataset.id, el.dataset.name, parseFloat(el.dataset.price), el.dataset.rx === '1');
        else if (action === 'line') changeLine(el.dataset.id, parseInt(el.dataset.step, 10));
        else if (action === 'remove-line') removeLine(el.dataset.id);
        else if (action === 'retry') loadItems(el.dataset.kind);
        else if (action === 'clear-filters') clearFilters(el.dataset.kind);
    });
}

// ===== Render functions =====
function renderDoctors(doctors) {
    const container = document.getElementById('doctorsContainer');
    if (!container) return;

    container.innerHTML = doctors.map(d => {
        const imageUrl = (d.image && d.image !== 'default-doctor.png') ? `/uploads/doctors/${d.image}` : null;
        const schedule = [d.available_days, d.available_time].filter(Boolean).join(', ');
        return `
        <article class="item-card">
            <div class="item-header">
                ${avatarHtml(d.name, imageUrl)}
                <div class="item-info">
                    <h3>${esc(d.name)}</h3>
                    <p class="item-sub">${esc(d.specialization)}</p>
                    ${Number(d.verified) === 1 ? '<span class="verified-badge"><i class="fas fa-circle-check"></i> Verified</span>' : ''}
                </div>
                <span class="rating" title="Rating"><i class="fas fa-star"></i> ${esc(Number(d.rating).toFixed(1))}</span>
            </div>
            <div class="item-meta">
                <div class="meta-item"><i class="fas fa-graduation-cap"></i><span>${esc(d.qualification)}</span></div>
                <div class="meta-item"><i class="fas fa-hospital"></i><span>${esc(d.hospital)}, ${esc(d.location)}</span></div>
                <div class="meta-item"><i class="fas fa-briefcase-medical"></i><span>${esc(d.experience)} years experience</span></div>
                ${schedule ? `<div class="meta-item"><i class="fas fa-calendar-alt"></i><span>${esc(schedule)}</span></div>` : ''}
            </div>
            <div class="item-footer">
                <div>
                    <span class="price-label">Consultation fee</span>
                    <span class="price">${formatMoney(d.fee)}</span>
                </div>
                <button type="button" class="btn btn-primary" data-action="book" data-id="${esc(d.id)}">Book</button>
            </div>
        </article>`;
    }).join('');
}

function renderDonors(donors) {
    const container = document.getElementById('donorsContainer');
    if (!container) return;

    container.innerHTML = donors.map(d => {
        const imageUrl = (d.image && d.image !== 'default-donor.png') ? `/uploads/donors/${d.image}` : null;
        const tel = String(d.phone || '').replace(/[^\d+]/g, '');
        return `
        <article class="item-card">
            <div class="item-header">
                ${avatarHtml(d.name, imageUrl)}
                <div class="item-info">
                    <h3>${esc(d.name)}</h3>
                    <p class="item-sub">${esc(d.location)}</p>
                </div>
                <span class="blood-badge" title="Blood type">${esc(d.blood_type)}</span>
            </div>
            <div class="item-meta">
                <div class="meta-item"><i class="fas fa-user"></i><span>${esc(d.age)} years old</span></div>
                <div class="meta-item"><i class="fas fa-calendar"></i><span>${d.last_donation ? 'Last donated ' + esc(d.last_donation) : 'No donation recorded'}</span></div>
                <div class="meta-item"><i class="fas fa-phone"></i><span>${esc(d.phone)}</span></div>
            </div>
            <div class="item-footer">
                <span class="badge badge-success">Available</span>
                <a class="btn btn-primary" href="tel:${esc(tel)}"><i class="fas fa-phone"></i> Call donor</a>
            </div>
        </article>`;
    }).join('');
}

function renderMedicines(medicines) {
    const container = document.getElementById('medicinesContainer');
    if (!container) return;

    container.innerHTML = medicines.map(m => {
        const imageUrl = (m.image && m.image !== 'default-medicine.png') ? `/uploads/medicines/${m.image}` : null;
        const stock = parseInt(m.stock, 10) || 0;
        const stockBadge = stock <= 0
            ? '<span class="badge badge-danger">Out of stock</span>'
            : (stock <= 10
                ? `<span class="badge badge-warning">Only ${stock} left</span>`
                : '<span class="badge badge-success">In stock</span>');
        return `
        <article class="item-card">
            <div class="med-thumb" aria-hidden="true">
                <i class="fas fa-pills"></i>
                ${imageUrl ? `<img src="${esc(imageUrl)}" alt="" loading="lazy" onerror="this.remove()">` : ''}
            </div>
            <div class="item-info">
                <h3>${esc(m.name)}</h3>
                <p class="item-sub">${esc(m.category)}</p>
            </div>
            <div class="badge-row">
                ${stockBadge}
                ${Number(m.prescription_required) ? '<span class="badge badge-info">Prescription</span>' : ''}
            </div>
            <div class="item-meta">
                ${m.dosage ? `<div class="meta-item"><i class="fas fa-pills"></i><span>${esc(m.dosage)}</span></div>` : ''}
                ${m.manufacturer ? `<div class="meta-item"><i class="fas fa-industry"></i><span>${esc(m.manufacturer)}</span></div>` : ''}
            </div>
            <div class="item-footer">
                <span class="price">${formatMoney(m.price)}</span>
                <button type="button" class="btn btn-primary" data-action="order" data-id="${esc(m.id)}" data-name="${esc(m.name)}" data-price="${esc(m.price)}" data-rx="${Number(m.prescription_required) ? 1 : 0}" ${stock <= 0 ? 'disabled' : ''}>Add to cart</button>
            </div>
        </article>`;
    }).join('');
}

// Action Functions
function bookAppointment(doctorId) {
    // Set doctor ID in hidden field
    document.getElementById('appointmentDoctorId').value = doctorId;
    document.getElementById('appointmentDate').value = '';
    loadSlots();
    openModal('appointmentModal');
}

// Show the free times for the chosen doctor and date
function loadSlots() {
    const doctorId = document.getElementById('appointmentDoctorId').value;
    const date = document.getElementById('appointmentDate').value;
    const picker = document.getElementById('slotPicker');
    const hidden = document.getElementById('appointmentTime');
    hidden.value = '';
    if (!doctorId || !date) {
        picker.innerHTML = '<p class="slot-note">Pick a date to see the times.</p>';
        return;
    }
    picker.innerHTML = '<p class="slot-note">Loading times...</p>';
    fetch(`../api/get_slots.php?doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(date)}`, { credentials: 'same-origin' })
        .then(response => response.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Could not load times');
            if (!data.slots.length) {
                picker.innerHTML = '<p class="slot-note">The doctor does not see patients on this day. Try another date.</p>';
                return;
            }
            picker.innerHTML = '';
            data.slots.forEach(slot => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'slot-btn';
                button.textContent = slotLabel(slot.time);
                button.disabled = !slot.available;
                button.setAttribute('aria-pressed', 'false');
                button.addEventListener('click', () => {
                    picker.querySelectorAll('.slot-btn').forEach(b => { b.classList.remove('selected'); b.setAttribute('aria-pressed', 'false'); });
                    button.classList.add('selected');
                    button.setAttribute('aria-pressed', 'true');
                    hidden.value = slot.time;
                });
                picker.appendChild(button);
            });
            if (!data.slots.some(slot => slot.available)) {
                picker.insertAdjacentHTML('beforeend', '<p class="slot-note">All times are taken on this day. Try another date.</p>');
            }
        })
        .catch(() => { picker.innerHTML = '<p class="slot-note">Could not load times. Try again.</p>'; });
}

function slotLabel(time) {
    const [h, m] = time.split(':').map(Number);
    return `${((h + 11) % 12) + 1}:${String(m).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`;
}

function submitAppointment() {
    const form = document.getElementById('appointmentForm');
    const formData = new FormData(form);
    formData.set('csrf_token', csrfToken());
    
    // Ensure doctor_id is properly set
    const doctorId = document.getElementById('appointmentDoctorId').value;
    if (doctorId) {
        formData.set('doctor_id', doctorId);
    }
    if (!formData.get('appointment_time')) {
        showAlert('error', 'Pick a time for your appointment.');
        return;
    }
    
    setBusy(form, true, 'Booking...');
    postJson('../api/book_appointment.php', formData)
    .then(data => {
        if (data.success) {
            showAlert('success', 'Appointment booked successfully!');
            closeModal('appointmentModal');
            form.reset();
        } else {
            showAlert('error', data.message || 'Failed to book appointment');
        }
    })
    .catch(error => {
        showAlert('error', 'An error occurred. Please try again.');
        console.error('Error:', error);
    })
    .finally(() => setBusy(form, false));
}

function contactDonor(phone, name) {
    if (confirm(`Contact ${name} at ${phone}?`)) {
        window.location.href = `tel:${phone}`;
    }
}

// ===== Cart =====
let cart = {};

function loadCart() {
    try {
        const saved = JSON.parse(localStorage.getItem('mx_cart') || '{}');
        if (saved && typeof saved === 'object') cart = saved;
    } catch (e) { cart = {}; }
}

function saveCart() {
    try { localStorage.setItem('mx_cart', JSON.stringify(cart)); } catch (e) {}
    const count = Object.values(cart).reduce((sum, line) => sum + line.qty, 0);
    const badge = document.getElementById('cartCount');
    if (badge) {
        badge.textContent = count;
        badge.hidden = count === 0;
    }
}

function addToCart(id, name, price, rx) {
    const line = cart[id] || { id: String(id), name, price, rx: !!rx, qty: 0 };
    line.qty = Math.min(99, line.qty + 1);
    line.price = price;
    line.rx = !!rx;
    cart[id] = line;
    saveCart();
    showToast('success', name + __(' added to your cart'));
}

function openCart() {
    renderCart();
    openModal('orderModal');
}

function changeLine(id, step) {
    const line = cart[id];
    if (!line) return;
    line.qty = Math.min(99, Math.max(1, line.qty + step));
    saveCart();
    renderCart();
}

function removeLine(id) {
    delete cart[id];
    saveCart();
    renderCart();
}

function renderCart() {
    const box = document.getElementById('cartLines');
    if (!box) return;
    const lines = Object.values(cart);
    const submit = document.getElementById('placeOrderBtn');
    if (!lines.length) {
        box.innerHTML = '<p class="slot-note">Your cart is empty. Add medicines from the list.</p>';
        submit.disabled = true;
    } else {
        submit.disabled = false;
        box.innerHTML = lines.map(line => `
            <div class="cart-line">
                <div class="cart-line-info">
                    <strong>${esc(line.name)}</strong>
                    ${line.rx ? '<span class="badge badge-info">Prescription</span>' : ''}
                    <small>${formatMoney(line.price)} each</small>
                </div>
                <div class="cart-line-qty">
                    <button type="button" class="qty-btn" data-action="line" data-step="-1" data-id="${esc(line.id)}" aria-label="Decrease quantity of ${esc(line.name)}">&minus;</button>
                    <span aria-live="polite">${line.qty}</span>
                    <button type="button" class="qty-btn" data-action="line" data-step="1" data-id="${esc(line.id)}" aria-label="Increase quantity of ${esc(line.name)}">+</button>
                </div>
                <strong class="cart-line-total">${formatMoney(line.price * line.qty)}</strong>
                <button type="button" class="qty-btn" data-action="remove-line" data-id="${esc(line.id)}" aria-label="Remove ${esc(line.name)}"><i class="fas fa-trash"></i></button>
            </div>`).join('');
    }
    const total = lines.reduce((sum, line) => sum + line.price * line.qty, 0);
    document.getElementById('orderTotal').textContent = total.toFixed(2);
    const rxNames = lines.filter(line => line.rx).map(line => line.name);
    document.getElementById('rxGroup').hidden = rxNames.length === 0;
    document.getElementById('rxNames').textContent = rxNames.join(', ');
    document.getElementById('prescriptionFile').required = rxNames.length > 0;
}

function submitOrder() {
    const form = document.getElementById('orderForm');
    const lines = Object.values(cart);
    if (!lines.length) {
        showAlert('error', 'Your cart is empty.');
        return;
    }
    const formData = new FormData(form);
    formData.set('csrf_token', csrfToken());
    formData.set('items', JSON.stringify(lines.map(line => ({ medicine_id: Number(line.id), quantity: line.qty }))));

    setBusy(form, true, 'Placing order...');
    postJson('../api/order_medicine.php', formData)
    .then(data => {
        if (data.success) {
            showAlert('success', __('Order placed successfully! Order #') + data.order_number);
            closeModal('orderModal');
            form.reset();
            cart = {};
            saveCart();
            loadItems('medicines');
        } else {
            showAlert('error', data.message || 'Failed to place order');
        }
    })
    .catch(error => {
        showAlert('error', 'An error occurred. Please try again.');
        console.error('Error:', error);
    })
    .finally(() => setBusy(form, false));
}

function requestAmbulance(type) {
    if (type === 'emergency') {
        if (confirm(__('This will call 999, the national emergency line. Continue?'))) {
            window.location.href = 'tel:999';
        }
    } else {
        openModal('ambulanceModal');
    }
}

function submitAmbulanceRequest() {
    const form = document.getElementById('ambulanceForm');
    const formData = new FormData(form);
    formData.set('csrf_token', csrfToken());
    
    // Set request_type to 'scheduled' for form submissions
    formData.set('request_type', 'scheduled');
    
    setBusy(form, true, 'Submitting...');
    postJson('../api/request_ambulance.php', formData)
    .then(data => {
        if (data.success) {
            showAlert('success', 'Ambulance request submitted successfully!');
            closeModal('ambulanceModal');
            form.reset();
        } else {
            showAlert('error', data.message || 'Failed to submit request');
        }
    })
    .catch(error => {
        showAlert('error', 'An error occurred. Please try again.');
        console.error('Error:', error);
    })
    .finally(() => setBusy(form, false));
}

// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function showToast(type, message) {
    message = __(message);
    const icons = { success: 'check-circle', error: 'exclamation-circle', warning: 'exclamation-triangle', info: 'info-circle' };
    const kind = icons[type] ? type : 'info';

    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        container.setAttribute('aria-live', 'polite');
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${kind}`;
    toast.setAttribute('role', kind === 'error' ? 'alert' : 'status');

    const icon = document.createElement('i');
    icon.className = `fas fa-${icons[kind]}`;
    const text = document.createElement('span');
    text.textContent = message;
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'toast-close';
    close.setAttribute('aria-label', 'Dismiss');
    close.innerHTML = '&times;';

    toast.append(icon, text, close);
    container.appendChild(toast);

    const dismiss = () => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 300);
    };
    close.addEventListener('click', dismiss);
    setTimeout(dismiss, kind === 'error' ? 6000 : 4000);
}

// Kept so existing calls still work
function showAlert(type, message) {
    showToast(type, message);
}

// Form Validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    const inputs = form.querySelectorAll('[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = 'var(--danger)';
            isValid = false;
        } else {
            input.style.borderColor = '';
        }
    });
    
    return isValid;
}
