(function () {
    function qs(selector, root = document) {
        if (!root) {
            return null;
        }

        return root.querySelector(selector);
    }

    function inputBySuffix(root, suffix) {
        if (!root) {
            return null;
        }

        return qs('[name$="[' + suffix + ']"]', root);
    }

    function getWrapper(target) {
        if (target) {
            return target.closest('[data-calendar-url-validator], #calendar-url-field-wrapper');
        }

        return qs('[data-calendar-url-validator], #calendar-url-field-wrapper');
    }

    function getElements(wrapper) {
        return {
            wrapper,
            validationUrl: wrapper?.dataset.calendarValidationUrl || '',

            calendarUrlInput: inputBySuffix(wrapper, 'calendarUrl'),
            usernameInput: inputBySuffix(wrapper, 'calendarUsername'),
            passwordInput: inputBySuffix(wrapper, 'plainCalendarPassword'),

            message: qs('[data-calendar-validation-message], #calendar-url-validation-message', wrapper),

            urlButton: qs('[data-validate-calendar-url], #validate-calendar-url-button', wrapper),
            editUrlButton: qs('[data-edit-calendar-url], #edit-calendar-url-button', wrapper),
            urlValidIcon: qs('[data-calendar-url-valid-icon], #calendar-url-valid-icon', wrapper),

            authButton: qs('[data-validate-calendar-auth], #validate-calendar-auth-button', wrapper),
            authValidIcon: qs('[data-calendar-auth-valid-icon], #calendar-auth-valid-icon', wrapper),
            authCollapse: qs('[data-calendar-auth-collapse], #calendar-auth-collapse', wrapper),
            authRequiredMessage: qs('[data-calendar-auth-required-message], #calendar-auth-required-message', wrapper),
            authRequiredTitle: qs('[data-calendar-auth-required-title]', wrapper),
            authRequiredText: qs('[data-calendar-auth-required-text]', wrapper),
            authToggleButton: qs('[data-toggle-calendar-auth], #toggle-calendar-auth-button', wrapper),
            caldavFields: qs('[data-caldav-fields], #caldav-fields-wrapper', wrapper),

            dependentFields: qs('[data-calendar-dependent-fields], #calendar-dependent-fields-wrapper'),

            connectionCard: qs('[data-calendar-connection-card]'),
            connectionTitle: qs('[data-calendar-connection-title]'),
            connectionText: qs('[data-calendar-connection-text]'),
            connectionIconWrapper: qs('[data-calendar-connection-icon-wrapper]'),
            connectionIcon: qs('[data-calendar-connection-icon]'),

            connectionButton: qs('[data-calendar-connection-button]'),
            connectionButtonIcon: qs('[data-calendar-connection-button-icon]'),
            connectionButtonLabel: qs('[data-calendar-connection-button-label]'),
            connectionDisableButton: qs('[data-calendar-disable-button]'),

            sidepanel: qs('[data-calendar-sidepanel], #calendar-sidepanel'),
            sidepanelBackdrop: qs('[data-calendar-sidepanel-backdrop], #calendar-sidepanel-backdrop')
        };
    }

    function setMessage(elements, type, text) {
        if (!elements.message) {
            return;
        }

        elements.message.className = 'mt-2 small';

        if (type) {
            elements.message.classList.add('text-' + type);
        }

        elements.message.textContent = text || '';
    }

    function show(element, displayClass = 'd-block') {
        if (!element) {
            return;
        }

        element.classList.remove('d-none');

        if (displayClass) {
            element.classList.add(displayClass);
        }
    }

    function hide(element) {
        if (!element) {
            return;
        }

        element.classList.add('d-none');
        element.classList.remove('d-block', 'd-inline-block', 'd-flex');
    }

    function setReadonly(input, readonly) {
        if (!input) {
            return;
        }

        input.readOnly = readonly;
        input.classList.toggle('bg-body-secondary', readonly);
    }

    function setConnectionStatus(elements, connected) {
        elements.connectionCard?.classList.toggle('bg-light', !connected);
        elements.connectionCard?.classList.toggle('bg-success-subtle', connected);
        elements.connectionCard?.classList.toggle('border', connected);
        elements.connectionCard?.classList.toggle('border-success', connected);

        elements.connectionIconWrapper?.classList.toggle('text-primary', !connected);
        elements.connectionIconWrapper?.classList.toggle('text-success', connected);

        if (elements.connectionIcon) {
            elements.connectionIcon.className = connected
                ? 'fa-solid fa-circle-check'
                : 'fa-solid fa-calendar-days';
        }

        if (elements.connectionTitle) {
            elements.connectionTitle.textContent = connected
                ? 'Calendrier connecté'
                : 'Connexion au calendrier';
        }

        if (elements.connectionText) {
            elements.connectionText.classList.toggle('text-muted', !connected);
            elements.connectionText.classList.toggle('text-success', connected);
        }

        if (elements.connectionButton) {
            elements.connectionButton.classList.toggle('btn-secondary', !connected);
            elements.connectionButton.classList.toggle('btn-success', connected);
        }

        if (elements.connectionButtonIcon) {
            elements.connectionButtonIcon.className = connected
                ? 'fa-solid fa-pen me-1'
                : 'fa-solid fa-link me-1';
        }

        if (elements.connectionButtonLabel) {
            elements.connectionButtonLabel.textContent = connected
                ? 'Modifier le calendrier'
                : 'Connecter le calendrier';
        }

        if (elements.connectionDisableButton) {
            if(connected)
            {
               show(elements.connectionDisableButton);
            }else{
                hide(elements.connectionDisableButton);
            }
        }
    }

    function openSidepanel(elements) {
        elements.sidepanel?.classList.add('is-open');
        elements.sidepanel?.setAttribute('aria-hidden', 'false');
        elements.sidepanelBackdrop?.classList.add('is-open');
    }

    function closeSidepanel(elements) {
        elements.sidepanel?.classList.remove('is-open');
        elements.sidepanel?.setAttribute('aria-hidden', 'true');
        elements.sidepanelBackdrop?.classList.remove('is-open');
    }

    function openAuth(elements) {
        show(elements.authCollapse);

        if (elements.authToggleButton) {
            elements.authToggleButton.innerHTML =
                '<i class="fa-solid fa-lock-open me-1"></i> Masquer les identifiants du calendrier';
        }
    }

    function closeAuth(elements) {
        hide(elements.authCollapse);

        if (elements.authToggleButton) {
            elements.authToggleButton.innerHTML =
                '<i class="fa-solid fa-lock me-1"></i> Afficher les identifiants du calendrier';
        }
    }

    function setAuthWarning(elements, title, text) {
        if (elements.authRequiredTitle) {
            elements.authRequiredTitle.textContent = title;
        }

        if (elements.authRequiredText) {
            elements.authRequiredText.textContent = text;
        }

        show(elements.authRequiredMessage);
    }

    function setCredentialsRequired(elements, required) {
        if (elements.caldavFields) {
            elements.caldavFields.dataset.authRequired = required ? '1' : '0';
        }

        [elements.usernameInput, elements.passwordInput].forEach(function (input) {
            if (!input) {
                return;
            }

            const label = elements.wrapper.querySelector('label[for="' + input.id + '"]');

            input.required = false;

            if (required) {
                input.dataset.calendarAuthRequired = '1';
                input.setAttribute('aria-required', 'true');
                label?.classList.add('required');
            } else {
                input.dataset.calendarAuthRequired = '0';
                input.removeAttribute('aria-required');
                label?.classList.remove('required');
            }
        });
    }

    function setDependentFieldsVisible(elements, visible) {
        if (!elements.dependentFields) {
            return;
        }

    
        if(visible)
        {
            show(elements.dependentFields);
        }else{
            hide(elements.dependentFields);
        }
        

        elements.dependentFields
            .querySelectorAll('input, select, textarea')
            .forEach(function (field) {
                if (!field.dataset.originalRequired) {
                    field.dataset.originalRequired = field.required ? '1' : '0';
                }

                field.required = visible && field.dataset.originalRequired === '1';
            });
    }

    function resetCalendar(elements) {
        elements.wrapper.dataset.calendarUrlValidated = '0';
        elements.wrapper.dataset.calendarAuthValidated = '0';

        hide(elements.urlValidIcon);
        hide(elements.authValidIcon);
        hide(elements.editUrlButton);
        show(elements.urlButton, 'd-inline-block');

        hide(elements.authButton);
        hide(elements.authRequiredMessage);
        closeAuth(elements);

        setReadonly(elements.calendarUrlInput, false);
        setReadonly(elements.usernameInput, false);
        setReadonly(elements.passwordInput, false);

        setCredentialsRequired(elements, false);
        setDependentFieldsVisible(elements, false);
        setConnectionStatus(elements, false);
    }

    function markUrlValidated(elements) {
        elements.wrapper.dataset.calendarUrlValidated = '1';

        show(elements.urlValidIcon, 'd-inline-block');
        hide(elements.urlButton);
        show(elements.editUrlButton, 'd-inline-block');

        setReadonly(elements.calendarUrlInput, true);
    }

    function markFullyConnected(elements, reloadPage = false) {
        elements.wrapper.dataset.calendarUrlValidated = '1';
        elements.wrapper.dataset.calendarAuthValidated = '1';

        show(elements.urlValidIcon, 'd-inline-block');
        show(elements.authValidIcon, 'd-inline-block');

        hide(elements.urlButton);
        show(elements.editUrlButton, 'd-inline-block');
        hide(elements.authButton);
        hide(elements.authRequiredMessage);

        setReadonly(elements.calendarUrlInput, true);
        setReadonly(elements.usernameInput, true);
        setReadonly(elements.passwordInput, true);

        setDependentFieldsVisible(elements, true);
        setConnectionStatus(elements, true);
        closeSidepanel(elements);

        if (reloadPage) {
            window.location.reload();
        }
    }

    function getFormValue(input) {
        return input ? input.value.trim() : '';
    }

    function hasCalendarChanged(elements) {
        const wrapper = elements.wrapper;

        if (wrapper.dataset.calendarEditMode !== '1') {
            return true;
        }

        return (
            getFormValue(elements.calendarUrlInput) !== (wrapper.dataset.originalCalendarUrl || '')
            || getFormValue(elements.usernameInput) !== (wrapper.dataset.originalCalendarUsername || '')
            || getFormValue(elements.passwordInput) !== (wrapper.dataset.originalCalendarPassword || '')
        );
    }

    function cancelCalendarEditMode(elements) {
        const wrapper = elements.wrapper;

        if (!wrapper || wrapper.dataset.calendarEditMode !== '1') {
            return;
        }

        if (elements.calendarUrlInput) {
            elements.calendarUrlInput.value = wrapper.dataset.originalCalendarUrl || '';
        }

        if (elements.usernameInput) {
            elements.usernameInput.value = wrapper.dataset.originalCalendarUsername || '';
        }

        if (elements.passwordInput) {
            elements.passwordInput.value = wrapper.dataset.originalCalendarPassword || '';
        }

        wrapper.dataset.calendarEditMode = '0';
        wrapper.dataset.calendarUrlValidated = '1';
        wrapper.dataset.calendarAuthValidated = '1';

        setReadonly(elements.calendarUrlInput, true);
        setReadonly(elements.usernameInput, true);
        setReadonly(elements.passwordInput, true);

        show(elements.urlValidIcon, 'inline-block');
        show(elements.authValidIcon, 'inline-block');

        hide(elements.urlButton);
        show(elements.editUrlButton, 'inline-block');

        hide(elements.authButton);
        hide(elements.authRequiredMessage);
        closeAuth(elements);

        setCredentialsRequired(elements, false);
        setDependentFieldsVisible(elements, true);
        setConnectionStatus(elements, true);

        setMessage(elements, null, '');
    }

    async function callValidation(elements, withCredentials) {
        const formData = new FormData();

        formData.append('calendarUrl', getFormValue(elements.calendarUrlInput));
        formData.append('withCredentials', withCredentials ? '1' : '0');
        formData.append('saveCalendar', '1');

        formData.append(
            'calendarUsername',
            withCredentials ? getFormValue(elements.usernameInput) : ''
        );

        formData.append(
            'plainCalendarPassword',
            withCredentials ? getFormValue(elements.passwordInput) : ''
        );

        const response = await fetch(elements.validationUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        let result;

        try {
            result = await response.json();
        } catch (e) {
            throw new Error('Réponse invalide du serveur.');
        }

        return { response, result };
    }

    async function validateUrl(button) {
        const wrapper = getWrapper(button);
        const elements = getElements(wrapper);

        setMessage(elements, null, '');
        resetCalendar(elements);

        if (!elements.validationUrl) {
            setMessage(elements, 'danger', 'URL de validation calendrier manquante.');
            return;
        }

        if (!getFormValue(elements.calendarUrlInput)) {
            setMessage(elements, 'danger', "Veuillez renseigner l’adresse du calendrier.");
            return;
        }

        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = 'Validation en cours...';

        try {
            const { response, result } = await callValidation(elements, false);

            if (result.auth_required) {
                markUrlValidated(elements);

                elements.wrapper.dataset.calendarAuthValidated = '0';

                setReadonly(elements.usernameInput, false);
                setReadonly(elements.passwordInput, false);

                setCredentialsRequired(elements, true);
                openAuth(elements);

                show(elements.authButton, 'inline-block');

                setAuthWarning(
                    elements,
                    'Authentification requise.',
                    'Ce calendrier est protégé par une authentification, merci de saisir vos identifiants ci-dessous.'
                );

                setMessage(elements, null, '');
                return;
            }

            if (!response.ok || !result.valid) {
                setMessage(
                    elements,
                    'danger',
                    result.message || 'URL de calendrier invalide.'
                );
                return;
            }

            setCredentialsRequired(elements, false);
            markFullyConnected(
                elements,
                elements.wrapper.dataset.calendarReloadAfterSave === '1'
            );

            setMessage(elements, 'success', '');
        } catch (error) {
            setMessage(
                elements,
                'danger',
                error.message || 'Impossible de valider l’URL du calendrier.'
            );
        } finally {
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    }

    async function validateAuth(button) {
        const wrapper = getWrapper(button);
        const elements = getElements(wrapper);

        setMessage(elements, null, '');

        if (!getFormValue(elements.usernameInput) || !getFormValue(elements.passwordInput)) {
            openAuth(elements);
            setCredentialsRequired(elements, true);

            setAuthWarning(
                elements,
                'Authentification requise.',
                'Veuillez renseigner les identifiants d’accès à votre calendrier.'
            );

            if (!getFormValue(elements.usernameInput)) {
                elements.usernameInput?.reportValidity();
            } else {
                elements.passwordInput?.reportValidity();
            }

            return;
        }

        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = 'Validation des identifiants...';

        try {
            const { response, result } = await callValidation(elements, true);

            if (!response.ok || !result.valid) {
                openAuth(elements);

                setAuthWarning(
                    elements,
                    result.message || 'Identifiant ou mot de passe calendrier incorrect.',
                    'Merci de vérifier les informations saisies.'
                );

                return;
            }

            setReadonly(elements.usernameInput, true);
            setReadonly(elements.passwordInput, true);

            setCredentialsRequired(elements, true);
            markFullyConnected(
                elements,
                elements.wrapper.dataset.calendarReloadAfterSave === '1'
            );

            setMessage(elements, 'success', '');
        } catch (error) {
            setMessage(
                elements,
                'danger',
                error.message || 'Impossible de valider les identifiants du calendrier.'
            );
        } finally {
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    }

    function canSubmitCalendar(form) {
        const wrapper = qs('[data-calendar-url-validator], #calendar-url-field-wrapper', form);

        if (!wrapper) {
            return true;
        }

        const elements = getElements(wrapper);
        const calendarUrl = getFormValue(elements.calendarUrlInput);

        if (!calendarUrl) {
            return true;
        }

        const urlValidated = elements.wrapper.dataset.calendarUrlValidated === '1';

        if (!urlValidated) {
            setMessage(elements, 'danger', 'Veuillez valider l’URL du calendrier avant de continuer.');
            openSidepanel(elements);
            elements.urlButton?.focus();
            return false;
        }

        const authRequired = elements.caldavFields?.dataset.authRequired === '1';
        const authValidated = elements.wrapper.dataset.calendarAuthValidated === '1';

        if (authRequired && !authValidated) {
            openSidepanel(elements);
            openAuth(elements);

            show(elements.authButton, 'inline-block');

            setAuthWarning(
                elements,
                'Veuillez valider les identifiants du calendrier.',
                'Cliquez sur “Valider les identifiants” avant de continuer.'
            );

            elements.authButton?.focus();
            return false;
        }

        return true;
    }

    document.addEventListener('click', function (event) {
        const disableCalendarButton = event.target.closest('[data-disable-calendar-submit]');

        if (disableCalendarButton) {
            event.preventDefault();
            event.stopPropagation();

            const url = disableCalendarButton.dataset.disableCalendarUrl;
            const token = disableCalendarButton.dataset.disableCalendarToken;

            if (!url || !token) {
                alert('Impossible de désactiver le calendrier : paramètres manquants.');
                return;
            }

            const originalHtml = disableCalendarButton.innerHTML;

            disableCalendarButton.disabled = true;
            disableCalendarButton.innerHTML = 'Désactivation...';

            const formData = new FormData();
            formData.append('_token', token);

            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Impossible de désactiver le calendrier.');
                }

                if (disableCalendarButton.dataset.disableCalendarReload === '1') {
                    window.location.reload();
                    return;
                }

                const wrapper = getWrapper();

                if (wrapper) {
                    const elements = getElements(wrapper);

                    elements.wrapper.dataset.calendarUrlValidated = '0';
                    elements.wrapper.dataset.calendarAuthValidated = '0';
                    elements.wrapper.dataset.calendarEditMode = '0';

                    if (elements.calendarUrlInput) {
                        elements.calendarUrlInput.value = '';
                    }

                    if (elements.usernameInput) {
                        elements.usernameInput.value = '';
                    }

                    if (elements.passwordInput) {
                        elements.passwordInput.value = '';
                    }

                    resetCalendar(elements);
                    setMessage(elements, 'success', 'Le calendrier a été désactivé.');
                }

                const modalElement = disableCalendarButton.closest('.modal');

                if (modalElement && window.bootstrap?.Modal) {
                    const modal = bootstrap.Modal.getInstance(modalElement)
                        || new bootstrap.Modal(modalElement);

                    modal.hide();
                }

                disableCalendarButton.disabled = false;
                disableCalendarButton.innerHTML = originalHtml;
            })
            .catch(function (error) {
                disableCalendarButton.disabled = false;
                disableCalendarButton.innerHTML = originalHtml;
                alert(error.message || 'Erreur pendant la désactivation du calendrier.');
            });

            return;
        }
        const openButton = event.target.closest('[data-open-calendar-sidepanel]');

        if (openButton) {
            const sidepanelId = openButton.dataset.sidepanelId;
            const backdropId = openButton.dataset.backdropId;

            const sidepanel = sidepanelId
                ? document.getElementById(sidepanelId)
                : document.querySelector('[data-calendar-sidepanel], #calendar-sidepanel');

            const backdrop = backdropId
                ? document.getElementById(backdropId)
                : document.querySelector('[data-calendar-sidepanel-backdrop], #calendar-sidepanel-backdrop');

            sidepanel?.classList.add('is-open');
            sidepanel?.setAttribute('aria-hidden', 'false');
            backdrop?.classList.add('is-open');

            return;
        }

        const closeButton = event.target.closest('[data-close-calendar-sidepanel]');
        const backdrop = event.target.closest('[data-calendar-sidepanel-backdrop], #calendar-sidepanel-backdrop');

        if (closeButton || backdrop) {
            const sidepanel = closeButton
                ? closeButton.closest('[data-calendar-sidepanel]')
                : document.getElementById(
                    backdrop.id.replace(
                        'calendar-sidepanel-backdrop-',
                        'calendar-sidepanel-'
                    )
                );

            const sidepanelBackdrop = sidepanel
                ? document.getElementById(
                    sidepanel.id.replace(
                        'calendar-sidepanel-',
                        'calendar-sidepanel-backdrop-'
                    )
                )
                : backdrop;

            const wrapper = sidepanel?.querySelector(
                '[data-calendar-url-validator], #calendar-url-field-wrapper'
            );

            if (wrapper) {
                const elements = getElements(wrapper);
                cancelCalendarEditMode(elements);
            }

            if (document.activeElement && sidepanel?.contains(document.activeElement)) {
                document.activeElement.blur();
            }

            sidepanel?.classList.remove('is-open');
            sidepanel?.setAttribute('aria-hidden', 'true');
            sidepanelBackdrop?.classList.remove('is-open');

            return;
        }

        const editButton = event.target.closest('[data-edit-calendar-url], #edit-calendar-url-button');

        if (editButton) {
            const wrapper = getWrapper(editButton);

            if (!wrapper) {
                return;
            }

            const elements = getElements(wrapper);

            wrapper.dataset.calendarEditMode = '1';

            wrapper.dataset.originalCalendarUrl = getFormValue(elements.calendarUrlInput);
            wrapper.dataset.originalCalendarUsername = getFormValue(elements.usernameInput);
            wrapper.dataset.originalCalendarPassword = getFormValue(elements.passwordInput);

            wrapper.dataset.calendarUrlValidated = '0';
            wrapper.dataset.calendarAuthValidated = '0';

            setReadonly(elements.calendarUrlInput, false);
            setReadonly(elements.usernameInput, false);
            setReadonly(elements.passwordInput, false);

            hide(elements.urlValidIcon);
            hide(elements.authValidIcon);

            hide(elements.editUrlButton);
            show(elements.urlButton, 'inline-block');

            hide(elements.authButton);
            hide(elements.authRequiredMessage);

            setCredentialsRequired(elements, false);

            openAuth(elements);

            setMessage(
                elements,
                'dark',
                'Vous modifiez actuellement l’adresse (URL) de votre calendrier et ses identifiants. Validez de nouveau l’URL pour enregistrer les changements.'
            );

            elements.calendarUrlInput?.focus();

            return;
        }

        const validateUrlButton = event.target.closest('[data-validate-calendar-url], #validate-calendar-url-button');
        if (validateUrlButton) {
            validateUrl(validateUrlButton);
            return;
        }

        const validateAuthButton = event.target.closest('[data-validate-calendar-auth], #validate-calendar-auth-button');
        if (validateAuthButton) {
            validateAuth(validateAuthButton);
            return;
        }

        const authToggleButton = event.target.closest('[data-toggle-calendar-auth], #toggle-calendar-auth-button');
        if (authToggleButton) {
            const elements = getElements(getWrapper(authToggleButton));
            const isOpen = elements.authCollapse && !elements.authCollapse.classList.contains('d-none');

            if (isOpen) {
                closeAuth(elements);
            } else {
                openAuth(elements);
            }
        }
    });

    document.addEventListener('input', function (event) {
        const wrapper = getWrapper(event.target);

        if (!wrapper) {
            return;
        }

        const elements = getElements(wrapper);

        if (
            event.target !== elements.calendarUrlInput &&
            event.target !== elements.usernameInput &&
            event.target !== elements.passwordInput
        ) {
            return;
        }

        if (!hasCalendarChanged(elements)) {
            return;
        }

        if (event.target === elements.calendarUrlInput) {
            setMessage(elements, null, '');

            elements.wrapper.dataset.calendarUrlValidated = '0';
            elements.wrapper.dataset.calendarAuthValidated = '0';

            hide(elements.urlValidIcon);
            hide(elements.authValidIcon);

            show(elements.urlButton, 'inline-block');
            hide(elements.editUrlButton);

            hide(elements.authButton);
            hide(elements.authRequiredMessage);

            setReadonly(elements.calendarUrlInput, false);
            setReadonly(elements.usernameInput, false);
            setReadonly(elements.passwordInput, false);

            setCredentialsRequired(elements, false);
            setDependentFieldsVisible(elements, false);
            setConnectionStatus(elements, false);

            return;
        }

        if (
            event.target === elements.usernameInput ||
            event.target === elements.passwordInput
        ) {
            elements.wrapper.dataset.calendarAuthValidated = '0';

            hide(elements.authValidIcon);

            if (elements.wrapper.dataset.calendarUrlValidated === '1') {
                hide(elements.urlButton);
                show(elements.editUrlButton, 'inline-block');
                show(elements.urlValidIcon, 'inline-block');
                setReadonly(elements.calendarUrlInput, true);
            }

            setReadonly(elements.usernameInput, false);
            setReadonly(elements.passwordInput, false);

            const authRequired = elements.caldavFields?.dataset.authRequired === '1';

            if (authRequired || elements.wrapper.dataset.calendarUrlValidated === '1') {
                show(elements.authButton, 'inline-block');
            }

            hide(elements.authRequiredMessage);
            setDependentFieldsVisible(elements, false);
            setConnectionStatus(elements, false);

            return;
        }
    });

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (!canSubmitCalendar(form)) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);

    document.addEventListener('DOMContentLoaded', function () {
        const wrapper = getWrapper();

        if (!wrapper) {
            return;
        }

        const elements = getElements(wrapper);

        const alreadyConnected =
            wrapper.dataset.calendarUrlValidated === '1'
            && wrapper.dataset.calendarAuthValidated === '1';

        setDependentFieldsVisible(
            elements,
            alreadyConnected || (
                elements.dependentFields &&
                elements.dependentFields.style.display !== 'none'
            )
        );

        if (alreadyConnected) {
            setConnectionStatus(elements, true);

            hide(elements.urlButton);
            show(elements.editUrlButton, 'inline-block');

            show(elements.urlValidIcon, 'inline-block');
            show(elements.authValidIcon, 'inline-block');

            setReadonly(elements.calendarUrlInput, true);
            setReadonly(elements.usernameInput, true);
            setReadonly(elements.passwordInput, true);

            return;
        }

        if (wrapper.dataset.calendarCredentialsRequiredOnLoad === '1') {
            setCredentialsRequired(elements, true);
            openAuth(elements);
            show(elements.authButton, 'inline-block');

            setAuthWarning(
                elements,
                'Authentification requise.',
                'Veuillez renseigner l’identifiant et le mot de passe d’application du calendrier.'
            );
        }
    });
})();