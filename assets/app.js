import './stimulus_bootstrap.js';
import './styles/app.css';

// Bootstrap bundle i JS jezgro iz template-a (assets/vendor/template). Bundle postavlja window.bootstrap,
// a template app.js ga koristi u DOMContentLoaded handlerima.
import './vendor/template/js/bootstrap.bundle.min.js';
import $ from 'jquery';
import './vendor/template/js/app.js';

window.$ = window.jQuery = $;

// CSRF token za AJAX pozive (header X-CSRF-Token), token dolazi iz <meta name="csrf-token">
$.ajaxSetup({
    beforeSend(xhr) {
        const token = $('meta[name="csrf-token"]').attr('content');
        if (token) {
            xhr.setRequestHeader('X-CSRF-Token', token);
        }
    },
});

// Moduli po funkciji (svaki sam proverava da li je njegov deo stranice prisutan)
import './js/checklist.js';
import './js/comments.js';
import './js/notifications.js';
import './js/dashboard.js';
import './js/confirm.js';
import './js/nbs.js';
import './js/theme.js';
import './js/countup.js';
