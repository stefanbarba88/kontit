import $ from 'jquery';
import Swal from 'sweetalert2';

/**
 * Jedan globalni mehanizam potvrde (SPEC 8a). Radi preko data atributa:
 *   data-confirm="Pitanje?"            (obavezno)
 *   data-confirm-text="Objašnjenje"
 *   data-confirm-type="info|warning|danger"
 *   data-confirm-button="Da, ..."
 *   data-confirm-reason="required"     traži razlog, šalje se kao polje `reason`
 *   data-confirm-type-name="Naziv"     korisnik mora da ukuca naziv
 * Potvrda je samo UI sloj: bez JS-a forma ide običnim POST-om, a server proverava Voter i CSRF.
 */
const buttons = {
    info: 'btn kt-btn-confirm',
    warning: 'btn kt-btn-confirm',
    danger: 'btn kt-btn-danger',
};

const icons = { info: 'info', warning: 'warning', danger: 'warning' };

const swal = Swal.mixin({
    buttonsStyling: false,
    reverseButtons: true,
    focusCancel: true,
    customClass: { cancelButton: 'btn', confirmButton: '', popup: 'kt-swal text-start' },
});

const toaster = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    showCloseButton: true,
    closeButtonAriaLabel: 'Zatvori',
    timerProgressBar: true,
    customClass: { popup: 'kt-toast' },
    // prelazak mišem zaustavlja odbrojavanje, da duža poruka može da se pročita
    didOpen: (popup) => {
        popup.addEventListener('mouseenter', Swal.stopTimer);
        popup.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

/**
 * Toast gore desno, ispod gornje trake. Uspeh traje 3 s, greška i upozorenje 6 s.
 * type: success | danger | error | warning | info. Vraća Promise koji se rešava kad se toast zatvori.
 */
export function toast(message, type = 'success') {
    const icon = { success: 'success', danger: 'error', error: 'error', warning: 'warning', info: 'info' }[type] || 'info';

    return toaster.fire({ icon, title: message, timer: 'success' === icon ? 3000 : 6000 });
}

/**
 * Toast sa dugmetom "Poništi" (5 s). onUndo se poziva samo ako korisnik klikne dugme.
 */
export function undoToast(message, onUndo, seconds = 5) {
    return toaster
        .fire({
            icon: 'success',
            title: message,
            showConfirmButton: true,
            confirmButtonText: 'Poništi',
            timer: seconds * 1000,
            customClass: { popup: 'kt-toast', confirmButton: 'btn btn-sm btn-light kt-toast-undo' },
        })
        .then((result) => {
            if (result.isConfirmed) {
                return onUndo();
            }

            return null;
        });
}

// Flash poruke posle akcije (base.html.twig) prikazuju se kao toast, jedna za drugom
const flashes = document.querySelector('script[data-kt-flashes]');
if (flashes) {
    JSON.parse(flashes.textContent).reduce((queue, [type, message]) => queue.then(() => toast(message, type)), Promise.resolve());
}

/**
 * Prikazuje dijalog. Rešava se sa { confirmed, reason }.
 */
export function confirmDialog(options) {
    const type = buttons[options.type] ? options.type : 'warning';
    const typeName = options.typeName || '';
    const needsReason = options.reason === 'required';
    const html = [];

    if (options.text) {
        html.push(`<div class="mb-2"></div>`);
    }
    if (typeName) {
        html.push('<div class="mb-1 text-muted">Za potvrdu ukucaj: <strong class="swal-type-name"></strong></div><input class="form-control swal-type-input" autocomplete="off">');
    }
    if (needsReason || 'optional' === options.reason) {
        html.push(`<textarea class="form-control swal-reason mt-2" rows="3" maxlength="255" placeholder="${needsReason ? 'Razlog (obavezno)' : 'Napomena (neobavezno)'}"></textarea>`);
    }

    return swal
        .fire({
            title: options.message,
            text: html.length ? undefined : options.text,
            icon: icons[type],
            showCancelButton: true,
            confirmButtonText: options.button || 'Potvrdi',
            cancelButtonText: 'Odustani',
            customClass: { confirmButton: buttons[type], cancelButton: 'btn', popup: 'kt-swal text-start' },
            html: html.length ? `<p class="mb-2 text-muted">${$('<div>').text(options.text || '').html()}</p>${html.join('')}` : undefined,
            didOpen: (popup) => {
                $(popup).find('.swal-type-name').text(typeName);
            },
            preConfirm: () => {
                const popup = Swal.getPopup();
                if (typeName && String($(popup).find('.swal-type-input').val() ?? '').trim() !== typeName) {
                    Swal.showValidationMessage('Uneti naziv se ne poklapa.');
                    return false;
                }
                const reason = String($(popup).find('.swal-reason').val() ?? '').trim();
                if (needsReason && !reason) {
                    Swal.showValidationMessage('Razlog je obavezan.');
                    return false;
                }
                return { reason };
            },
        })
        .then((result) => ({ confirmed: result.isConfirmed, reason: result.value?.reason || '' }));
}

/**
 * Opcije dijaloga iz data atributa elementa.
 */
export function optionsFrom(el) {
    const $el = $(el);

    return {
        message: $el.data('confirm'),
        text: $el.data('confirmText'),
        type: $el.data('confirmType'),
        button: $el.data('confirmButton'),
        reason: $el.data('confirmReason'),
        typeName: $el.data('confirmTypeName'),
    };
}

const submitConfirmed = (form, submitter, reason) => {
    const $form = $(form);
    if (reason) {
        $form.find('input[name=reason][data-confirm-added]').remove();
        $form.find('textarea[name=reason], input[name=reason]:not([data-confirm-added])').val(reason);
        if (!$form.find('[name=reason]').length) {
            $('<input type="hidden" name="reason" data-confirm-added>').val(reason).appendTo($form);
        }
    }
    $form.data('confirmed', true);
    if (submitter && form.requestSubmit) {
        form.requestSubmit(submitter);
    } else {
        form.submit();
    }
};

// Forma koja traži potvrdu samo ako se promeni neko od polja: data-confirm-changed="CSS selektor polja".
// data-confirm-option-warning: pita samo ako izabrana <option> ima data-warning, a taj tekst je objašnjenje.
$(function () {
    $('form[data-confirm-changed]').each(function () {
        $(this).find($(this).data('confirmChanged')).each(function () {
            $(this).data('initial', $(this).val());
        });
    });
});

const changedConfirm = (form) => {
    const $form = $(form);
    const $fields = $form.find($form.data('confirmChanged')).filter(function () {
        return $(this).data('initial') !== $(this).val();
    });
    if (!$fields.length) {
        return null;
    }
    let options = optionsFrom(form);
    // polje može da ima svoj tekst (data-confirm-field-text) i neobaveznu napomenu (data-confirm-reason="optional"); više izmenjenih polja = jedno pitanje
    const texts = [...new Set($fields.map(function () { return $(this).data('confirmFieldText') || options.text; }).get())];
    options = { ...options, text: texts.join(' '), message: $fields.length > 1 ? 'Sačuvati promene?' : $fields.first().data('confirmFieldMessage') || options.message };
    const optionalNote = $fields.filter('[data-confirm-reason]').first().data('confirmReason');
    if (optionalNote) {
        options.reason = optionalNote;
    }
    const $selected = $fields.first().is('select') ? $fields.first().find('option:selected') : $();
    const warning = $selected.data('warning');
    if ($form.is('[data-confirm-option-warning]') && !warning) {
        return null;
    }

    return warning ? { ...options, text: warning } : options;
};

$(document).on('submit', 'form[data-confirm-changed]', function (event) {
    if ($(this).data('confirmed')) {
        return;
    }
    const options = changedConfirm(this);
    if (!options) {
        return;
    }
    event.preventDefault();
    const form = this;
    confirmDialog(options).then(({ confirmed, reason }) => {
        if (confirmed) {
            submitConfirmed(form, null, reason);
        }
    });
});

// Dugme/link unutar forme (data-confirm na dugmetu) ili cela forma (data-confirm na formi)
$(document).on('click', 'button[data-confirm][type=submit], button[data-confirm]:not([type]), input[type=submit][data-confirm]', function (event) {
    const form = this.form;
    if (!form || $(form).data('confirmed')) {
        return;
    }
    event.preventDefault();
    const button = this;
    confirmDialog(optionsFrom(button)).then(({ confirmed, reason }) => {
        if (confirmed) {
            submitConfirmed(form, button, reason);
        }
    });
});

$(document).on('submit', 'form[data-confirm]', function (event) {
    if ($(this).data('confirmed')) {
        return;
    }
    event.preventDefault();
    const form = this;
    confirmDialog(optionsFrom(form)).then(({ confirmed, reason }) => {
        if (confirmed) {
            submitConfirmed(form, null, reason);
        }
    });
});

// Link sa potvrdom (GET navigacija)
$(document).on('click', 'a[data-confirm]', function (event) {
    event.preventDefault();
    const href = this.href;
    confirmDialog(optionsFrom(this)).then(({ confirmed }) => {
        if (confirmed) {
            window.location.href = href;
        }
    });
});

// dostupno stranicama (npr. /_design) bez uvoza modula
window.ktToast = toast;
