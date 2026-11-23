/*
 * Uploads the images an admin drops on a Content textarea of the Terms form, pastes into it or picks through the
 * "choose one" link below it, part of setono/sylius-terms-plugin.
 *
 * GitHub's <file-attachment> element around the textarea turns each of those into a file-attachment-accepted event. The
 * event bubbles to the document, where this module listens, so uploading works in every locale and after the Live form
 * re-renders. The element's data attributes come from templates/admin/terms/form/translations/content/upload.html.twig.
 *
 * Each file gets a placeholder at the cursor at once, ![Uploading <name>…](). When the upload succeeds, the placeholder
 * becomes ![<name without its extension>](<url>). When it fails, the placeholder is removed and an error that names the
 * file shows below the textarea. Every edit goes through execCommand('insertText'), as the Markdown toolbar's do, so
 * undo works and the Live form gets an input event for each one.
 */

/**
 * Per textarea id, the promise of its latest placeholder replacement. The placeholders of a textarea are replaced in
 * the order they were inserted, so two placeholders with the same text, such as of two pasted images both named
 * image.png, each get their own image
 */
const replacements = new Map();

document.addEventListener('file-attachment-accepted', (event) => {
    const element = event.target;
    if (!(element instanceof HTMLElement) || !element.matches('file-attachment.setono-terms-upload')) {
        return;
    }

    const textarea = element.querySelector('textarea');
    if (null === textarea) {
        return;
    }

    const files = event.detail.attachments.map((attachment) => attachment.file);
    const placeholders = files.map((file) => `![Uploading ${escapeAltText(file.name)}…]()`);

    element.querySelector('.setono-terms-upload-errors')?.replaceChildren();

    textarea.focus();
    edit(textarea, textarea.selectionStart, textarea.selectionEnd, placeholders.join('\n\n'));

    files.forEach((file, index) => {
        // The uploads run side by side
        const upload = uploadImage(element, file);
        const previous = replacements.get(textarea.id) ?? Promise.resolve();

        replacements.set(textarea.id, previous.then(() => upload).then(({ url, error }) => {
            if (undefined !== url) {
                replacePlaceholder(textarea.id, placeholders[index], `![${escapeAltText(withoutExtension(file.name))}](${url})`);

                return;
            }

            replacePlaceholder(textarea.id, placeholders[index], '');
            showError(textarea.id, error);
        }));
    });
});

/**
 * Resolves to the image's URL, or to the error to show, which names the file. It never rejects
 *
 * @return {Promise<{url: string}|{error: string}>}
 */
async function uploadImage(element, file) {
    const failed = { error: element.dataset.uploadFailed.replace('%name%', file.name) };

    const body = new FormData();
    body.append('image', file);
    body.append('_csrf_token', element.dataset.csrfToken);

    try {
        const response = await fetch(element.dataset.uploadUrl, {
            method: 'POST',
            body,
            headers: { Accept: 'application/json' },
        });

        // Whatever isn't the endpoint's JSON, such as the login page after the session expired, is a failure
        const data = await response.json().catch(() => ({}));

        if (response.ok && 'string' === typeof data.url) {
            return { url: data.url };
        }

        return 'string' === typeof data.error ? { error: data.error } : failed;
    } catch {
        return failed;
    }
}

/**
 * Replaces the first occurrence of the placeholder, if the admin hasn't removed it, and leaves the admin's cursor and
 * focus where they were. The textarea is looked up again, since a Live re-render may have replaced it
 */
function replacePlaceholder(textareaId, placeholder, replacement) {
    const textarea = document.getElementById(textareaId);
    if (!(textarea instanceof HTMLTextAreaElement)) {
        return;
    }

    const start = textarea.value.indexOf(placeholder);
    if (-1 === start) {
        return;
    }

    const end = start + placeholder.length;
    const focused = document.activeElement;
    const { selectionStart, selectionEnd } = textarea;

    textarea.focus({ preventScroll: true });
    if (!edit(textarea, start, end, replacement)) {
        return;
    }

    // The text after the placeholder moved, and a cursor inside the placeholder goes after the replacement
    const move = (offset) => {
        if (offset <= start) {
            return offset;
        }

        return offset >= end ? offset + replacement.length - placeholder.length : start + replacement.length;
    };
    textarea.setSelectionRange(move(selectionStart), move(selectionEnd));

    if (focused === textarea) {
        return;
    }

    if (focused instanceof HTMLElement && focused !== document.body) {
        focused.focus({ preventScroll: true });
    } else {
        textarea.blur();
    }
}

/**
 * Replaces the text from start to end. When the textarea has the focus, it goes through the browser's editing commands,
 * so undo works, and returns true. A hidden textarea, in a collapsed locale or behind the Preview tab, can't take the
 * focus, and one that had it keeps it until the browser next renders, while the editing commands no longer reach it.
 * So its value is changed directly, keeping its selection, and the input and change events the Live form listens to
 * are dispatched
 */
function edit(textarea, start, end, text) {
    if (document.activeElement === textarea && textarea.getClientRects().length > 0) {
        textarea.setSelectionRange(start, end);
        document.execCommand('insertText', false, text);

        return true;
    }

    textarea.setRangeText(text, start, end, 'preserve');
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.dispatchEvent(new Event('change', { bubbles: true }));

    return false;
}

function showError(textareaId, message) {
    const errors = document.getElementById(textareaId)?.closest('file-attachment')?.querySelector('.setono-terms-upload-errors');
    if (null === errors || undefined === errors) {
        return;
    }

    const error = document.createElement('div');
    error.className = 'invalid-feedback d-block';
    error.textContent = message;
    errors.append(error);
}

/**
 * The brackets and backslashes of a file name would end the alt text early
 */
function escapeAltText(text) {
    return text.replace(/[[\]\\]/g, '\\$&');
}

function withoutExtension(fileName) {
    return fileName.replace(/\.[^.]*$/, '');
}
