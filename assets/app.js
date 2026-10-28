import './stimulus_bootstrap.js';

// Bootstrap 5.3 (CSS + JS), pa naš izgled preko tokena (DESIGN.md). Vendor CSS se ne menja.
import 'bootstrap/dist/css/bootstrap.min.css';
import 'tom-select/dist/css/tom-select.bootstrap5.min.css';
import 'flatpickr/dist/flatpickr.min.css';
import './styles/app.css';
import * as bootstrap from 'bootstrap';
import $ from 'jquery';

window.bootstrap = bootstrap;
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
import './js/layout.js';
import './js/forms.js';
import './js/theme.js';
import './js/countup.js';
import './js/mobile.js';
import './js/activity.js';
