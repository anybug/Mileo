(function () {
    if (window.__mileoReportAppInstalled) return;
    window.__mileoReportAppInstalled = true;

    document.addEventListener('DOMContentLoaded', () => {
        initGlobalApp();
        initReportFeatures();
        initReadonlyReportFeatures();
    });

    /* =========================================================
     * INIT
     * ========================================================= */

    function initGlobalApp() {
        initTooltips();
    }

    function initReportFeatures() {
        hideAndPresetDateFields();
        hideFavoritesField();

        waitForGoogleMaps(() => {
            initAutocomplete(document);
        });

        dateTypeRange();

        delegate(document, 'change', '#filters_Period_value', () => {
            document.getElementById('filters')?.submit();
        });

        delegate(document, 'change', '#filters_period_value', () => {
            document.getElementById('filters')?.submit();
        });

        delegate(document, 'click', '.field-collection-delete-button', () => {
            delay(() => {
                totalForReport();
            }, 100);
        });

        delegate(document, 'click', '.field-collection-add-button', () => {
            waitForGoogleMaps(() => {
                initAutocomplete(document);
            });
            dateTypeRange();

            delay(() => {
                totalForReport();
            }, 100);
        });

        delegate(document, 'change', '.report_start_date', () => {
            dateTypeRange();
        });

        delegate(document, 'change', '.report_end_date', () => {
            dateTypeRange();
        });

        delegate(document, 'change', '.lines_start, .lines_end', (e, target) => {
            const form = target.closest('form');
            const distance = form?.querySelector('.report_km');
            const dpt = form?.querySelector('.lines_start');
            const arv = form?.querySelector('.lines_end');

            delay(() => {
                calculDistance(null, distance, dpt, arv);
            }, 400);
        });

        delegate(document, 'change', '.report_lines_start, .report_lines_end', (e, target) => {
            const line = getLineContainer(target);
            const distance = line?.querySelector('.report_lines_km');
            const dpt = line?.querySelector('.report_lines_start');
            const arv = line?.querySelector('.report_lines_end');

            delay(() => {
                calculDistance(line, distance, dpt, arv);
            }, 400);
        });

        delegate(document, 'change', '.report_is_return', () => {
            calculTotalLineKm(null);
        });

        delegate(document, 'change', '.report_lines_is_return', (e, target) => {
            const line = getLineContainer(target);
            calculTotalLineKm(line);
        });

        delegate(document, 'change', '.report_scale', () => {
            requestForGeneratingAmount();
        });

        delegate(document, 'focusout', '.report_scale', () => {
            requestForGeneratingAmount();
        });

        delegate(document, 'change', '.report_lines_scale', (e, target) => {
            const line = getLineContainer(target);
            requestForGeneratingAmount(line);
        });

        delegate(document, 'change', '.report_vehicule', () => {
            requestForGeneratingAmount(null);
        });

        delegate(document, 'change', '.report_lines_vehicule', (e, target) => {
            const line = getLineContainer(target);
            requestForGeneratingAmount(line);
        });

        delegate(document, 'change', '.vehicule_type', async (e, target) => {
            await requestDependentChange(target, '.vehicule_power', url_vehicule_change_type);

            setTimeout(() => {
                qsa('.vehicule_power').forEach((el) => {
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }, 700);
        });

        delegate(document, 'change', '.vehicule_power', (e, target) => {
            requestDependentChange(target, '.vehicule_scale', url_vehicule_change_power);
        });

        delegate(document, 'click', '.popup-fav-start', (e, target) => {
            e.preventDefault();
            e.stopPropagation();

            const form = target.closest('form');
            const field = form?.querySelector('.lines_start');

            favoriteModal(target, url_popup_fav_start, field, null);
        });

        delegate(document, 'click', '.popup-fav-lines-start', (e, target) => {
            e.preventDefault();
            e.stopPropagation();

            const line = getLineContainer(target);
            const field = line?.querySelector('.report_lines_start');

            favoriteModal(target, url_popup_fav_lines_start, field, line);
        });

        delegate(document, 'click', '.popup-fav-end', (e, target) => {
            e.preventDefault();
            e.stopPropagation();

            const form = target.closest('form');
            const field = form?.querySelector('.lines_end');

            favoriteModal(target, url_popup_fav_end, field, null);
        });

        delegate(document, 'click', '.popup-fav-lines-end', (e, target) => {
            e.preventDefault();
            e.stopPropagation();

            const line = getLineContainer(target);
            const field = line?.querySelector('.report_lines_end');

            favoriteModal(target, url_popup_fav_lines_end, field, line);
        });

        delegate(document, 'change', '.form_scale', (e, target) => {
            const form = target.closest('form');
            const currentValue = target.value;
            const previousValue = target.dataset.previousValue ?? currentValue;

            const content = `
                <p><i class="fa-solid fa-triangle-exclamation"></i> Le changement de barème sera appliqué au rapport annuel ainsi qu'aux rapports provisionnels de l'année fiscale.</p>
                <p>Si vous souhaitez conserver une copie de vos rapports provisionnels de l'année, cliquez sur Annuler et téléchargez-les avant d'appliquer la modification de barème.</p>
                <button type="button" class="btn btn-secondary do_not_change_scale" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary confirm_change_scale ms-2">Confirmer</button>
            `;

            doModal('Veuillez confirmer cette action', content, null, {
                size: 'lg',
                scrollable: false,
                centered: true
            });

            const modalEl = document.getElementById('dynamicModal');
            if (!modalEl) return;

            const onClick = (evt) => {
                const confirmBtn = evt.target.closest('.confirm_change_scale');
                if (confirmBtn) {
                    confirmBtn.disabled = true;
                    if (!confirmBtn.querySelector('.spinner-border')) {
                        confirmBtn.insertAdjacentHTML(
                            'beforeend',
                            ' <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>'
                        );
                    }
                    form?.submit();
                    return;
                }

                const cancelBtn = evt.target.closest('.do_not_change_scale');
                if (cancelBtn) {
                    target.value = previousValue;
                }
            };

            modalEl.addEventListener('click', onClick, { once: true });
        });

        delegate(document, 'focus', '.form_scale', (e, target) => {
            target.dataset.previousValue = target.value;
        });
    }

    function initReadonlyReportFeatures() {
        if (!getReadonlyContainer()) return;

        bindReadonlyContainerEvents();
        bindReadonlyPanelEvents();
        bindReadonlyWindowMessageEvents();

        applyReadonlySearchFilter();
        updateReadonlyStats();
    }

    /* =========================================================
     * HELPERS DOM
     * ========================================================= */

    function qs(selector, root = document) {
        return root.querySelector(selector);
    }

    function qsa(selector, root = document) {
        return Array.from(root.querySelectorAll(selector));
    }

    function delegate(root, eventName, selector, handler) {
        root.addEventListener(eventName, (event) => {
            const target = event.target.closest(selector);
            if (!target || !root.contains(target)) {
                return;
            }
            handler(event, target);
        });
    }

    function parseHTML(html) {
        const parser = new DOMParser();
        return parser.parseFromString(html, 'text/html');
    }

    function setFieldValue(field, value) {
        if (!field) return;
        field.value = value;
        field.setAttribute('value', value);
    }

    function addLoading(el) {
        el?.classList.add('loading');
    }

    function removeLoading(el) {
        el?.classList.remove('loading');
    }

    function getLineContainer(el) {
        return (
            el.closest('[data-collection-item]') ||
            el.closest('.field-collection-item') ||
            el.closest('.ea-form-collection-items .field-collection-item') ||
            el.closest('.report-line-row') ||
            null
        );
    }

    function getFieldValue(field) {
        if (!field) {
            return '';
        }

        if (field.matches('select, input, textarea')) {
            return field.value ?? '';
        }

        const realField = field.querySelector('select, input, textarea');
        return realField ? (realField.value ?? '') : '';
    }

    function getInputValue(target) {
        if (!target) {
            return '';
        }

        if (target.matches('input, select, textarea')) {
            if ((target.type === 'radio' || target.type === 'checkbox')) {
                return target.checked ? target.value : '';
            }

            return target.value ?? '';
        }

        const checked = target.querySelector('input[type="radio"]:checked, input[type="checkbox"]:checked');
        if (checked) {
            return checked.value ?? '';
        }

        const input = target.querySelector('input, select, textarea');
        if (input) {
            return input.value ?? '';
        }

        return '';
    }

    function parseNumber(value) {
        if (value == null) {
            return 0;
        }

        const normalized = String(value)
            .replace(/\s/g, '')
            .replace(',', '.')
            .replace(/[^\d.\-]/g, '')
            .trim();

        const number = parseFloat(normalized);
        return Number.isFinite(number) ? number : 0;
    }

    function roundCurrency(value) {
        return Math.round((Number(value) || 0) * 100) / 100;
    }

    /* =========================================================
     * TOTAL FORM REPORT
     * ========================================================= */

    function totalForReport() {
        let total = 0;
        let totalKm = 0;

        const kmTotalInput = qs('form .km');
        const amountTotalInput = qs('form .total');

        const linesKm = qsa('form .report_lines_km_total');
        const linesAmount = qsa('form .report_lines_amount');

        linesKm.forEach((field) => {
            totalKm += parseInt(field.value || '0', 10);
        });

        linesAmount.forEach((field) => {
            total += roundCurrency(parseNumber(field.value));
        });

        total = parseFloat(roundCurrency(total)).toFixed(2);

        if (kmTotalInput) {
            kmTotalInput.value = String(totalKm);
        }

        if (amountTotalInput) {
            amountTotalInput.value = total;
        }

        const tripsCountEl = qs('.js-report-stat-lines');
        if (tripsCountEl) {
            tripsCountEl.textContent = String(linesKm.length);
        }

        const kmTotalEl = qs('.js-report-stat-km');
        if (kmTotalEl) {
            kmTotalEl.textContent = String(totalKm);
        }

        const amountTotalEl = qs('.js-report-stat-total');
        if (amountTotalEl) {
            amountTotalEl.textContent = total;
        }
    }

    /* =========================================================
     * TOOLTIPS
     * ========================================================= */

    function initTooltips(root = document) {
        qsa('[data-bs-toggle="tooltip"]', root).forEach((el) => {
            if (el.dataset.tooltipInitialized === '1') {
                return;
            }

            el.dataset.tooltipInitialized = '1';
            new bootstrap.Tooltip(el);
        });
    }

    /* =========================================================
     * INIT UI
     * ========================================================= */

    function hideAndPresetDateFields() {
        const month = qs('#Report_Year_month');
        const day = qs('#Report_Year_day');

        if (month) {
            month.style.display = 'none';
            month.value = '1';
        }

        if (day) {
            day.style.display = 'none';
            day.value = '1';
        }

        qsa('.report_start_date').forEach((el) => {
            const wrapper = el.parentElement?.parentElement?.parentElement;
            if (wrapper) {
                wrapper.style.display = 'none';
            }
        });

        qsa('.report_end_date').forEach((el) => {
            const wrapper = el.parentElement?.parentElement?.parentElement;
            if (wrapper) {
                wrapper.style.display = 'none';
            }
        });
    }

    function hideFavoritesField() {
        qsa('.report_favories').forEach((el) => {
            const wrapper = el.parentElement?.parentElement?.parentElement;
            if (wrapper) {
                wrapper.style.display = 'none';
            }
        });
    }

    /* =========================================================
     * DELAY
     * ========================================================= */

    const delay = (() => {
        let timer = 0;

        return (callback, ms) => {
            clearTimeout(timer);
            timer = setTimeout(callback, ms);
        };
    })();

    /* =========================================================
     * MODAL
     * ========================================================= */

    function doModal(heading, content, height = null, options = {}) {
        const existing = document.getElementById('dynamicModal');

        if (existing) {
            const existingInstance = bootstrap.Modal.getInstance(existing);
            existingInstance?.dispose();
            existing.remove();
        }

        const {
            size = 'lg',
            scrollable = false,
            centered = false,
            bodyClass = ''
        } = options;

        const dialogClasses = ['modal-dialog', `modal-${size}`];

        if (scrollable) {
            dialogClasses.push('modal-dialog-scrollable');
        }

        if (centered) {
            dialogClasses.push('modal-dialog-centered');
        }

        const bodyStyle = height ? `style="${height}"` : '';

        const html = `
            <div id="dynamicModal" class="modal fade" tabindex="-1" aria-labelledby="title-modal" aria-hidden="true">
                <div class="${dialogClasses.join(' ')}">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="title-modal">${heading}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body ${bodyClass}" ${bodyStyle}>
                            ${content}
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', html);

        const modalEl = document.getElementById('dynamicModal');
        const modal = new bootstrap.Modal(modalEl, {
            backdrop: true,
            keyboard: true,
            focus: true
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            modal.dispose();
            modalEl.remove();
        }, { once: true });

        modal.show();

        return modal;
    }

    /* =========================================================
     * GOOGLE MAPS
     * ========================================================= */

    function initAutocomplete(root = document) {
        if (!window.google || !google.maps || !google.maps.places) {
            return;
        }

        qsa('.autocomplete', root).forEach((input) => {
            if (input.dataset.autocompleteInitialized === '1') {
                return;
            }

            input.dataset.autocompleteInitialized = '1';

            new google.maps.places.Autocomplete(input, {
                componentRestrictions: { country: 'fr' }
            });
        });
    }

    function waitForGoogleMaps(callback, tries = 0) {
        if (!document.querySelector('.autocomplete')) {
            return;
        }

        if (window.google && google.maps && google.maps.places) {
            callback();
            return;
        }

        if (tries > 40) {
            console.warn('Google Maps API non disponible');
            return;
        }

        setTimeout(() => {
            waitForGoogleMaps(callback, tries + 1);
        }, 250);
    }

    /* =========================================================
     * FETCH HELPERS
     * ========================================================= */

    async function fetchText(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options
        });

        if (!response.ok) {
            throw response;
        }

        return response.text();
    }

    async function fetchJSON(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options
        });

        if (!response.ok) {
            throw response;
        }

        return response.json();
    }

    function toFormUrlEncoded(data) {
        return new URLSearchParams(data).toString();
    }

    /* =========================================================
     * READONLY REPORT
     * ========================================================= */

    function getReadonlyContainer() {
        return document.getElementById('report-lines-readonly');
    }

    function getReadonlyTripItems() {
        const container = getReadonlyContainer();
        if (!container) return [];
        return Array.from(container.querySelectorAll('.tm-trip-item[data-trip-item]'));
    }

    function getReadonlySearchInput() {
        return getReadonlyContainer()?.querySelector('.tm-trip-search-input') || null;
    }

    function getReadonlySearchResetButton() {
        return getReadonlyContainer()?.querySelector('.tm-trip-search-reset') || null;
    }

    function getReadonlyEmptyState() {
        return getReadonlyContainer()?.querySelector('.tm-trip-empty') || null;
    }

    function getReadonlyPanelElements() {
        const panelEl = document.getElementById('ea-sidepanel');
        const backdropEl = document.getElementById('ea-sidepanel-backdrop');

        if (!panelEl) {
            return { panelEl: null, backdropEl: null, iframe: null, closeBtn: null };
        }

        return {
            panelEl,
            backdropEl,
            iframe: panelEl.querySelector('[data-sidepanel-iframe]'),
            closeBtn: panelEl.querySelector('[data-close-sidepanel]')
        };
    }

    function updateReadonlyStats() {
        const allItems = getReadonlyTripItems();
        let totalKm = 0;

        allItems.forEach((item) => {
            totalKm += parseNumber(item.dataset.km ?? 0);
        });

        const linesEl = document.querySelector('.js-report-stat-lines');
        const kmEl = document.querySelector('.js-report-stat-km');

        if (linesEl) linesEl.textContent = String(allItems.length);
        if (kmEl) kmEl.textContent = String(Math.round(totalKm));
    }

    function applyReadonlySearchFilter() {
        const query = (getReadonlySearchInput()?.value || '').trim().toLowerCase();
        const resetBtn = getReadonlySearchResetButton();
        const emptyState = getReadonlyEmptyState();

        let visibleCount = 0;

        getReadonlyTripItems().forEach((item) => {
            const haystack = (item.dataset.search || '').toLowerCase();
            const matches = !query || haystack.includes(query);

            item.classList.toggle('d-none', !matches);

            if (matches) {
                visibleCount++;
            }
        });

        if (resetBtn) {
            resetBtn.classList.toggle('d-none', !query);
        }

        if (emptyState) {
            emptyState.classList.toggle('d-none', visibleCount > 0 || !query);
        }
    }

    function openReadonlySidepanel(url) {
        const { panelEl, backdropEl, iframe } = getReadonlyPanelElements();
        if (!panelEl || !iframe || !url) return;

        iframe.src = url;
        panelEl.classList.add('is-open');
        backdropEl?.classList.add('is-open');
        document.body.classList.add('tm-sidepanel-open');
        panelEl.setAttribute('aria-hidden', 'false');
    }

    function closeReadonlySidepanel() {
        const { panelEl, backdropEl, iframe } = getReadonlyPanelElements();
        if (!panelEl) return;

        panelEl.classList.remove('is-open');
        backdropEl?.classList.remove('is-open');
        document.body.classList.remove('tm-sidepanel-open');
        panelEl.setAttribute('aria-hidden', 'true');

        setTimeout(() => {
            if (iframe) {
                iframe.src = '';
            }
        }, 250);
    }

    async function refreshReportReadonly() {
        const container = getReadonlyContainer();
        const refreshUrl = container?.dataset.refreshUrl;

        if (!container || !refreshUrl) return false;

        const currentQuery = getReadonlySearchInput()?.value || '';

        try {
            setReadonlyStatsLoading(true);

            await new Promise((resolve) => requestAnimationFrame(resolve));
            await new Promise((resolve) => setTimeout(resolve, 80));

            const response = await fetch(refreshUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Impossible de rafraîchir le contenu');
            }

            const html = await response.text();
            container.innerHTML = html;

            const newSearchInput = getReadonlySearchInput();
            if (newSearchInput) {
                newSearchInput.value = currentQuery;
            }

            applyReadonlySearchFilter();
            updateReadonlyStats();

            initTooltips(container);
            waitForGoogleMaps(() => {
                initAutocomplete(container);
            });

            setReadonlyStatsLoading(false);
            return true;
        } catch (error) {
            console.error(error);
            setReadonlyStatsLoading(false);
            return false;
        }
    }

    async function refreshReadonlyOrReload() {
        const refreshed = await refreshReportReadonly();

        if (!refreshed) {
            window.location.reload();
        }
    }

    function bindReadonlyContainerEvents() {
        document.addEventListener('click', async function (e) {
            const container = getReadonlyContainer();
            if (!container) return;

            const clickedInsideContainer = e.target.closest('#report-lines-readonly');
            if (!clickedInsideContainer) return;

            const openBtn = e.target.closest('[data-open-sidepanel], [data-edit]');
            if (openBtn) {
                e.preventDefault();
                e.stopPropagation();
                openReadonlySidepanel(openBtn.dataset.url);
                return;
            }

            const resetBtn = e.target.closest('.tm-trip-search-reset');
            if (resetBtn) {
                e.preventDefault();

                const input = getReadonlySearchInput();
                if (input) {
                    input.value = '';
                    input.focus();
                }

                applyReadonlySearchFilter();
                return;
            }

            const deleteBtn = e.target.closest('[data-delete]');
            if (deleteBtn) {
                e.preventDefault();
                e.stopPropagation();

                if (!confirm('Confirmer la suppression ?')) {
                    return;
                }

                deleteBtn.disabled = true;

                try {
                    const response = await fetch(deleteBtn.dataset.url, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            _token: deleteBtn.dataset.token
                        })
                    });

                    if (!response.ok) {
                        const text = await response.text();
                        alert('Suppression impossible : ' + text);
                        deleteBtn.disabled = false;
                        return;
                    }

                    await refreshReadonlyOrReload();
                } catch (error) {
                    alert('Erreur réseau lors de la suppression');
                    deleteBtn.disabled = false;
                }

                return;
            }
        });

        document.addEventListener('input', function (e) {
            if (!e.target.closest('#report-lines-readonly .tm-trip-search-input')) {
                return;
            }

            applyReadonlySearchFilter();
        });
    }

    function bindReadonlyPanelEvents() {
        const { backdropEl, panelEl, closeBtn } = getReadonlyPanelElements();

        backdropEl?.addEventListener('click', function () {
            closeReadonlySidepanel();
        });

        closeBtn?.addEventListener('click', function () {
            closeReadonlySidepanel();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && panelEl?.classList.contains('is-open')) {
                closeReadonlySidepanel();
            }
        });
    }

    function bindReadonlyWindowMessageEvents() {
        window.addEventListener('message', async function (event) {
            const data = event.data || {};

            if (data.type === 'report-line-preview') {
                const { kmEl } = getReadonlyStatElements();

                if (kmEl) kmEl.classList.add('is-loading');

                requestAnimationFrame(() => {
                    if (kmEl && data.km !== undefined) {
                        kmEl.textContent = data.km;
                    }

                    if (kmEl) kmEl.classList.remove('is-loading');
                });

                return;
            }

            if (data.type === 'ea-modal-action' && data.action === 'save') {
                setReadonlyStatsLoading(true);

                await new Promise((resolve) => requestAnimationFrame(resolve));
                await new Promise((resolve) => setTimeout(resolve, 80));

                closeReadonlySidepanel();
                await refreshReadonlyOrReload();
            }
        });
    }

    function getReadonlyStatElements() {
        return {
            linesEl: document.querySelector('.js-report-stat-lines'),
            kmEl: document.querySelector('.js-report-stat-km'),
            totalEl: document.querySelector('.js-report-stat-total')
        };
    }

    function setReadonlyStatsLoading(loading) {
        const { linesEl, kmEl, totalEl } = getReadonlyStatElements();

        [linesEl, kmEl, totalEl].forEach((el) => {
            if (!el) return;
            el.classList.toggle('is-loading', loading);
        });
    }

    /* =========================================================
     * DISTANCE
     * ========================================================= */

    function calculDistance(line, distance, dpt, arv) {
        if (!distance || !dpt || !arv) {
            return;
        }

        if (dpt.value && arv.value) {
            const service = new google.maps.DistanceMatrixService();

            qsa('form .report_km_total').forEach((el) => {
                el.value = '';
                addLoading(el);
            });

            qsa('form .report_amount').forEach((el) => {
                el.value = '';
                addLoading(el);
            });

            if (line) {
                const kmTotal = line.querySelector('.report_lines_km_total');
                const amount = line.querySelector('.report_lines_amount');

                if (kmTotal) {
                    kmTotal.value = '';
                    addLoading(kmTotal);
                }

                if (amount) {
                    amount.value = '';
                    addLoading(amount);
                }
            }

            service.getDistanceMatrix(
                {
                    origins: [dpt.value],
                    destinations: [arv.value],
                    travelMode: google.maps.TravelMode.DRIVING,
                    unitSystem: google.maps.UnitSystem.METRIC,
                    avoidHighways: false,
                    avoidTolls: false
                },
                (response, status) => {
                    if (status !== google.maps.DistanceMatrixStatus.OK) {
                        alert('Error was: ' + status);
                        return;
                    }

                    for (let i = 0; i < response.originAddresses.length; i++) {
                        if (typeof response.rows[i] === 'undefined') {
                            continue;
                        }

                        const results = response.rows[i].elements;

                        for (let j = 0; j < results.length; j++) {
                            const element = results[j];

                            if (!element || typeof element.distance === 'undefined') {
                                continue;
                            }

                            const km = Math.round(element.distance.value / 1000);

                            if (distance.value !== String(km)) {
                                setFieldValue(distance, km);
                            }
                        }
                    }
                }
            );
        }

        delay(() => {
            calculTotalLineKm(line);
        }, 800);
    }

    function calculTotalLineKm(line) {
        let totalKm = 0;

        if (line == null) {
            const km = parseFloat(qs('form .report_km')?.value || '0');
            const isReturn = qs('form .report_is_return')?.checked;

            totalKm = isReturn ? km * 2 : km;

            const totalField = qs('form .report_km_total');
            removeLoading(totalField);

            if (totalField) {
                totalField.value = Math.round(totalKm);
            }
        } else {
            const km = parseFloat(line.querySelector('.report_lines_km')?.value || '0');
            const isReturn = line.querySelector('.report_lines_is_return')?.checked;

            totalKm = isReturn ? km * 2 : km;

            const totalField = line.querySelector('.report_lines_km_total');
            removeLoading(totalField);

            if (totalField) {
                totalField.value = Math.round(totalKm);
            }
        }

        requestForGeneratingAmount(line);
    }

    /* =========================================================
     * AMOUNT
     * ========================================================= */

    async function requestForGeneratingAmount(line = null) {
        const params = new URLSearchParams(window.location.search);

        let totalField;
        let kmField;
        let vehiculeField;
        let reportId = null;
        let reportLineId = null;

        if (line) {
            totalField = line.querySelector('.report_lines_amount');
            kmField = line.querySelector('.report_lines_km_total');
            vehiculeField = line.querySelector('.report_lines_vehicule');
            reportId = params.get('entityId');
        } else {
            totalField = document.querySelector('.report_amount');
            kmField = document.querySelector('form .report_km_total');
            vehiculeField = document.querySelector('.report_vehicule');
            reportLineId = params.get('entityId');
        }

        const kmValue = kmField ? kmField.value : '';
        const vehiculeValue = getFieldValue(vehiculeField);

        if (kmValue != 0 && vehiculeField) {
            const requestUrl = new URL(url_generateAmountAction, window.location.origin);

            if (reportId) {
                requestUrl.searchParams.set('report_id', reportId);
            }

            if (reportLineId) {
                requestUrl.searchParams.set('report_line_id', reportLineId);
            }

            requestUrl.searchParams.set('distance', kmValue);
            requestUrl.searchParams.set('vehicule', vehiculeValue);

            try {
                if (totalField) {
                    totalField.value = '';
                    totalField.classList.add('loading');
                }

                const response = await fetch(requestUrl.toString(), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error('Erreur lors du calcul du montant');
                }

                const data = await response.json();

                if (totalField) {
                    totalField.classList.remove('loading');

                    if (data.amount) {
                        totalField.value = parseFloat(data.amount).toFixed(2);
                    }
                }

                if (line) {
                    totalForReport();
                }
            } catch (error) {
                console.error(error);

                if (totalField) {
                    totalField.classList.remove('loading');
                }
            }

            return false;
        }
    }

    /* =========================================================
     * DEPENDENT FIELDS
     * ========================================================= */

    async function requestDependentChange(obj, classToChange, action) {
        const data = {};
        data[obj.name] = obj.value;
        data['Vehicule[type]'] = qs('.vehicule_type:checked')?.value || '';

        qsa(classToChange).forEach((el) => {
            el.value = '';
            addLoading(el);
        });

        try {
            const html = await fetchText(action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: toFormUrlEncoded(data)
            });

            replaceFieldParentFromHtml(classToChange, html);
        } catch (error) {
            try {
                const html = await error.text();
                if (html) {
                    replaceFieldParentFromHtml(classToChange, html);
                }
            } catch (fallbackError) {
                console.error(fallbackError);
            }
        }
    }

    function replaceFieldParentFromHtml(classToChange, html) {
        const doc = parseHTML(html);
        const newField = doc.querySelector(classToChange);
        const currentField = document.querySelector(classToChange);

        if (!newField || !currentField || !newField.parentElement || !currentField.parentElement) {
            return;
        }

        currentField.parentElement.replaceWith(newField.parentElement);

        initTooltips(document);
        waitForGoogleMaps(() => {
            initAutocomplete(document);
        });
    }

    /* =========================================================
     * DATES
     * ========================================================= */

    function dateTypeRange() {
        const travelDates = qsa('form .report_lines_travel_date');
        const startDate = qs('form .report_start_date');
        const endDate = qs('form .report_end_date');

        travelDates.forEach((el) => {
            if (startDate) {
                el.setAttribute('min', startDate.value);
            }
            if (endDate) {
                el.setAttribute('max', endDate.value);
            }
        });
    }

    /* =========================================================
     * FAVORITES
     * ========================================================= */

    function buildFavoriteModalContent(innerHtml) {
        return `
            <div class="favorite-modal">
                <div class="favorite-modal__toolbar mb-3">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input
                            type="search"
                            class="form-control favorite-modal-search"
                            placeholder="Rechercher une adresse favorite"
                            autocomplete="off"
                        >
                        <button type="button" class="btn btn-outline-secondary favorite-modal-search-reset d-none">
                            Réinitialiser
                        </button>
                    </div>
                    <div class="form-text mt-2 favorite-modal-count"></div>
                </div>

                <div class="favorite-modal-empty alert alert-light border d-none mb-0">
                    Aucune adresse ne correspond à votre recherche.
                </div>

                <div class="favorite-modal-list">
                    ${innerHtml}
                </div>
            </div>
        `;
    }

    function getFavoriteChoiceRows(modalEl) {
        return qsa('.favorite-modal-list .form-check', modalEl);
    }

    function getFavoriteSearchInput(modalEl) {
        return qs('.favorite-modal-search', modalEl);
    }

    function getFavoriteSearchReset(modalEl) {
        return qs('.favorite-modal-search-reset', modalEl);
    }

    function getFavoriteEmptyState(modalEl) {
        return qs('.favorite-modal-empty', modalEl);
    }

    function getFavoriteCountEl(modalEl) {
        return qs('.favorite-modal-count', modalEl);
    }

    function getFavoriteChoiceLabelText(row) {
        const label = row.querySelector('.form-check-label');
        const input = row.querySelector('.report_favories_choice');

        const labelText = label?.textContent?.trim() || '';
        const inputValue = input?.value?.trim() || '';

        return `${labelText} ${inputValue}`.trim().toLowerCase();
    }

    function applyFavoriteSearchFilter(modalEl) {
        const input = getFavoriteSearchInput(modalEl);
        const resetBtn = getFavoriteSearchReset(modalEl);
        const emptyState = getFavoriteEmptyState(modalEl);
        const countEl = getFavoriteCountEl(modalEl);
        const rows = getFavoriteChoiceRows(modalEl);

        const query = (input?.value || '').trim().toLowerCase();

        let visibleCount = 0;
        let totalCount = 0;

        rows.forEach((row) => {
            const choice = row.querySelector('.report_favories_choice');

            if (!choice || choice.disabled || !choice.value) {
                row.classList.add('d-none');
                return;
            }

            totalCount++;
            const haystack = getFavoriteChoiceLabelText(row);
            const matches = !query || haystack.includes(query);

            row.classList.toggle('d-none', !matches);

            if (matches) {
                visibleCount++;
            }
        });

        if (resetBtn) {
            resetBtn.classList.toggle('d-none', !query);
        }

        if (emptyState) {
            emptyState.classList.toggle('d-none', visibleCount > 0 || !query);
        }

        if (countEl) {
            if (!totalCount) {
                countEl.textContent = 'Aucune adresse favorite disponible.';
            } else if (!query) {
                countEl.textContent = `${totalCount} adresse${totalCount > 1 ? 's' : ''} disponible${totalCount > 1 ? 's' : ''}.`;
            } else {
                countEl.textContent = `${visibleCount} résultat${visibleCount > 1 ? 's' : ''} sur ${totalCount}.`;
            }
        }
    }

    function focusFavoriteSearch(modalEl) {
        const input = getFavoriteSearchInput(modalEl);
        if (!input) return;

        requestAnimationFrame(() => {
            input.focus();
            input.select();
        });
    }

    function resolveFavoriteContext(field, fallbackLine = null) {
        const form = field?.closest('form') || null;
        const line = fallbackLine || getLineContainer(field);

        if (line) {
            return {
                line,
                distance: line.querySelector('.report_lines_km'),
                dpt: line.querySelector('.report_lines_start'),
                arv: line.querySelector('.report_lines_end')
            };
        }

        return {
            line: null,
            distance: form?.querySelector('.report_km') || null,
            dpt: form?.querySelector('.lines_start') || null,
            arv: form?.querySelector('.lines_end') || null
        };
    }

    function setFavoriteValue(field, value) {
        if (!field) {
            return;
        }

        field.value = value;
        field.setAttribute('value', value);

        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function formatFavoriteChoices(modalEl) {
        const rows = getFavoriteChoiceRows(modalEl);

        rows.forEach((row) => {
            const input = row.querySelector('.report_favories_choice');
            const label = row.querySelector('.form-check-label');

            if (!input || !label) {
                return;
            }

            const address = (input.value || '').trim();
            if (!address) {
                row.classList.add('d-none');
                return;
            }

            let name = (label.textContent || '').trim();

            if (name.includes(address)) {
                name = name.replace(address, '').trim();
            }

            name = name.replace(/[:\-–—\s]+$/, '').trim();

            if (!name) {
                name = 'Adresse favorite';
            }

            label.textContent = '';

            const line = document.createElement('span');
            line.className = 'favorite-choice-line';

            const strong = document.createElement('strong');
            strong.className = 'favorite-choice-name';
            strong.textContent = name;

            const separator = document.createElement('span');
            separator.className = 'favorite-choice-separator';
            separator.textContent = ' — ';

            const addressSpan = document.createElement('span');
            addressSpan.className = 'favorite-choice-address';
            addressSpan.textContent = address;

            line.appendChild(strong);
            line.appendChild(separator);
            line.appendChild(addressSpan);

            label.appendChild(line);
        });
    }

    let favoriteModalOpening = false;
    let favoriteModalLastTriggerAt = 0;

    async function favoriteModal(btn, urlAjax, fieldToChange, fallbackLine = null) {
        const now = Date.now();

        if (favoriteModalOpening || (now - favoriteModalLastTriggerAt) < 250) {
            return;
        }

        favoriteModalLastTriggerAt = now;

        const existingModal = document.getElementById('dynamicModal');
        if (existingModal) {
            return;
        }

        if (!btn || btn.dataset.favoriteModalBusy === '1') {
            return;
        }

        favoriteModalOpening = true;
        btn.dataset.favoriteModalBusy = '1';

        try {
            btn.disabled = true;

            if (!btn.querySelector('.spinner-border')) {
                btn.insertAdjacentHTML(
                    'beforeend',
                    ' <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>'
                );
            }

            const html = await fetchText(urlAjax, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const doc = parseHTML(html);
            const favoritesBlock = doc.querySelector('.report_favories');

            if (!favoritesBlock) {
                return;
            }

            const modalContent = buildFavoriteModalContent(favoritesBlock.innerHTML);

            doModal(
                'Sélectionnez une de vos adresses',
                modalContent,
                null,
                {
                    size: 'lg',
                    scrollable: true,
                    centered: true,
                    bodyClass: 'favorite-address-modal-body'
                }
            );

            const modalEl = document.getElementById('dynamicModal');
            if (!modalEl) {
                return;
            }

            const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
            let valueApplied = false;

            formatFavoriteChoices(modalEl);
            applyFavoriteSearchFilter(modalEl);
            focusFavoriteSearch(modalEl);

            const applyFavorite = (value) => {
                if (valueApplied || !value || !fieldToChange) {
                    return;
                }

                valueApplied = true;

                setFavoriteValue(fieldToChange, value);

                const context = resolveFavoriteContext(fieldToChange, fallbackLine);

                modalEl.addEventListener('hidden.bs.modal', () => {
                    delay(() => {
                        calculDistance(
                            context.line,
                            context.distance,
                            context.dpt,
                            context.arv
                        );
                    }, 200);
                }, { once: true });

                modalInstance.hide();
            };

            modalEl.addEventListener('input', (e) => {
                if (e.target.closest('.favorite-modal-search')) {
                    applyFavoriteSearchFilter(modalEl);
                }
            });

            modalEl.addEventListener('click', (e) => {
                const resetBtn = e.target.closest('.favorite-modal-search-reset');
                if (resetBtn) {
                    e.preventDefault();
                    const input = getFavoriteSearchInput(modalEl);
                    if (input) {
                        input.value = '';
                        input.focus();
                    }
                    applyFavoriteSearchFilter(modalEl);
                    return;
                }

                const choice = e.target.closest('.report_favories_choice');
                if (choice) {
                    applyFavorite(choice.value);
                    return;
                }

                const label = e.target.closest('.form-check-label');
                if (label) {
                    const input = label.parentElement?.querySelector('.report_favories_choice');
                    if (input && !input.disabled && input.value) {
                        input.checked = true;
                        applyFavorite(input.value);
                    }
                }
            });

            modalEl.addEventListener('change', (e) => {
                const choice = e.target.closest('.report_favories_choice');
                if (choice && !choice.disabled && choice.value) {
                    applyFavorite(choice.value);
                }
            });

            modalEl.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') {
                    return;
                }

                const input = e.target.closest('.favorite-modal-search');
                if (!input) {
                    return;
                }

                e.preventDefault();

                const firstVisibleChoice = qsa('.favorite-modal-list .report_favories_choice', modalEl)
                    .find((choice) => !choice.closest('.form-check')?.classList.contains('d-none') && !!choice.value);

                if (firstVisibleChoice) {
                    firstVisibleChoice.checked = true;
                    applyFavorite(firstVisibleChoice.value);
                }
            });

            modalEl.addEventListener('hidden.bs.modal', () => {
                favoriteModalOpening = false;
                btn.dataset.favoriteModalBusy = '0';
            }, { once: true });

        } catch (error) {
            console.error('Erreur favoriteModal', error);
            favoriteModalOpening = false;
            btn.dataset.favoriteModalBusy = '0';
        } finally {
            btn.disabled = false;
            btn.querySelector('.spinner-border')?.remove();
        }
    }
})();