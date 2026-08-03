import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        uploadUrl: String,
    };

    async connect() {
        await this.loadQuill();

        if (!window.Quill) {
            console.error('Quill n’est pas chargé.');
            return;
        }

        this.textarea = this.element;

        if (!(this.textarea instanceof HTMLTextAreaElement)) {
            console.error('Le controller quill-editor doit être attaché à un textarea.');
            return;
        }

        this.textarea.classList.add('d-none');

        this.wrapper = document.createElement('div');

        this.toolbar = document.createElement('div');
        this.toolbar.className = 'bg-body-tertiary border rounded-top px-2 py-2';

        this.toolbar.innerHTML = `
            <span class="ql-formats">
                <select class="ql-header">
                    <option selected></option>
                    <option value="2"></option>
                    <option value="3"></option>
                </select>
            </span>

            <span class="ql-formats">
                <button type="button" class="ql-bold"></button>
                <button type="button" class="ql-italic"></button>
                <button type="button" class="ql-underline"></button>
                <button type="button" class="ql-strike"></button>
            </span>

            <span class="ql-formats">
                <button type="button" class="ql-list" value="ordered"></button>
                <button type="button" class="ql-list" value="bullet"></button>
            </span>

            <span class="ql-formats">
                <button type="button" class="ql-link"></button>
                <button type="button" class="ql-image"></button>
            </span>

            <span class="ql-formats">
                <button type="button" class="ql-clean"></button>
            </span>
        `;

        this.editor = document.createElement('div');
        this.editor.className = 'bg-body border border-top-0 rounded-bottom';
        this.editor.style.minHeight = '260px';

        this.wrapper.appendChild(this.toolbar);
        this.wrapper.appendChild(this.editor);

        this.textarea.parentNode.insertBefore(this.wrapper, this.textarea);

        this.quill = new window.Quill(this.editor, {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: this.toolbar,
                    handlers: {
                        image: () => this.selectImage(),
                    },
                },
            },
        });

        this.quill.root.innerHTML = this.textarea.value || '';

        this.quill.on('text-change', () => {
            this.syncTextarea();
        });

        const form = this.textarea.closest('form');

        if (form) {
            form.addEventListener('submit', () => {
                this.syncTextarea();
            });
        }
    }

    syncTextarea() {
        this.textarea.value = this.quill.root.innerHTML;
    }

    async loadQuill() {
        if (window.Quill) {
            return;
        }

        await this.loadCss('https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css');
        await this.loadScript('https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js');
    }

    loadScript(src) {
        return new Promise((resolve, reject) => {
            const existingScript = document.querySelector(`script[src="${src}"]`);

            if (existingScript) {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.onload = resolve;
            script.onerror = reject;

            document.head.appendChild(script);
        });
    }

    loadCss(href) {
        return new Promise((resolve) => {
            const existingLink = document.querySelector(`link[href="${href}"]`);

            if (existingLink) {
                resolve();
                return;
            }

            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.onload = resolve;

            document.head.appendChild(link);
        });
    }

    selectImage() {
        if (!this.uploadUrlValue) {
            const imageUrl = prompt('URL de l’image');

            if (imageUrl) {
                this.insertImage(imageUrl);
            }

            return;
        }

        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';

        input.addEventListener('change', async () => {
            const file = input.files?.[0];

            if (!file) {
                return;
            }

            await this.uploadImage(file);
        });

        input.click();
    }

    async uploadImage(file) {
        const formData = new FormData();
        formData.append('file', file);

        const response = await fetch(this.uploadUrlValue, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            alert('Impossible d’envoyer l’image.');
            return;
        }

        const data = await response.json();

        if (!data.url) {
            alert('Réponse invalide du serveur.');
            return;
        }

        this.insertImage(data.url);
    }

    insertImage(url) {
        const range = this.quill.getSelection(true);
        this.quill.insertEmbed(range.index, 'image', url);
        this.quill.setSelection(range.index + 1);
        this.syncTextarea();
    }
}