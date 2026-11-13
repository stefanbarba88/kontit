// Kalendar (SPEC 8n): FullCalendar v6 standardni paket (MIT, window.FullCalendar), latinični srpski, nedelja od ponedeljka, tokeni iz DESIGN.md.
// Događaji AJAX-om za vidljivi opseg; prevlačenje zadatka menja rok (SweetAlert, upozorenje za neradni dan);
// klik na prazan dan nudi novi zadatak ili zahtev za odsustvo; klik na događaj otvara popover sa detaljima.
import { Popover } from 'bootstrap';
import { ajax } from './ajax.js';
import { confirmDialog, toast } from './confirm.js';
import { info, remember, cache } from './non-working.js';

const el = document.getElementById('kt-calendar');
if (el && window.FullCalendar) {
    const year = new Date().getFullYear();
    Promise.all([year - 1, year, year + 1].map(remember)).then(() => {
        const { Calendar } = window.FullCalendar;
        const filters = document.querySelector('[data-kt-cal-filters]');
        const phone = window.matchMedia('(max-width: 575.98px)').matches;
        const escape = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        let popover = null;

        const closePopover = () => {
            popover?.dispose();
            popover = null;
        };

        const params = () => {
            const p = new URLSearchParams();
            p.set('scope', filters.querySelector('input[name="scope"]:checked')?.value || 'mine');
            p.set('types', [...filters.querySelectorAll('input[name="types"]:checked')].map((i) => i.value).join(',') || 'none');
            ['client', 'user'].forEach((n) => {
                const v = filters.querySelector(`select[name="${n}"]`)?.value;
                if (v) {
                    p.set(n, v);
                }
            });
            return p;
        };

        // latinični srpski (paket ima ćirilični "sr")
        const srLatn = {
            code: 'sr-latn',
            week: { dow: 1, doy: 7 },
            buttonText: { prev: 'Prethodni', next: 'Sledeći', today: 'Danas', year: 'Godina', month: 'Mesec', week: 'Nedelja', day: 'Dan', list: 'Lista' },
            weekText: 'Ned',
            allDayText: 'Ceo dan',
            moreLinkText: (n) => `+ još ${n}`,
            noEventsText: 'Nema događaja za prikaz',
        };

        const calendar = new Calendar(el, {
            locale: srLatn,
            firstDay: 1,
            height: 'auto',
            initialView: phone ? 'listWeek' : 'dayGridMonth',
            headerToolbar: phone ? { left: 'prev,next', center: 'title', right: 'today' } : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
            footerToolbar: phone ? { center: 'listWeek,dayGridMonth' } : false,
            buttonText: { listWeek: 'Lista', dayGridMonth: 'Mesec', timeGridWeek: 'Nedelja', timeGridDay: 'Dan' },
            dayMaxEvents: 4,
            editable: true,
            eventStartEditable: true,
            eventDurationEditable: false,
            navLinks: true,
            nowIndicator: true,
            allDaySlot: true,
            slotMinTime: '07:00:00',
            slotMaxTime: '19:00:00',
            events: (fetchInfo, success, failure) => {
                const p = params();
                p.set('start', fetchInfo.startStr.slice(0, 10));
                p.set('end', fetchInfo.endStr.slice(0, 10));
                fetch(`${el.dataset.eventsUrl}?${p}`, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
                    .then((r) => (r.ok ? r.json() : Promise.reject(new Error('Događaji nisu učitani.'))))
                    .then(success)
                    .catch((e) => { toast(e.message, 'danger'); failure(e); });
            },
            // neradni dani u nedelji su prigušeni (pravilo iz Podešavanja); praznici dolaze kao pozadinski događaji
            datesSet: (arg) => {
                [arg.start.getFullYear(), arg.end.getFullYear()].filter((y) => !(y in cache)).forEach(remember);
            },
            dayCellClassNames: (arg) => {
                const day = info(arg.date, {});
                return day && day.type === 'WEEKLY' ? ['kt-fc-weekend'] : [];
            },
            eventContent: (arg) => {
                const p = arg.event.extendedProps;
                if (p.kind === 'holiday') {
                    return { html: `<span class="kt-fc-holiday-name">${escape(p.name)}</span>` };
                }
                if (p.kind === 'absence') {
                    return { html: `<span class="kt-fc-initials">${escape(p.initials)}</span><span class="kt-fc-title">${escape(arg.event.title)}</span>` };
                }
                const urgent = p.urgent ? '<span class="kt-fc-urgent" title="Hitno">!</span>' : '';
                return { html: `${urgent}<span class="kt-fc-title">${escape(arg.event.title)}</span>` };
            },
            eventClick: (arg) => {
                arg.jsEvent.preventDefault();
                closePopover();
                const e = arg.event;
                const p = e.extendedProps;
                if (p.kind === 'holiday') {
                    return;
                }
                if (p.kind === 'todo') {
                    popover = new Popover(arg.el, { title: escape(e.title), content: `<div class="kt-text-sm"><div>Podsetnik${p.time ? ` · ${escape(p.time)}` : ''}</div><a href="${escape(p.todoUrl)}" class="btn btn-primary btn-sm mt-2">Otvori Podsetnik</a></div>`, html: true, trigger: 'manual', placement: 'auto', customClass: 'kt-fc-popover', sanitize: false });
                    popover.show();

                    return;
                }
                const body = p.kind === 'task'
                    ? `<div class="kt-text-sm"><div><strong>${escape(p.client)}</strong></div><div>Status: ${escape(p.status)} · ${escape(p.priority)}</div>${p.assignees ? `<div>Učesnici: ${escape(p.assignees)}</div>` : ''}<a href="${escape(e.url)}" class="btn btn-primary btn-sm mt-2">Otvori zadatak</a></div>`
                    : `<div class="kt-text-sm"><div>${escape(p.summary)}</div><div>Status: ${escape(p.status)}</div></div>`;
                popover = new Popover(arg.el, { title: escape(e.title), content: body, html: true, trigger: 'manual', placement: 'auto', customClass: 'kt-fc-popover', sanitize: false });
                popover.show();
            },
            dateClick: (arg) => {
                closePopover();
                const date = iso(arg.date);
                const off = info(arg.date, cache[arg.date.getFullYear()] || {});
                popover = new Popover(arg.dayEl, {
                    title: arg.date.toLocaleDateString('sr-Latn-RS', { weekday: 'long', day: 'numeric', month: 'long' }),
                    content: `${off ? `<div class="kt-text-xs text-muted mb-2">Neradni dan: ${escape(off.name)}</div>` : ''}<div class="d-grid gap-1"><a class="btn btn-sm btn-primary" href="${el.dataset.taskNewUrl}?due=${date}${el.dataset.returnTo ? `&returnTo=${encodeURIComponent(el.dataset.returnTo)}` : ''}">Novi zadatak za ovaj dan</a><a class="btn btn-sm btn-outline-secondary" href="${el.dataset.absenceNewUrl}?from=${date}">Zahtev za odsustvo od ovog dana</a></div>`,
                    html: true,
                    trigger: 'manual',
                    placement: 'auto',
                    customClass: 'kt-fc-popover',
                    sanitize: false,
                });
                popover.show();
            },
            eventDrop: async (arg) => {
                closePopover();
                const e = arg.event;
                const date = iso(e.start);
                const off = info(e.start, cache[e.start.getFullYear()] || {});
                const { confirmed } = await confirmDialog({
                    message: 'Promeniti rok zadatka?',
                    text: `„${e.title}“: novi rok ${e.start.toLocaleDateString('sr-Latn-RS')}.${off ? ` Pažnja: to je neradni dan (${off.name}).` : ''}`,
                    type: off ? 'warning' : 'info',
                    button: 'Da, promeni rok',
                });
                if (!confirmed) {
                    arg.revert();
                    return;
                }
                try {
                    const { message } = await ajax('POST', e.extendedProps.moveUrl, { date });
                    toast(message);
                } catch (err) {
                    arg.revert();
                    toast(err.message, 'danger');
                }
            },
        });
        calendar.render();

        filters.addEventListener('change', () => calendar.refetchEvents());
        document.addEventListener('click', (ev) => {
            if (popover && !ev.target.closest('.popover') && !ev.target.closest('.fc-event') && !ev.target.closest('.fc-daygrid-day')) {
                closePopover();
            }
        });
        // tema: FullCalendar koristi CSS promenljive, pa je dovoljno ponovo iscrtati
        document.addEventListener('kt:themechange', () => calendar.render());

        // telefon: prevlačenje levo/desno za prethodni ili sledeći period
        let startX = null;
        el.addEventListener('touchstart', (e) => { startX = e.touches[0].clientX; }, { passive: true });
        el.addEventListener('touchend', (e) => {
            if (startX === null) {
                return;
            }
            const dx = e.changedTouches[0].clientX - startX;
            if (Math.abs(dx) > 70) {
                if (dx < 0) {
                    calendar.next();
                } else {
                    calendar.prev();
                }
            }
            startX = null;
        });
    });
}
