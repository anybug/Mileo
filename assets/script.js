function refreshAssistantCalculationWarnings() {
        const previewZone = document.getElementById('preview-zone');

        if (!previewZone) {
            return;
        }

        const rows = qsa('.tm-trip-item', previewZone);
        const warning = document.getElementById('calculation-warning');
        const confirmBtn = document.querySelector('.js-confirm-dup');

        let hasError = false;
        let selectableCount = 0;

        rows.forEach((line) => {
            const kmTotal = parseNumber(line.querySelector('.report_lines_km_total')?.value || 0);
            const amount = parseNumber(line.querySelector('.report_lines_amount')?.value || 0);

            const isCalculated = kmTotal > 0 && amount > 0;

            line.classList.toggle('table-warning', !isCalculated);
            line.classList.toggle('js-calculation-error', !isCalculated);
            line.dataset.calculationError = isCalculated ? '0' : '1';

            const checkbox = line.querySelector('.js-calendar-trip-selection');

            if (checkbox) {
                checkbox.disabled = !isCalculated;
                checkbox.classList.toggle('d-none', !isCalculated);

                if (!isCalculated) {
                    checkbox.checked = false;
                } else {
                    checkbox.title = '';
                    selectableCount++;
                }
            }

            const kmTotalCell = line.querySelector('.js-preview-km-total');
            const amountCell = line.querySelector('.js-preview-amount');

            if (!isCalculated) {
                hasError = true;

                if (kmTotalCell && kmTotal <= 0) {
                    kmTotalCell.innerHTML = '<span class="text-warning fw-bold">Non calculé</span>';
                }

                if (amountCell && amount <= 0) {
                    amountCell.innerHTML = '<span class="text-warning fw-bold">Non calculé</span>';
                }
            }
        });

        if (warning) {
            warning.classList.toggle('d-none', !hasError);
        }

        /*
        * On ne bloque plus le bouton parce qu'une ligne est orange.
        * On bloque seulement s'il n'existe aucune ligne sélectionnable.
        */
        if (confirmBtn && selectableCount === 0) {
            confirmBtn.disabled = true;
            confirmBtn.classList.add('opacity-50');
        }

        window.updateTripSelectionState?.();
    }