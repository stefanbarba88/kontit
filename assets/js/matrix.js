import { ajax } from './ajax.js';
import { toast } from './confirm.js';

/**
 * Matrica "Pregled poslova po klijentima" (SPEC 8r): filteri (AJAX, bez osvežavanja strane) i učitavanje sledećih strana.
 * Ćelije su samo prikaz statusa (bez klika). Stanje filtera je u URL-u (deljiv link) i pamti se po korisniku u localStorage.
 */
function init(root) {
    const form = root.querySelector('[data-kt-mx-filters]');
    const bodyEl = root.querySelector('[data-kt-matrix-body]');
    const full = root.dataset.full === '1';
    const storageKey = `kt-matrix-${root.dataset.user}`;
    const defaultPeriod = root.dataset.defaultPeriod || '';
    const defaultCat = root.dataset.defaultCat || '';
    let page = Number(root.dataset.page) || 1;
    let timer = null;
    let seq = 0;

    const field = (name) => form.elements[name];

    /** Filteri iz forme kao URL parametri (prazni se ne šalju) */
    const params = () => {
        const p = new URLSearchParams();
        const q = field('q').value.trim();
        if (q) { p.set('q', q); }
        if (field('user').value) { p.set('user', field('user').value); }
        if (field('cat') && field('cat').value) { p.set('cat', field('cat').value); }
        if (field('period').value) { p.set('period', field('period').value); }

        return p;
    };

    const activeCount = () => {
        const p = params();
        return ['q', 'user'].filter((k) => p.has(k)).length;
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
        // podrazumevani period i podrazumevana (prva) kategorija sami ne čine filter
        const rest = new URLSearchParams(p);
        if (rest.get('period') === defaultPeriod) { rest.delete('period'); }
        if (rest.get('cat') === defaultCat) { rest.delete('cat'); }
        const onlyDefault = !query || rest.size === 0;
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
            if (nextPage === 1) {
                bodyEl.innerHTML = data.body;
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
    // "Poništi filtere" resetuje pretragu, zaposlenog i mesec; izabrana kategorija ostaje (to je izbor prikaza, ne filter)
    form.querySelector('[data-kt-mx-reset]').addEventListener('click', () => {
        field('q').value = '';
        field('user').tomselect ? field('user').tomselect.clear(true) : (field('user').value = '');
        field('period').value = defaultPeriod || field('period').value;
        refresh();
    });

    root.addEventListener('click', (e) => {
        if (e.target.closest('[data-kt-mx-more]')) {
            load(page + 1);
        }
    });

    // sačuvani filteri se primenjuju samo kad link nema svoje
    try {
        const saved = localStorage.getItem(storageKey);
        const hasUrlFilters = ['q', 'user', 'cat', 'period'].some((k) => new URLSearchParams(location.search).has(k));
        if (saved && !hasUrlFilters) {
            const p = new URLSearchParams(saved);
            field('q').value = p.get('q') || '';
            const setSelect = (el, values) => {
                if (el.tomselect) { el.tomselect.setValue(values, true); } else { [...el.options].forEach((o) => { o.selected = values.includes(o.value); }); }
            };
            setSelect(field('user'), p.get('user') ? [p.get('user')] : []);
            if (p.get('cat') && [...field('cat').options].some((o) => o.value === p.get('cat'))) { setSelect(field('cat'), [p.get('cat')]); }
            if (p.get('period') && [...field('period').options].some((o) => o.value === p.get('period'))) { field('period').value = p.get('period'); }
            refresh();
        }
    } catch (e) { /* bez localStorage-a nema pamćenja */ }
}

document.querySelectorAll('[data-kt-matrix]').forEach(init);
