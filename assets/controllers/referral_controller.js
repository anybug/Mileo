import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'input',
        'button',
        'message'
    ];

    static values = {
        url: String
    };

    reset() {
        this.inputTarget.classList.remove(
            'is-valid',
            'is-invalid'
        );

        this.messageTarget.className = 'form-text';
        this.messageTarget.textContent =
            'Le code de parrainage vous permet, ainsi qu’à votre parrain, de bénéficier de 45 jours supplémentaires.';
    }

    async validate() {
        const code = this.inputTarget.value.trim();

        if (!code) {
            this.setError(
                'Veuillez saisir un code de parrainage.'
            );
            return;
        }

        this.buttonTarget.disabled = true;

        const originalContent = this.buttonTarget.innerHTML;

        this.buttonTarget.innerHTML = `
            <i class="fa-solid fa-spinner fa-spin me-1"></i>
            Vérification...
        `;

        try {
            const response = await fetch(
                this.urlValue,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        code: code,
                    }),
                }
            );

            const data = await response.json();

            if (!data.valid) {
                this.setError(data.message);
                return;
            }

            this.inputTarget.classList.remove('is-invalid');
            this.inputTarget.classList.add('is-valid');

            this.messageTarget.className =
                'form-text text-success';

            this.messageTarget.innerHTML = `
                <i class="fa-solid fa-circle-check me-1"></i>
                ${data.message}
            `;

        } catch (error) {
            this.setError(
                'Impossible de vérifier le code de parrainage.'
            );
        } finally {
            this.buttonTarget.disabled = false;
            this.buttonTarget.innerHTML = originalContent;
        }
    }

    setError(message) {
        this.inputTarget.classList.remove('is-valid');
        this.inputTarget.classList.add('is-invalid');

        this.messageTarget.className =
            'form-text text-danger';

        this.messageTarget.innerHTML = `
            <i class="fa-solid fa-circle-xmark me-1"></i>
            ${message}
        `;
    }
}