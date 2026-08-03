import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['content'];

    static values = {
        url: String,
        year: Number,
    };

    connect() {
        console.log('Pagination AJAX des véhicules connectée');
    }

    async changePage(event) {
        event.preventDefault();
        event.stopPropagation();

        const button = event.currentTarget;
        const page = Number.parseInt(button.dataset.page, 10);

        if (button.disabled || !Number.isInteger(page) || page < 1) {
            return;
        }

        const ajaxUrl = new URL(this.urlValue, window.location.origin);

        ajaxUrl.searchParams.set('yearSelected', String(this.yearValue));
        ajaxUrl.searchParams.set('vehiculePage', String(page));

        this.contentTarget.setAttribute('aria-busy', 'true');
        this.contentTarget.classList.add('opacity-50');

        try {
            const response = await fetch(ajaxUrl.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Erreur HTTP ${response.status}`);
            }

            this.contentTarget.innerHTML = await response.text();

            const browserUrl = new URL(window.location.href);
            browserUrl.searchParams.set('vehiculePage', String(page));

            window.history.replaceState({}, '', browserUrl.toString());
        } catch (error) {
            console.error(
                'Erreur pendant le chargement AJAX du graphique :',
                error
            );
        } finally {
            this.contentTarget.removeAttribute('aria-busy');
            this.contentTarget.classList.remove('opacity-50');
        }
    }
}