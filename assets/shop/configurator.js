const debounce = (callback, delay) => {
    let timeout;

    return () => {
        window.clearTimeout(timeout);
        timeout = window.setTimeout(callback, delay);
    };
};

const selectionValue = (field) => {
    const type = field.dataset.fieldType;
    const controls = [...field.querySelectorAll('input[name^="selection["]')];
    if (type === 'multiple_choice') {
        return controls.filter((control) => control.checked).map((control) => control.value);
    }
    if (type === 'single_choice') {
        return controls.find((control) => control.checked)?.value;
    }
    if (type === 'boolean') {
        return controls[0]?.checked ?? false;
    }
    const value = controls[0]?.value;
    if (value === '' || value === undefined) {
        return undefined;
    }
    if (type === 'integer' || type === 'quantity') {
        return Number.parseInt(value, 10);
    }
    return value;
};

document.querySelectorAll('[data-configurator]').forEach((root) => {
    const form = root.querySelector('[data-configurator-form]');
    const submit = root.querySelector('[data-configurator-submit]');
    const price = root.querySelector('.cn-configurator__price');
    let controller;
    const dependencies = JSON.parse(root.dataset.dependencies || '[]').sort((a, b) => a.priority - b.priority);

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

    const applyDependencies = () => {
        root.querySelectorAll('[data-configurator-field]').forEach((field) => {
            field.hidden = false;
            field.querySelectorAll('input').forEach((input) => { input.disabled = false; input.required = input.dataset.baseRequired === 'true'; });
            field.querySelectorAll('[data-configurator-value]').forEach((value) => { value.hidden = false; });
        });
        dependencies.filter((rule) => ['show', 'enable'].includes(rule.effect)).forEach((rule) => applyRule(rule, false));
        dependencies.filter(dependencyMatches).forEach((rule) => applyRule(rule, true));
    };

    const applyRule = (rule, active) => {
        const field = rule.targetFieldCode ? root.querySelector(`[data-configurator-field="${CSS.escape(rule.targetFieldCode)}"]`) : null;
        if (!field) return;
        const value = rule.targetValueCode ? field.querySelector(`[data-configurator-value="${CSS.escape(rule.targetValueCode)}"]`) : null;
        const target = value || field;
        const controls = [...target.querySelectorAll('input')];
        if (rule.effect === 'show') target.hidden = !active;
        if (rule.effect === 'hide') target.hidden = active;
        if (rule.effect === 'enable') controls.forEach((control) => { control.disabled = !active; });
        if (['disable', 'forbid'].includes(rule.effect) && active) controls.forEach((control) => { control.disabled = true; });
        if (rule.effect === 'require') controls.forEach((control) => { control.required = active; });
        if (target.hidden || controls.some((control) => control.disabled)) controls.forEach((control) => { control.checked = false; if (!['radio', 'checkbox'].includes(control.type)) control.value = ''; });
    };

    root.querySelectorAll('[data-configurator-field] input').forEach((input) => { input.dataset.baseRequired = String(input.required); });
    applyDependencies();

    const clearErrors = () => {
        root.querySelectorAll('[data-field-error]').forEach((element) => {
            element.textContent = '';
            element.classList.remove('d-block');
        });
        root.querySelector('[data-configurator-errors]').classList.add('d-none');
    };

    const showErrors = (errors) => {
        const general = [];
        errors.forEach(({ field, message }) => {
            const target = field ? root.querySelector(`[data-field-error="${CSS.escape(field)}"]`) : null;
            if (target) {
                target.textContent = message;
                target.classList.add('d-block');
            } else {
                general.push(message);
            }
        });
        if (general.length) {
            const box = root.querySelector('[data-configurator-errors]');
            box.textContent = general.join(' ');
            box.classList.remove('d-none');
        }
    };

    const calculate = async () => {
        applyDependencies();
        clearErrors();
        const quantity = Number.parseInt(root.querySelector('[data-configurator-quantity]').value, 10);
        const selections = {};
        root.querySelectorAll('[data-configurator-field]:not([hidden])').forEach((field) => {
            const value = selectionValue(field);
            if (value !== undefined && value !== '' && !(Array.isArray(value) && value.length === 0)) {
                if (![...field.querySelectorAll('input')].every((input) => input.disabled)) selections[field.dataset.configuratorField] = value;
            }
        });

        controller?.abort();
        controller = new AbortController();
        submit.disabled = true;
        price.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify({quantity, leadTimeCode: root.querySelector('input[name="leadTimeCode"]:checked')?.value, selections}),
                signal: controller.signal,
            });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                showErrors(data.errors ?? [{field: null, message: 'Der Preis konnte nicht berechnet werden.'}]);
                return;
            }

            const formatter = new Intl.NumberFormat(document.documentElement.lang || 'de-DE', {style: 'currency', currency: data.currencyCode});
            const breakdown = root.querySelector('[data-configurator-breakdown]');
            breakdown.replaceChildren(...data.breakdown.map((line) => {
                const row = document.createElement('div');
                row.className = 'cn-configurator__price-line';
                const label = document.createElement('span');
                label.textContent = line.label || 'Preisposition';
                const amount = document.createElement('span');
                amount.textContent = formatter.format(line.amount / 100);
                row.append(label, amount);
                return row;
            }));
            root.querySelector('[data-configurator-total]').textContent = formatter.format(data.total / 100);
            root.querySelector('[data-configurator-price-title]').textContent = 'Preisübersicht';
            root.querySelector('[data-configurator-result-quantity]').textContent = data.quantity;
            const leadTimeResult = root.querySelector('[data-configurator-lead-time-result]');
            if (leadTimeResult && data.leadTimeCode) {
                leadTimeResult.querySelector('span').textContent = `${data.leadTimeName} – ca. ${data.workingDays} Arbeitstage`;
                leadTimeResult.classList.remove('d-none');
            }
            root.querySelector('[data-configurator-placeholder]').classList.add('d-none');
            root.querySelector('[data-configurator-result]').classList.remove('d-none');
        } catch (error) {
            if (error.name !== 'AbortError') {
                showErrors([{field: null, message: 'Der Preisservice ist derzeit nicht erreichbar.'}]);
            }
        } finally {
            submit.disabled = false;
            price.setAttribute('aria-busy', 'false');
        }
    };

    const debouncedCalculate = debounce(calculate, 450);
    form.addEventListener('submit', (event) => { event.preventDefault(); calculate(); });
    form.addEventListener('change', () => { applyDependencies(); debouncedCalculate(); });
    form.addEventListener('input', () => { applyDependencies(); debouncedCalculate(); });
});
