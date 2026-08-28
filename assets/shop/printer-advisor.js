const root = document.querySelector('[data-printer-advisor]');

if (root) {
    const workspace = root.querySelector('[data-advisor-workspace]');
    const form = root.querySelector('[data-advisor-form]');
    const steps = [...root.querySelectorAll('[data-advisor-step]')];
    let current = 0;

    const emit = (event, detail = {}) => window.dispatchEvent(new CustomEvent(event, { detail }));
    const update = () => {
        steps.forEach((step, index) => { step.hidden = index !== current; });
        root.querySelector('[data-advisor-count]').textContent = `Schritt ${current + 1} von ${steps.length}`;
        root.querySelector('[data-advisor-progress]').style.width = `${((current + 1) / steps.length) * 100}%`;
        root.querySelector('[data-advisor-back]').hidden = current === 0;
        root.querySelector('[data-advisor-next]').hidden = current === steps.length - 1;
        root.querySelector('[data-advisor-submit]').hidden = current !== steps.length - 1;
    };
    const updateSummary = () => {
        const selected = [...form.querySelectorAll('input:checked')];
        root.querySelector('[data-advisor-summary]').innerHTML = selected.length
            ? selected.map((input) => `<div><dt>${input.closest('fieldset').querySelector('legend').textContent}</dt><dd>${input.nextElementSibling.textContent}</dd></div>`).join('')
            : '<div><dt>Noch keine Auswahl</dt><dd>Wählen Sie links Ihre Anforderungen.</dd></div>';
    };

    root.querySelector('[data-advisor-start]').addEventListener('click', () => {
        workspace.hidden = false; workspace.scrollIntoView({ behavior: 'smooth' }); emit('advisor_started'); update();
    });
    root.querySelector('[data-advisor-next]').addEventListener('click', () => {
        const checked = steps[current].querySelector('input:checked');
        if (!checked) { steps[current].querySelector('input').reportValidity(); return; }
        current++; update();
    });
    root.querySelector('[data-advisor-back]').addEventListener('click', () => { current--; update(); });
    form.addEventListener('change', updateSummary);
    form.addEventListener('submit', async (event) => {
        if (!form.checkValidity()) return;
        event.preventDefault();
        const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        root.querySelector('[data-advisor-results]').replaceWith(page.querySelector('[data-advisor-results]'));
        emit('advisor_completed'); root.querySelector('[data-advisor-results]').scrollIntoView({ behavior: 'smooth' });
    });
    update(); updateSummary();
}
