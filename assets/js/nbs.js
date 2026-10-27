import $ from 'jquery';
import { ajax } from './ajax.js';
import { toast } from './confirm.js';

// "NBS" dugme pored PIB-a na formi klijenta: popunjava polja podacima iz NBS-a.
const $button = $('#nbs-fetch');

if ($button.length) {
    const fields = {
        name: 'name',
        shortName: 'shortName',
        maticniBroj: 'maticniBroj',
        address: 'address',
        postalCode: 'postalCode',
        city: 'city',
        phone: 'phone',
        email: 'email',
        legalForm: 'legalForm',
    };

    $button.on('click', function () {
        const pib = $.trim($('#client_pib').val());
        $button.prop('disabled', true);
        ajax('GET', `${$button.data('url')}?pib=${encodeURIComponent(pib)}&exclude_id=${$button.data('exclude')}`)
            .then(({ data }) => {
                Object.entries(fields).forEach(([key, field]) => {
                    const $field = $(`#client_${field}`);
                    // telefon i email se ne prepisuju ako su već uneti
                    if (data[key] && (!['phone', 'email'].includes(key) || !$field.val())) {
                        $field.val(data[key]);
                    }
                });
                toast('Podaci su preuzeti iz NBS-a. Proverite ih pre čuvanja.');
            })
            .catch((error) => toast(error.message, 'danger'))
            .finally(() => $button.prop('disabled', false));
    });
}
