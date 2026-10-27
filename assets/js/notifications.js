import $ from 'jquery';
import { ajax } from './ajax.js';

const POLL_INTERVAL = 60000;

const $bell = $('#notification-bell');

if ($bell.length) {
    const $menu = $('#notification-menu');
    const $count = $bell.find('.notification-count');

    const render = ({ count, html }) => {
        $menu.html(html);
        $count.text(count).toggleClass('d-none', count === 0);
    };

    const refresh = () => ajax('GET', $bell.data('url')).then(({ data }) => render(data)).catch(() => {
        // poll ne sme da smeta korisniku: ako server ne odgovori, pokušava se opet na sledeći interval
    });

    $menu.on('click', '.notification-read-all', () => {
        ajax('POST', $bell.data('readAllUrl')).then(({ data }) => render(data)).catch(() => {});
    });

    // bez osvežavanja dok je tab sakriven; čim se vrati, odmah se uzimaju nove
    setInterval(() => {
        if (!document.hidden) {
            refresh();
        }
    }, POLL_INTERVAL);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refresh();
        }
    });
    $bell[0].addEventListener('show.bs.dropdown', refresh);
}
