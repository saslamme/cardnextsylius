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
        clearErrors();
        const quantity = Number.parseInt(root.querySelector('[data-configurator-quantity]').value, 10);
        const selections = {};
        root.querySelectorAll('[data-configurator-field]').forEach((field) => {
            const value = selectionValue(field);
            if (value !== undefined && value !== '' && !(Array.isArray(value) && value.length === 0)) {
                selections[field.dataset.configuratorField] = value;
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
                body: JSON.stringify({quantity, selections}),
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
                label.textContent = line.label || line.chargeCode;
                label.title = `${line.chargeCode} · ${line.priceType} · × ${line.multiplier}`;
                const amount = document.createElement('span');
                amount.textContent = formatter.format(line.amount / 100);
                row.append(label, amount);
                return row;
            }));
            root.querySelector('[data-configurator-total]').textContent = formatter.format(data.total / 100);
            root.querySelector('[data-configurator-result-quantity]').textContent = data.quantity;
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
    form.addEventListener('change', debouncedCalculate);
    form.addEventListener('input', debouncedCalculate);
});
