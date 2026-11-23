class Attachment {
    constructor(file, directory) {
        this.file = file;
        this.directory = directory;
        this.state = 'pending';
        this.id = null;
        this.href = null;
        this.name = null;
        this.percent = 0;
    }
    static traverse(transfer, directory) {
        return transferredFiles(transfer, directory);
    }
    static from(files) {
        const result = [];
        for (const file of files) {
            if (file instanceof File) {
                result.push(new Attachment(file));
            }
            else if (file instanceof Attachment) {
                result.push(file);
            }
            else {
                throw new Error('Unexpected type');
            }
        }
        return result;
    }
    get fullPath() {
        return this.directory ? `${this.directory}/${this.file.name}` : this.file.name;
    }
    isImage() {
        return (['image/gif', 'image/png', 'image/jpg', 'image/jpeg', 'image/svg+xml', 'image/webp'].indexOf(this.file.type) > -1);
    }
    isVideo() {
        return ['video/mp4', 'video/quicktime'].indexOf(this.file.type) > -1;
    }
    saving(percent) {
        if (this.state !== 'pending' && this.state !== 'saving') {
            throw new Error(`Unexpected transition from ${this.state} to saving`);
        }
        this.state = 'saving';
        this.percent = percent;
    }
    saved(attributes) {
        var _a, _b, _c;
        if (this.state !== 'pending' && this.state !== 'saving') {
            throw new Error(`Unexpected transition from ${this.state} to saved`);
        }
        this.state = 'saved';
        this.id = (_a = attributes === null || attributes === void 0 ? void 0 : attributes.id) !== null && _a !== void 0 ? _a : null;
        this.href = (_b = attributes === null || attributes === void 0 ? void 0 : attributes.href) !== null && _b !== void 0 ? _b : null;
        this.name = (_c = attributes === null || attributes === void 0 ? void 0 : attributes.name) !== null && _c !== void 0 ? _c : null;
    }
    isPending() {
        return this.state === 'pending';
    }
    isSaving() {
        return this.state === 'saving';
    }
    isSaved() {
        return this.state === 'saved';
    }
}
function transferredFiles(transfer, directory) {
    if (directory && isDirectory(transfer)) {
        return traverse('', roots(transfer));
    }
    return Promise.resolve(visible(Array.from(transfer.files || [])).map(f => new Attachment(f)));
}
function hidden(file) {
    return file.name.startsWith('.');
}
function visible(files) {
    return Array.from(files).filter(file => !hidden(file));
}
function getFile(entry) {
    return new Promise(function (resolve, reject) {
        entry.file(resolve, reject);
    });
}
function getEntries(entry) {
    return new Promise(function (resolve, reject) {
        const result = [];
        const reader = entry.createReader();
        const read = () => {
            reader.readEntries(entries => {
                if (entries.length > 0) {
                    result.push(...entries);
                    read();
                }
                else {
                    resolve(result);
                }
            }, reject);
        };
        read();
    });
}
async function traverse(path, entries) {
    const results = [];
    for (const entry of visible(entries)) {
        if (entry.isDirectory) {
            results.push(...(await traverse(entry.fullPath, await getEntries(entry))));
        }
        else {
            const file = await getFile(entry);
            results.push(new Attachment(file, path));
        }
    }
    return results;
}
function isDirectory(transfer) {
    return (transfer.items &&
        Array.from(transfer.items).some((item) => {
            const entry = item.webkitGetAsEntry && item.webkitGetAsEntry();
            return entry && entry.isDirectory;
        }));
}
function roots(transfer) {
    return Array.from(transfer.items)
        .map((item) => item.webkitGetAsEntry())
        .filter(entry => entry != null);
}

class FileAttachmentElement extends HTMLElement {
    connectedCallback() {
        this.addEventListener('dragenter', onDragenter);
        this.addEventListener('dragover', onDragenter);
        this.addEventListener('dragleave', onDragleave);
        this.addEventListener('drop', onDrop);
        this.addEventListener('paste', onPaste);
        this.addEventListener('change', onChange);
    }
    disconnectedCallback() {
        this.removeEventListener('dragenter', onDragenter);
        this.removeEventListener('dragover', onDragenter);
        this.removeEventListener('dragleave', onDragleave);
        this.removeEventListener('drop', onDrop);
        this.removeEventListener('paste', onPaste);
        this.removeEventListener('change', onChange);
    }
    get directory() {
        return this.hasAttribute('directory');
    }
    set directory(value) {
        if (value) {
            this.setAttribute('directory', '');
        }
        else {
            this.removeAttribute('directory');
        }
    }
    async attach(transferred) {
        const attachments = transferred instanceof DataTransfer
            ? await Attachment.traverse(transferred, this.directory)
            : Attachment.from(transferred);
        const accepted = this.dispatchEvent(new CustomEvent('file-attachment-accept', {
            bubbles: true,
            cancelable: true,
            detail: { attachments }
        }));
        if (accepted && attachments.length) {
            this.dispatchEvent(new CustomEvent('file-attachment-accepted', {
                bubbles: true,
                detail: { attachments }
            }));
        }
    }
}
function hasFile(transfer) {
    return Array.from(transfer.types).indexOf('Files') >= 0;
}
let dragging = null;
function onDragenter(event) {
    const target = event.currentTarget;
    if (dragging) {
        clearTimeout(dragging);
    }
    dragging = window.setTimeout(() => target.removeAttribute('hover'), 200);
    const transfer = event.dataTransfer;
    if (!transfer || !hasFile(transfer))
        return;
    transfer.dropEffect = 'copy';
    target.setAttribute('hover', '');
    event.preventDefault();
}
function onDragleave(event) {
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'none';
    }
    const target = event.currentTarget;
    target.removeAttribute('hover');
    event.stopPropagation();
    event.preventDefault();
}
function onDrop(event) {
    const container = event.currentTarget;
    if (!(container instanceof FileAttachmentElement))
        return;
    container.removeAttribute('hover');
    const transfer = event.dataTransfer;
    if (!transfer || !hasFile(transfer))
        return;
    container.attach(transfer);
    event.stopPropagation();
    event.preventDefault();
}
const images = /^image\/(gif|png|jpeg)$/;
function pastedFile(items) {
    for (const item of items) {
        if (item.kind === 'file' && images.test(item.type)) {
            return item.getAsFile();
        }
    }
    return null;
}
function onPaste(event) {
    if (!event.clipboardData)
        return;
    if (!event.clipboardData.items)
        return;
    const container = event.currentTarget;
    if (!(container instanceof FileAttachmentElement))
        return;
    const file = pastedFile(event.clipboardData.items);
    if (!file)
        return;
    const files = [file];
    container.attach(files);
    event.preventDefault();
}
function onChange(event) {
    const container = event.currentTarget;
    if (!(container instanceof FileAttachmentElement))
        return;
    const input = event.target;
    if (!(input instanceof HTMLInputElement))
        return;
    const id = container.getAttribute('input');
    if (id && input.id !== id)
        return;
    const files = input.files;
    if (!files || files.length === 0)
        return;
    container.attach(files);
    input.value = '';
}
if (!window.customElements.get('file-attachment')) {
    window.FileAttachmentElement = FileAttachmentElement;
    window.customElements.define('file-attachment', FileAttachmentElement);
}

export { Attachment, FileAttachmentElement as default };
