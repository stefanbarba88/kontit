import { ajax } from './ajax.js';
import { toast } from './confirm.js';

// Timeline sa "Učitaj još" i filterima (SPEC 8f.1). Prva strana dolazi sa serverom, sledeće preko /ajax/.../activity?page=N.
document.querySelectorAll('[data-kt-activity]').forEach((feed) => {
    const list = feed.querySelector('.kt-timeline');
    const more = feed.querySelector('[data-kt-activity-more]');
    const skeleton = feed.querySelector('.kt-tl-skeleton');
    const empty = feed.querySelector('.kt-tl-empty');
    const action = feed.querySelector('[data-kt-activity-action]');
    const period = feed.querySelector('[data-kt-activity-period]');
    let page = 1;
    let busy = false;

    const load = async (reset) => {
        if (busy) {
            return;
        }
        busy = true;
        const next = reset ? 1 : page + 1;
        const params = new URLSearchParams({ page: String(next), action: action.value, period: period.value });
        skeleton.hidden = false;
        more.hidden = true;
        if (reset) {
            list.innerHTML = '';
            empty.hidden = true;
        }
        try {
            const { data } = await ajax('GET', `${feed.dataset.ktActivity}?${params}`);
            const fragment = document.createElement('template');
            fragment.innerHTML = data.html;
            // strana koja nastavlja isti dan ne ponavlja zaglavlje dana
            const firstDay = fragment.content.querySelector('.kt-tl-day');
            const lastDay = [...list.querySelectorAll('.kt-tl-day')].pop();
            if (firstDay && lastDay && firstDay.dataset.day === lastDay.dataset.day) {
                firstDay.remove();
            }
            list.append(fragment.content);
            page = next;
            more.hidden = !data.hasMore;
            empty.hidden = !(reset && data.empty);
        } catch (e) {
            toast(e.message, 'danger');
            more.hidden = false;
        } finally {
            skeleton.hidden = true;
            busy = false;
        }
    };

    more.addEventListener('click', () => load(false));
    action.addEventListener('change', () => load(true));
    // flatpickr opseg: menja vrednost kad su izabrana oba datuma ili kad se polje obriše
    period.addEventListener('change', () => {
        if (period.value === '' || period.value.match(/\d{4}-\d{2}-\d{2}/g)?.length === 2) {
            load(true);
        }
    });
});
