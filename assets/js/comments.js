import $ from 'jquery';
import { ajax, flash, withId } from './ajax.js';

const $page = $('#task-page');

if ($page.length) {
    const $comments = $('#comments');
    const urls = $page.data();

    $('#comment-add').on('submit', function (event) {
        event.preventDefault();
        const $form = $(this);
        const body = String($form.find('[name=body]').val() ?? '').trim();
        if (!body) {
            return;
        }
        ajax('POST', urls.commentUrl, { body })
            .then(({ data }) => {
                $comments.append(data.html);
                $('#comments-empty').addClass('d-none');
                $form.find('[name=body]').val('');
            })
            .catch((error) => flash($page, error.message));
    });

    $comments.on('click', '.comment-edit-start', function () {
        const $comment = $(this).closest('.task-comment');
        const $body = $comment.find('.comment-body');
        const text = $body.attr('data-raw');

        const $editor = $('<div class="comment-editor"></div>')
            .append($('<textarea class="form-control mb-2" rows="3"></textarea>').val(text))
            .append('<button type="button" class="btn btn-sm btn-primary me-2 comment-edit-save">Sačuvaj</button>')
            .append('<button type="button" class="btn btn-sm btn-light comment-edit-cancel">Otkaži</button>');

        $body.addClass('d-none').after($editor);
        $(this).addClass('d-none');
    });

    $comments.on('click', '.comment-edit-cancel', function () {
        const $comment = $(this).closest('.task-comment');
        $comment.find('.comment-editor').remove();
        $comment.find('.comment-body, .comment-edit-start').removeClass('d-none');
    });

    $comments.on('click', '.comment-edit-save', function () {
        const $comment = $(this).closest('.task-comment');
        const body = String($comment.find('textarea').val() ?? '').trim();
        ajax('POST', withId(urls.commentEditUrl, $comment.data('id')), { body })
            .then(({ data }) => $comment.replaceWith(data.html))
            .catch((error) => flash($page, error.message));
    });
}
