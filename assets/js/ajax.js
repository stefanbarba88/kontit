import $ from 'jquery';
import { toast } from './confirm.js';

/**
 * JSON poziv ka /ajax/... ruti. CSRF header dodaje $.ajaxSetup u app.js.
 * Rešava se sa `data` iz odgovora { success, data, message }, a odbija sa porukom greške.
 */
export function ajax(method, url, payload = {}) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url,
            method,
            contentType: 'application/json',
            data: method === 'GET' || method === 'DELETE' ? undefined : JSON.stringify(payload),
            dataType: 'json',
        })
            .done((response) => (response.success ? resolve(response) : reject(new Error(response.message))))
            .fail((xhr) => reject(new Error(xhr.responseJSON?.message || 'Greška u komunikaciji sa serverom.')));
    });
}

/**
 * Zamenjuje 0 u putanji (…/0/… ili …/0) stvarnim ID-jem.
 */
export function withId(url, id) {
    return url.replace(/\/0(?=\/|$)/, `/${id}`);
}

/**
 * Bootstrap alert na vrhu kontejnera, koji sam nestaje.
 */
export function flash($container, message, type = 'danger') {
    toast(message, type);
}

export function flashAlert($container, message, type = 'danger') {
    const $alert = $('<div class="alert alert-dismissible fade show" role="alert"></div>')
        .addClass(`alert-${type}`)
        .text(message)
        .append('<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zatvori"></button>');
    $container.prepend($alert);
    setTimeout(() => $alert.remove(), 6000);
}
