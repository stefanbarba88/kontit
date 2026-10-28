// Telefon (DESIGN.md 16): tabele kao liste (labela iz zaglavlja), filteri u bottom sheet-u, "Učitaj još",
// prevlačenje sheet-a nadole, swipe akcije na zadacima, dugme nazad i pretraga preko celog ekrana.
import { Offcanvas, Modal } from 'bootstrap';

const phone = () => window.matchMedia('(max-width: 575.98px)').matches;

// --- tabele: svaka ćelija dobija data-label iz zaglavlja (CSS je na telefonu pretvara u karticu-red) ---
document.querySelectorAll('table.table').forEach((table) => {
    const heads = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
    if (!heads.length) {
        return;
    }
    table.classList.add('kt-stack');
    table.querySelectorAll('tbody tr').forEach((tr) => {
        [...tr.children].forEach((td, i) => {
            if (heads[i] && !td.hasAttribute('data-label')) {
                td.setAttribute('data-label', heads[i]);
            }
            if (!heads[i]) {
                td.classList.add('kt-stack-actions');
            }
            if (td.hasAttribute('colspan')) {
                td.classList.add('kt-stack-full');
            }
        });
    });
});

// --- filteri u bottom sheet-u ---
document.querySelectorAll('form[data-kt-filters]').forEach((form, index) => {
    if (!phone()) {
        return;
    }
    // aktivan filter = vrednost različita od podrazumevane (prva opcija liste, prazno polje)
    const active = [...form.querySelectorAll('select, input:not([type=hidden])')].filter((el) => (
        el.tagName === 'SELECT' ? el.selectedIndex > 0 : el.value !== ''
    )).length;
    const sheet = document.createElement('div');
    sheet.className = 'offcanvas offcanvas-bottom kt-sheet';
    sheet.id = `kt-filters-${index}`;
    sheet.tabIndex = -1;
    sheet.innerHTML = '<div class="kt-sheet-handle"></div><div class="kt-sheet-title">Filteri</div><div class="kt-sheet-body"></div>';
    const holder = form.closest('.card-body') || form.parentElement;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-light kt-filter-btn';
    button.innerHTML = `<svg viewBox="0 0 24 24" class="kt-icon me-2" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54z"/></g></svg>Filteri${active ? ` <span class="kt-filter-count">${active}</span>` : ''}`;
    button.setAttribute('data-bs-toggle', 'offcanvas');
    button.setAttribute('data-bs-target', `#${sheet.id}`);
    sheet.querySelector('.kt-sheet-body').appendChild(form);
    document.body.appendChild(sheet);
    holder.appendChild(button);
});

// --- "Učitaj još" umesto brojeva strana ---
document.querySelectorAll('.pagination').forEach((pagination) => {
    if (!phone()) {
        return;
    }
    const table = document.querySelector('table.kt-stack tbody');
    const nextHref = () => {
        const active = pagination.querySelector('.page-item.active');
        return active?.nextElementSibling?.querySelector('a.page-link')?.getAttribute('href') || null;
    };
    let next = nextHref();
    pagination.closest('nav, div')?.classList.add('d-none');
    if (!table || !next) {
        return;
    }
    const more = document.createElement('button');
    more.type = 'button';
    more.className = 'btn btn-light w-100 kt-load-more';
    more.textContent = 'Učitaj još';
    table.closest('.card').after(more);
    more.addEventListener('click', async () => {
        more.classList.add('is-loading');
        const html = await (await fetch(next, { headers: { 'X-Requested-With': 'fetch' } })).text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const heads = [...table.closest('table').querySelectorAll('thead th')].map((th) => th.textContent.trim());
        doc.querySelectorAll('table.table tbody tr').forEach((tr) => {
            [...tr.children].forEach((td, i) => heads[i] && td.setAttribute('data-label', heads[i]));
            table.appendChild(document.importNode(tr, true));
        });
        const docActive = doc.querySelector('.pagination .page-item.active');
        next = docActive?.nextElementSibling?.querySelector('a.page-link')?.getAttribute('href') || null;
        more.classList.remove('is-loading');
        if (!next) {
            more.remove();
        }
    });
});

// --- bottom sheet i modal: zatvaranje prevlačenjem nadole ---
const dragToClose = (panel, close) => {
    let startY = null;
    let dy = 0;
    panel.addEventListener('touchstart', (e) => {
        if (panel.scrollTop > 0 && !e.target.closest('.kt-sheet-handle')) {
            return;
        }
        startY = e.touches[0].clientY;
        dy = 0;
        panel.style.transition = 'none';
    }, { passive: true });
    panel.addEventListener('touchmove', (e) => {
        if (startY === null) {
            return;
        }
        dy = Math.max(0, e.touches[0].clientY - startY);
        panel.style.transform = `translateY(${dy}px)`;
    }, { passive: true });
    panel.addEventListener('touchend', () => {
        if (startY === null) {
            return;
        }
        panel.style.transition = '';
        panel.style.transform = '';
        if (dy > 80) {
            close();
        }
        startY = null;
    });
};
document.addEventListener('shown.bs.offcanvas', (e) => {
    if (e.target.classList.contains('kt-sheet') && !e.target.dataset.ktDrag) {
        e.target.dataset.ktDrag = '1';
        dragToClose(e.target, () => Offcanvas.getInstance(e.target)?.hide());
    }
});
document.addEventListener('shown.bs.modal', (e) => {
    const content = e.target.querySelector('.modal-content');
    if (phone() && content && !content.dataset.ktDrag) {
        content.dataset.ktDrag = '1';
        dragToClose(content, () => Modal.getInstance(e.target)?.hide());
    }
});

// --- swipe ulevo na redu zadatka otkriva brze akcije ---
document.querySelectorAll('.kt-swipe').forEach((row) => {
    const content = row.querySelector('.kt-swipe-content');
    const width = () => row.querySelector('.kt-swipe-actions')?.offsetWidth || 0;
    let startX = null;
    let startY = null;
    let dx = 0;
    let open = false;
    content.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        dx = 0;
        content.style.transition = 'none';
    }, { passive: true });
    content.addEventListener('touchmove', (e) => {
        if (startX === null) {
            return;
        }
        const x = e.touches[0].clientX - startX;
        if (Math.abs(e.touches[0].clientY - startY) > Math.abs(x)) {
            return;
        }
        dx = Math.min(0, Math.max(-width(), x + (open ? -width() : 0)));
        content.style.transform = `translateX(${dx}px)`;
    }, { passive: true });
    content.addEventListener('touchend', () => {
        content.style.transition = '';
        open = dx < -width() / 2;
        content.style.transform = open ? `translateX(${-width()}px)` : '';
        startX = null;
    });
    content.addEventListener('click', (e) => {
        if (open) {
            e.preventDefault();
            open = false;
            content.style.transform = '';
        }
    });
});

// --- dugme nazad: istorija ako smo došli iz aplikacije, inače poslednja karika u putanji ---
const back = document.querySelector('[data-kt-back]');
if (back) {
    const crumbs = [...document.querySelectorAll('.kt-breadcrumb a.breadcrumb-item')];
    const topLevel = ['app_dashboard', 'task_index', 'client_index', 'client_mine', 'absence_index', 'notification_index', 'app_profile',
        'admin_user_index', 'admin_team_index', 'admin_job_index', 'admin_package_index', 'admin_finance', 'admin_generation', 'admin_non_working_index'];
    if (topLevel.includes(document.body.dataset.route)) {
        back.hidden = true;
    }
    back.addEventListener('click', () => {
        if (document.referrer.startsWith(window.location.origin) && window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = crumbs.length ? crumbs[crumbs.length - 1].href : '/';
        }
    });
}

// --- pretraga preko celog ekrana ---
const overlay = document.getElementById('kt-search');
if (overlay) {
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-kt-search-open]')) {
            overlay.hidden = false;
            overlay.querySelector('input').focus();
        }
        if (e.target.closest('[data-kt-search-close]')) {
            overlay.hidden = true;
        }
    });
}

// "Moji klijenti" nemaju pretragu na serveru: kartice se filtriraju po ?q=
const q = new URLSearchParams(window.location.search).get('q');
if (q && document.body.dataset.route === 'client_mine') {
    const needle = q.toLowerCase();
    document.querySelectorAll('[data-kt-client-card]').forEach((card) => {
        (card.closest('[class*="col-"]') || card).hidden = !card.textContent.toLowerCase().includes(needle);
    });
}
