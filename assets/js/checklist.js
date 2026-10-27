import $ from 'jquery';
import Sortable from 'sortablejs';
import { ajax, flash, withId } from './ajax.js';
import { confirmDialog } from './confirm.js';

const $page = $('#task-page');

if ($page.length) {
    const $list = $('#checklist');
    const urls = $page.data();

    const refresh = (data) => {
        $('#checklist-progress').text(`${data.done}/${data.total}`);
        $('#checklist-empty').toggleClass('d-none', data.total > 0);
        // izmena stavke može da promeni status zadatka (otvaranje, prelaz u rad), pa se stranica osvežava
        if (data.status !== $page.attr('data-status')) {
            window.location.reload();
        }
    };

    const fail = (error, $item, revert) => {
        if (revert) {
            revert();
        }
        flash($page, error.message);
    };

    $list.on('change', '.checklist-toggle', async function () {
        const $item = $(this).closest('.checklist-item');
        const done = this.checked;
        // odštikliravanje na zadatku koji je na proveri ili završen ponovo ga otvara
        if (!done && ['NA_PROVERI', 'ZAVRSENO'].includes($page.attr('data-status'))) {
            const { confirmed } = await confirmDialog({
                message: 'Odštiklirati stavku?',
                text: 'Zadatak je već ' + ($page.attr('data-status') === 'ZAVRSENO' ? 'završen' : 'poslat na proveru') + '. Odštikliravanje ga ponovo otvara.',
                type: 'warning',
                button: 'Da, odštikliraj',
            });
            if (!confirmed) {
                this.checked = true;
                return;
            }
        }
        ajax('POST', withId(urls.toggleUrl, $item.data('id')), { done })
            .then(({ data }) => {
                $item.replaceWith(data.html);
                refresh(data);
            })
            .catch((error) => fail(error, $item, () => (this.checked = !done)));
    });

    $list.on('change', '.checklist-assign', function () {
        const $item = $(this).closest('.checklist-item');
        ajax('POST', withId(urls.assignUrl, $item.data('id')), { userId: $(this).val() || null })
            .then(({ data }) => $item.replaceWith(data.html))
            .catch((error) => fail(error));
    });

    $list.on('click', '.checklist-delete', async function () {
        const $item = $(this).closest('.checklist-item');
        const { confirmed } = await confirmDialog({ message: 'Obrisati stavku?', type: 'danger', button: 'Da, obriši' });
        if (!confirmed) {
            return;
        }
        ajax('DELETE', withId(urls.deleteUrl, $item.data('id')))
            .then(({ data }) => {
                $item.remove();
                refresh(data);
            })
            .catch((error) => fail(error));
    });

    $('#checklist-add').on('submit', function (event) {
        event.preventDefault();
        const $form = $(this);
        const text = $.trim($form.find('[name=text]').val());
        if (!text) {
            return;
        }
        ajax('POST', urls.checklistUrl, { text, assignedTo: $form.find('[name=assignedTo]').val() || null })
            .then(({ data }) => {
                $list.append(data.html);
                $form.find('[name=text]').val('').trigger('focus');
                refresh(data);
            })
            .catch((error) => fail(error));
    });

    if ($('#checklist-add').length) {
        Sortable.create($list[0], {
            handle: '.checklist-handle',
            animation: 150,
            onEnd: () => {
                const order = $list.children('.checklist-item').map((_, el) => $(el).data('id')).get();
                ajax('POST', urls.reorderUrl, { order }).catch((error) => fail(error));
            },
        });
    }
}
