const debounce = (callback, delay) => {
    let timeout;
    return () => {
        window.clearTimeout(timeout);
        timeout = window.setTimeout(callback, delay);
    };
};

const fieldControls = (field) => [...field.querySelectorAll('input[name^="selection["], select[name^="selection["]')];

const selectionValue = (field) => {
    const type = field.dataset.fieldType;
    const controls = fieldControls(field);
    if (type === 'multiple_choice') return controls.filter((control) => control.checked).map((control) => control.value);
    if (type === 'single_choice') {
        const select = controls.find((control) => control.tagName === 'SELECT');
        return select ? (select.value || undefined) : controls.find((control) => control.checked)?.value;
    }
    if (type === 'boolean') return controls[0]?.checked ?? false;
    const value = controls[0]?.value;
    if (value === '' || value === undefined) return undefined;
    if (type === 'integer' || type === 'quantity') return Number.parseInt(value, 10);
    return value;
};

document.querySelectorAll('[data-configurator]').forEach((root) => {
    const form = root.querySelector('[data-configurator-form]');
    const price = root.querySelector('.cn-configurator__price');
    const addButtons = [...root.querySelectorAll('[data-configurator-add], [data-configurator-mobile-add]')];
    const dependencies = JSON.parse(root.dataset.dependencies || '[]').sort((a, b) => a.priority - b.priority);
    let controller;
    let calculatedPayload;
    let requestVersion = 0;

    const dependencyMatches = ({sourceFieldCode, operator, expectedValues}) => {
        const field = root.querySelector(`[data-configurator-field="${CSS.escape(sourceFieldCode)}"]`);
        const actual = field ? selectionValue(field) : undefined;
        if (actual === undefined || actual === '' || (Array.isArray(actual) && !actual.length)) return false;
        const values = Array.isArray(actual) ? actual : [actual];
        if (operator === 'equals') return expectedValues.includes(actual);
        if (operator === 'not_equals') return !expectedValues.includes(actual);
        if (operator === 'in') return values.some((value) => expectedValues.includes(value));
        if (operator === 'not_in') return !values.some((value) => expectedValues.includes(value));
        if (operator === 'is_selected') return true;
        const number = Number(actual); const expected = Number(expectedValues[0]);
        return operator === 'greater_than' ? number > expected : operator === 'greater_than_or_equal' ? number >= expected : operator === 'less_than' ? number < expected : number <= expected;
    };

    const clearControl = (control) => {
        if (control.tagName === 'OPTION') {
            if (control.selected) control.parentElement.value = '';
        } else if (['radio', 'checkbox'].includes(control.type)) control.checked = false;
        else control.value = '';
    };
    const controlIsEffective = (control) => {
        const field = control.closest('[data-configurator-field]');
        const value = control.closest('[data-configurator-value]');
        return !control.disabled && !field?.hidden && !value?.hidden && !value?.disabled;
    };
    const controlsFor = (target) => target.matches('option') ? [target] : [...target.querySelectorAll('input, select')];
    const applyRule = (rule, active) => {
        const field = rule.targetFieldCode ? root.querySelector(`[data-configurator-field="${CSS.escape(rule.targetFieldCode)}"]`) : null;
        if (!field) return;
        const value = rule.targetValueCode ? field.querySelector(`[data-configurator-value="${CSS.escape(rule.targetValueCode)}"]`) : null;
        const target = value || field;
        const controls = controlsFor(target);
        if (rule.effect === 'show') target.hidden = !active;
        if (rule.effect === 'hide') target.hidden = active;
        if (rule.effect === 'enable') controls.forEach((control) => { control.disabled = !active; });
        if (['disable', 'forbid'].includes(rule.effect) && active) controls.forEach((control) => { control.disabled = true; });
        if (rule.effect === 'require') fieldControls(field).forEach((control) => { control.required = active; });
    };
    const applyDependencies = () => {
        root.querySelectorAll('[data-configurator-field]').forEach((field) => {
            field.hidden = false;
            fieldControls(field).forEach((control) => { control.disabled = false; control.required = control.dataset.baseRequired === 'true'; });
            field.querySelectorAll('[data-configurator-value]').forEach((value) => { value.hidden = false; value.disabled = false; });
        });
        dependencies.filter((rule) => ['show', 'enable'].includes(rule.effect)).forEach((rule) => applyRule(rule, false));
        dependencies.filter(dependencyMatches).forEach((rule) => applyRule(rule, true));
        root.querySelectorAll('[data-configurator-field] input, [data-configurator-field] select, [data-configurator-field] option[data-configurator-value]').forEach((control) => {
            if (!controlIsEffective(control)) clearControl(control);
        });
    };

    root.querySelectorAll('[data-configurator-field] input, [data-configurator-field] select').forEach((control) => { control.dataset.baseRequired = String(control.required); });
    applyDependencies();

    const clearErrors = () => {
        root.querySelectorAll('[data-field-error]').forEach((element) => { element.textContent = ''; element.classList.remove('is-visible'); });
        root.querySelector('[data-configurator-errors]').classList.add('d-none');
        root.querySelectorAll('[aria-invalid="true"]').forEach((control) => control.removeAttribute('aria-invalid'));
    };
    const showErrors = (errors) => {
        const general = [];
        errors.forEach(({field, message}) => {
            const target = field ? root.querySelector(`[data-field-error="${CSS.escape(field)}"]`) : null;
            if (target) {
                target.textContent = message; target.classList.add('is-visible');
                const control = field === 'quantity' ? root.querySelector('[data-configurator-quantity]') : root.querySelector(`[data-configurator-field="${CSS.escape(field)}"] input, [data-configurator-field="${CSS.escape(field)}"] select`);
                control?.setAttribute('aria-invalid', 'true');
            } else general.push(message);
        });
        if (general.length) { const box = root.querySelector('[data-configurator-errors]'); box.textContent = general.join(' '); box.classList.remove('d-none'); }
    };
    const incompleteConfigurationErrors = () => {
        const errors = [];
        const quantity = root.querySelector('[data-configurator-quantity]');
        if (!quantity || quantity.value === '' || !quantity.validity.valid) errors.push({field: 'quantity', message: 'Bitte geben Sie eine gültige Menge ein.'});
        root.querySelectorAll('[data-configurator-field]:not([hidden])').forEach((field) => {
            const controls = fieldControls(field).filter(controlIsEffective);
            const required = controls.filter((control) => control.required);
            if (!required.length) return;
            const complete = field.dataset.fieldType === 'single_choice'
                ? controls.some((control) => control.tagName === 'SELECT' ? control.value !== '' : control.checked)
                : required.every((control) => ['radio', 'checkbox'].includes(control.type) ? control.checked : control.value !== '' && control.validity.valid);
            if (!complete) errors.push({field: field.dataset.configuratorField, message: 'Bitte füllen Sie dieses Pflichtfeld aus.'});
        });
        const leadTimes = root.querySelector('[data-configurator-lead-times]');
        if (leadTimes && ![...leadTimes.querySelectorAll('input[name="leadTimeCode"][required]:not(:disabled)')].some((control) => control.checked)) errors.push({field: 'leadTime', message: 'Bitte wählen Sie eine Produktionszeit aus.'});
        return errors;
    };
    const updateSelectionSummary = () => {
        const summary = root.querySelector('[data-configurator-selection]');
        const rows = [];
        const addRow = (label, value) => { if (value) rows.push([label, value]); };
        addRow(root.querySelector('label[for^="configurator-quantity-"]')?.childNodes[0]?.textContent.trim(), root.querySelector('[data-configurator-quantity]')?.value);
        root.querySelectorAll('[data-configurator-field]:not([hidden])').forEach((field) => {
            const value = selectionValue(field);
            if (value === undefined || value === false || value === '' || (Array.isArray(value) && !value.length)) return;
            const label = field.querySelector('.cn-configurator__label')?.childNodes[0]?.textContent.trim();
            const names = fieldControls(field).filter((control) => control.tagName === 'SELECT' || control.checked).flatMap((control) => control.tagName === 'SELECT' ? [control.selectedOptions[0]?.textContent] : [control.closest('label')?.querySelector('.cn-configurator__choice-copy strong')?.textContent.trim()]).filter(Boolean);
            addRow(label, field.dataset.fieldType === 'boolean' ? 'Ja' : (names.join(', ') || String(value)));
        });
        summary.replaceChildren(...rows.map(([label, value]) => { const row = document.createElement('div'); row.innerHTML = `<span></span><strong></strong>`; row.querySelector('span').textContent = label; row.querySelector('strong').textContent = value; return row; }));
    };
    const invalidateResult = () => {
        calculatedPayload = undefined; requestVersion += 1; controller?.abort(); controller = undefined;
        addButtons.forEach((button) => { button.disabled = true; });
        root.querySelector('[data-configurator-state]').textContent = 'Unvollständig';
        root.querySelector('[data-configurator-placeholder]')?.classList.remove('d-none');
        root.querySelector('[data-configurator-result]')?.classList.add('d-none');
        root.querySelector('[data-configurator-mobile-total]').textContent = '–';
    };
    const calculate = async () => {
        invalidateResult(); applyDependencies(); updateSelectionSummary(); clearErrors();
        const requiredErrors = incompleteConfigurationErrors();
        if (requiredErrors.length) { price.setAttribute('aria-busy', 'false'); return; }
        const quantity = Number.parseInt(root.querySelector('[data-configurator-quantity]').value, 10);
        const selections = {};
        root.querySelectorAll('[data-configurator-field]:not([hidden])').forEach((field) => {
            const value = selectionValue(field); const controls = fieldControls(field);
            if (value !== undefined && value !== '' && !(Array.isArray(value) && !value.length) && !controls.every((control) => control.disabled)) selections[field.dataset.configuratorField] = value;
        });
        const payload = {quantity, leadTimeCode: root.querySelector('input[name="leadTimeCode"]:checked')?.value, selections};
        controller = new AbortController(); const version = requestVersion; price.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(root.dataset.endpoint, {method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json'}, body: JSON.stringify(payload), signal: controller.signal});
            const data = await response.json();
            if (version !== requestVersion) return;
            if (!response.ok || !data.ok) { showErrors(data.errors ?? [{field: null, message: 'Der Preis konnte nicht berechnet werden.'}]); return; }
            const formatter = new Intl.NumberFormat(document.documentElement.lang || 'de-DE', {style: 'currency', currency: data.currencyCode});
            const breakdown = root.querySelector('[data-configurator-breakdown]');
            breakdown.replaceChildren(...data.breakdown.map((line) => { const row = document.createElement('div'); row.className = 'cn-configurator__price-line'; const label = document.createElement('span'); label.textContent = line.label || 'Preisposition'; const amount = document.createElement('strong'); amount.textContent = formatter.format(line.amount / 100); row.append(label, amount); return row; }));
            const total = formatter.format(data.total / 100);
            root.querySelector('[data-configurator-total]').textContent = total;
            root.querySelector('[data-configurator-unit]').textContent = formatter.format(data.total / data.quantity / 100);
            root.querySelector('[data-configurator-mobile-total]').textContent = total;
            root.querySelector('[data-configurator-mobile-unit]').textContent = `${formatter.format(data.total / data.quantity / 100)} / Stück`;
            const lead = root.querySelector('[data-configurator-lead-time-result]');
            if (data.leadTimeCode) { lead.querySelector('strong').textContent = data.leadTimeName; lead.querySelector('small').textContent = `ca. ${data.workingDays} Arbeitstage`; lead.classList.remove('d-none'); }
            root.querySelector('[data-configurator-placeholder]').classList.add('d-none'); root.querySelector('[data-configurator-result]').classList.remove('d-none');
            root.querySelector('[data-configurator-state]').textContent = 'Aktuell'; calculatedPayload = payload; addButtons.forEach((button) => { button.disabled = false; });
        } catch (error) { if (error.name !== 'AbortError') showErrors([{field: null, message: 'Der Preisservice ist derzeit nicht erreichbar.'}]); }
        finally { if (version === requestVersion) price.setAttribute('aria-busy', 'false'); }
    };
    const debouncedCalculate = debounce(calculate, 450);
    const configurationChanged = () => { applyDependencies(); updateSelectionSummary(); clearErrors(); invalidateResult(); debouncedCalculate(); };
    form.addEventListener('change', configurationChanged); form.addEventListener('input', configurationChanged);
    const addToCart = async () => {
        if (!calculatedPayload) return;
        addButtons.forEach((button) => { button.disabled = true; });
        const response = await fetch(root.dataset.cartEndpoint, {method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': root.dataset.cartToken}, body: JSON.stringify(calculatedPayload)});
        const data = await response.json();
        if (response.ok && data.ok) window.location.assign(data.cartUrl);
        else { showErrors([{field: null, message: data.message || 'Der Artikel konnte nicht hinzugefügt werden.'}]); addButtons.forEach((button) => { button.disabled = false; }); }
    };
    addButtons.forEach((button) => button.addEventListener('click', addToCart));
    updateSelectionSummary(); debouncedCalculate();
});
