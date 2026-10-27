import $ from 'jquery';
import { ajax } from './ajax.js';
import { confirmDialog, toast } from './confirm.js';

// "Učitaj iz NBS" pored PIB-a na formi klijenta: popunjava polja podacima iz NBS-a (poziv ide preko našeg servera).
const $button = $('#nbs-fetch');

// ISO 7064 MOD 11,10, ista provera kao na serveru (App\Nbs\IdentifierChecksum)
export function isValidPib(pib) {
    if (!/^\d{9}$/.test(pib)) {
        return false;
    }
    let product = 10;
    for (let i = 0; i < 8; i += 1) {
        let sum = (Number(pib[i]) + product) % 10;
        if (sum === 0) {
            sum = 10;
        }
        product = (2 * sum) % 11;
    }
    return (11 - product) % 10 === Number(pib[8]);
}

const fields = ['name', 'shortName', 'maticniBroj', 'address', 'postalCode', 'city', 'phone', 'email', 'legalForm'];
// telefon i email iz NBS-a su često prazni ili zastareli, pa ručno uneti ostaju
const keepIfFilled = ['phone', 'email'];

const glow = ($field) => {
    $field.addClass('kt-flash');
    window.setTimeout(() => $field.removeClass('kt-flash'), 1000);
};

const renderAccounts = (accounts) => {
    const $box = $('#nbs-accounts');
    const $list = $box.find('ul').empty();
    (accounts || []).forEach((a) => {
        $('<li class="d-flex gap-2"></li>')
            .append($('<span class="kt-mono"></span>').text(a.account))
            .append($('<span class="text-muted"></span>').text(a.bank))
            .appendTo($list);
    });
    $box.toggleClass('d-none', !(accounts || []).length);
};

const fill = (data) => {
    fields.forEach((key) => {
        const $field = $(`#client_${key}`);
        if (!$field.length || !data[key] || (keepIfFilled.includes(key) && $field.val())) {
            return;
        }
        if ($field.val() !== data[key]) {
            const el = $field[0];
            if (el.tomselect) {
                // pravna forma je Tom Select: vrednost mora kroz njegov API
                el.tomselect.setValue(data[key], true);
                glow($(el.tomselect.control));
            } else {
                // native događaji, da IMask maska vidi novu vrednost
                el.value = data[key];
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
                glow($field);
            }
        }
    });
    renderAccounts(data.accounts);
};

if ($button.length) {
    $button.on('click', async () => {
        const $pib = $('#client_pib');
        const pib = String($pib.val() ?? '').trim();
        if (!isValidPib(pib)) {
            toast('PIB nije ispravan (9 cifara sa kontrolnom cifrom).', 'warning');
            $pib.trigger('focus');
            return;
        }

        $button.addClass('is-loading').prop('disabled', true);
        let response;
        try {
            response = await ajax('GET', `${$button.data('url')}?pib=${encodeURIComponent(pib)}&exclude_id=${$button.data('exclude')}`);
        } catch (error) {
            toast(error.message, 'danger');
            return;
        } finally {
            $button.removeClass('is-loading').prop('disabled', false);
        }

        const { data } = response;
        const overwritten = fields.filter((key) => {
            const value = String($(`#client_${key}`).val() ?? '').trim();
            return value !== '' && !keepIfFilled.includes(key) && data[key] && value !== data[key];
        });
        if (overwritten.length) {
            const { confirmed } = await confirmDialog({
                message: 'Prepisati unete podatke?',
                text: 'Neka polja već imaju vrednosti koje se razlikuju od podataka iz NBS-a. Telefon i email ostaju kakvi su.',
                type: 'warning',
                button: 'Da, prepiši',
            });
            if (!confirmed) {
                renderAccounts(data.accounts);
                return;
            }
        }

        fill(data);
        toast('Podaci su preuzeti iz NBS-a. Proveri ih pre čuvanja.');
    });
}
