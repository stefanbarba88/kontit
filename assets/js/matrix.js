import Swal from 'sweetalert2';
import { ajax } from './ajax.js';
import { swalFire, toast } from './confirm.js';
import { icon } from './icons.js';

/**
 * Matrica "Pregled poslova po klijentima" (SPEC 8r): filteri (AJAX, bez osvežavanja strane), učitavanje sledećih strana
 * i SweetAlert prozor sa zadacima ćelije. Stanje filtera je u URL-u (deljiv link) i pamti se po korisniku u localStorage.
 * Spisak zadataka ćelije se učitava tek na klik (GET /ajax/matrix/cell) i drži u memoriji strane dok se filteri ne promene.
 */
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const STATUS_ICON = { NOVO: 'circle', U_RADU: 'play', CEKA_KLIJENTA: 'hourglass', NA_PROVERI: 'eye', ZAVRSENO: 'circle-check-big' };
const GROUP_ICON = { overdue: 'triangle-alert', soon: 'clock-alert' };
const SUMMARY_ICON = { danger: 'triangle-alert', warning: 'clock-alert', success: 'circle-check-big' };

function init(root) {
    const form = root.querySelector('[data-kt-mx-filters]');
    const bodyEl = root.querySelector('[data-kt-matrix-body]');
    const countersEl = root.querySelector('[data-kt-mx-counters]');
    const full = root.dataset.full === '1';
    const storageKey = `kt-matrix-${root.dataset.user}`;
    const defaultPeriod = root.dataset.defaultPeriod || '';
    const cache = new Map();
    let page = Number(root.dataset.page) || 1;
    let timer = null;
    let seq = 0;

    const field = (name) => form.elements[name];

    /** Filteri iz forme kao URL parametri (prazni se ne šalju) */
    const params = () => {
        const p = new URLSearchParams();
        const q = field('q').value.trim();
        const pkg = [...field('pkg').selectedOptions].map((o) => o.value).filter(Boolean);
        if (q) { p.set('q', q); }
        if (field('user').value) { p.set('user', field('user').value); }
        if (pkg.length) { p.set('pkg', pkg.join(',')); }
        if (field('period').value) { p.set('period', field('period').value); }
        if (field('open').checked) { p.set('open', '1'); }
        if (field('problem').checked) { p.set('problem', '1'); }

        return p;
    };

    const activeCount = () => {
        const p = params();
        return ['q', 'user', 'pkg', 'open', 'problem'].filter((k) => p.has(k)).length;
    };

    const updateBadge = () => {
        const btn = root.querySelector('.kt-mx-filters-bar button');
        if (!btn) { return; }
        btn.querySelector('.kt-filter-count')?.remove();
        const n = activeCount();
        if (n) {
            btn.insertAdjacentHTML('beforeend', ` <span class="kt-filter-count">${n}</span>`);
        }
        form.querySelector('[data-kt-mx-clear-q]').hidden = field('q').value === '';
    };

    const remember = () => {
        const p = params();
        const query = p.toString();
        // period je podrazumevan: sam ne čini filter
        const onlyDefault = !query || (p.size === 1 && p.get('period') === defaultPeriod);
        // izvoz uvek nosi trenutne filtere (isti upit kao tabela)
        document.querySelectorAll('[data-kt-mx-export] a[href]').forEach((a) => {
            const u = new URL(a.getAttribute('href'), location.origin);
            a.setAttribute('href', `${u.pathname}${query ? `?${query}` : ''}`);
        });
        try {
            history.replaceState(null, '', onlyDefault ? location.pathname : `${location.pathname}?${query}`);
        } catch (e) { /* bez history API-ja URL se samo ne ažurira */ }
        try {
            if (onlyDefault) { localStorage.removeItem(storageKey); } else { localStorage.setItem(storageKey, query); }
        } catch (e) { /* bez localStorage-a filteri se samo ne pamte */ }
    };

    const load = async (nextPage = 1) => {
        const mine = ++seq;
        const p = params();
        p.set('full', full ? '1' : '0');
        p.set('page', String(nextPage));
        bodyEl.classList.add('is-loading');
        try {
            const { data } = await ajax('GET', `${root.dataset.url}?${p.toString()}`);
            if (mine !== seq) { return; }
            countersEl.innerHTML = data.counters;
            if (nextPage === 1) {
                bodyEl.innerHTML = data.body;
                cache.clear();
            } else {
                bodyEl.querySelector('[data-kt-mx-rows]')?.insertAdjacentHTML('beforeend', data.rows);
                bodyEl.querySelector('[data-kt-mx-cards]')?.insertAdjacentHTML('beforeend', data.cards);
                bodyEl.querySelector('[data-kt-mx-foot]').innerHTML = data.foot;
            }
            page = nextPage;
            observeMore();
        } catch (e) {
            if (mine === seq) { toast(e.message || 'Pregled nije moguće osvežiti. Pokušaj ponovo.', 'danger'); }
        } finally {
            if (mine === seq) { bodyEl.classList.remove('is-loading'); }
        }
    };

    const refresh = () => {
        updateBadge();
        remember();
        load(1);
    };

    // na stranici se sledeća strana učitava i kad "Učitaj još" dođe u vidno polje
    let observer = null;
    const observeMore = () => {
        observer?.disconnect();
        const more = bodyEl.querySelector('[data-kt-mx-more]');
        if (full && more && 'IntersectionObserver' in window) {
            observer = new IntersectionObserver((entries) => {
                if (entries.some((e) => e.isIntersecting)) { observer.disconnect(); load(page + 1); }
            }, { rootMargin: '200px' });
            observer.observe(more);
        }
    };
    observeMore();

    // --- događaji filtera ---
    field('q').addEventListener('input', () => {
        clearTimeout(timer);
        updateBadge();
        timer = setTimeout(refresh, 300);
    });
    field('q').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(timer); refresh(); } });
    form.querySelector('[data-kt-mx-clear-q]').addEventListener('click', () => { field('q').value = ''; clearTimeout(timer); refresh(); field('q').focus(); });
    form.addEventListener('change', (e) => { if (e.target !== field('q')) { refresh(); } });
    form.querySelector('[data-kt-mx-reset]').addEventListener('click', () => {
        field('q').value = '';
        field('user').tomselect ? field('user').tomselect.clear(true) : (field('user').value = '');
        field('pkg').tomselect ? field('pkg').tomselect.clear(true) : [...field('pkg').options].forEach((o) => { o.selected = false; });
        field('period').value = defaultPeriod || field('period').value;
        field('open').checked = false;
        field('problem').checked = false;
        refresh();
    });

    root.addEventListener('click', (e) => {
        // brojač u zaglavlju: uključuje "Samo problematični"
        if (e.target.closest('[data-kt-mx-problem]')) {
            field('problem').checked = true;
            refresh();

            return;
        }
        if (e.target.closest('[data-kt-mx-more]')) {
            load(page + 1);

            return;
        }
        const cell = e.target.closest('[data-kt-mx-cell]');
        if (cell) { openCell(cell); }
    });

    // sačuvani filteri se primenjuju samo kad link nema svoje
    try {
        const saved = localStorage.getItem(storageKey);
        const hasUrlFilters = ['q', 'user', 'pkg', 'period', 'open', 'problem'].some((k) => new URLSearchParams(location.search).has(k));
        if (saved && !hasUrlFilters) {
            const p = new URLSearchParams(saved);
            field('q').value = p.get('q') || '';
            const setSelect = (el, values) => {
                if (el.tomselect) { el.tomselect.setValue(values, true); } else { [...el.options].forEach((o) => { o.selected = values.includes(o.value); }); }
            };
            setSelect(field('user'), p.get('user') ? [p.get('user')] : []);
            setSelect(field('pkg'), p.get('pkg') ? p.get('pkg').split(',') : []);
            if (p.get('period') && [...field('period').options].some((o) => o.value === p.get('period'))) { field('period').value = p.get('period'); }
            field('open').checked = p.get('open') === '1';
            field('problem').checked = p.get('problem') === '1';
            refresh();
        }
    } catch (e) { /* bez localStorage-a nema pamćenja */ }

    // --- SweetAlert prozor sa zadacima ćelije ---
    const skeleton = '<div class="kt-mx-skel" aria-busy="true"><span></span><span></span><span></span></div>';

    const taskRow = (t, group) => {
        const cls = group === 'overdue' ? 'is-overdue' : (group === 'soon' ? 'is-soon' : (group === 'done' ? 'is-done' : ''));
        const ic = GROUP_ICON[group] || STATUS_ICON[t.status] || 'circle';
        const due = group === 'done' ? (t.finishedAt ? `završeno ${esc(t.finishedAt)}` : '') : (t.due ? `rok ${esc(t.due)}` : 'bez roka');
        const who = t.people.map((u) => `<span class="kt-avatar" title="${esc(u.name)}">${esc(u.initials)}</span>`).join('');

        return `<div class="kt-mx-task ${cls}" title="${esc(t.statusLabel)}">${icon(ic)}<a href="${esc(t.url)}">${esc(t.title)}</a><span class="kt-mx-due">${due}</span><span class="kt-mx-who">${who}</span></div>`;
    };

    const renderCell = (d) => {
        const groups = d.groups.map((g) => `<section class="kt-mx-group"><h3 class="kt-mx-group-title">${esc(g.label)}</h3>${g.rows.map((t) => taskRow(t, g.key)).join('')}</section>`).join('');
        const more = d.more > 0 ? `<div class="kt-mx-more">i još ${d.more} ${d.more === 1 ? 'zadatak' : (d.more < 5 ? 'zadatka' : 'zadataka')}</div>` : '';
        const sum = `<div class="kt-mx-sum kt-mx-${esc(d.state)}">${SUMMARY_ICON[d.state] ? icon(SUMMARY_ICON[d.state]) : ''}${esc(d.summary)}</div>`;

        return `${sum}<div class="kt-mx-list">${groups || '<p class="text-muted mb-0">Nema zadataka u ovom periodu.</p>'}${more}</div>`;
    };

    async function openCell(btn) {
        const period = field('period').value;
        const key = `${btn.dataset.client}|${btn.dataset.package}|${period}`;
        const url = `${root.dataset.cellUrl}?client=${encodeURIComponent(btn.dataset.client)}&package=${encodeURIComponent(btn.dataset.package)}&period=${encodeURIComponent(period)}`;
        let listUrl = null;

        const fill = async () => {
            const box = Swal.getHtmlContainer();
            if (!box) { return; }
            box.innerHTML = skeleton;
            try {
                if (!cache.has(key)) { cache.set(key, (await ajax('GET', url)).data); }
                const d = cache.get(key);
                if (!Swal.isVisible()) { return; }
                Swal.getTitle().textContent = `${d.packageName} · ${d.clientName} · ${d.period}`;
                Swal.getHtmlContainer().innerHTML = renderCell(d);
                listUrl = d.listUrl;
                Swal.getConfirmButton().disabled = false;
            } catch (e) {
                if (!Swal.isVisible()) { return; }
                Swal.getHtmlContainer().innerHTML = `<p class="text-danger">${esc(e.message || 'Zadatke nije moguće učitati.')}</p><button type="button" class="btn btn-outline-secondary btn-sm" data-kt-mx-retry>Pokušaj ponovo</button>`;
                Swal.getHtmlContainer().querySelector('[data-kt-mx-retry]').addEventListener('click', fill);
            }
        };

        const result = await swalFire({
            title: btn.getAttribute('aria-label')?.split(':')[0] || 'Zadaci',
            html: skeleton,
            showCancelButton: true,
            confirmButtonText: 'Otvori listu zadataka',
            cancelButtonText: 'Zatvori',
            allowOutsideClick: true,
            allowEscapeKey: true,
            returnFocus: true,
            customClass: { popup: 'kt-swal kt-mx-pop' },
            didOpen: () => {
                Swal.getConfirmButton().disabled = true;
                fill();
            },
        });
        if (result.isConfirmed && listUrl) {
            window.location.href = listUrl;
        }
    }
}

document.querySelectorAll('[data-kt-matrix]').forEach(init);
