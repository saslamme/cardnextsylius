const STORAGE_KEY = 'cardnext.productComparison';
const GROUP_KEY = 'cardnext.productComparisonGroup';
const MAX_PRODUCTS = 3;

const read = () => {
    try {
        const value = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '[]');
        return Array.isArray(value) ? value.filter(code => typeof code === 'string').slice(0, MAX_PRODUCTS) : [];
    } catch (_) {
        return [];
    }
};

const write = (codes, group = null) => {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(codes));
    if (codes.length && group) window.localStorage.setItem(GROUP_KEY, group);
    if (!codes.length) window.localStorage.removeItem(GROUP_KEY);
    document.dispatchEvent(new CustomEvent('cardnext:comparison-changed', {detail: {codes}}));
};

const controls = () => Array.from(document.querySelectorAll('[data-compare-toggle]'));
let message = '';

const render = () => {
    const codes = read();
    controls().forEach(control => {
        const active = codes.includes(control.dataset.productCode);
        control.setAttribute('aria-pressed', active ? 'true' : 'false');
        control.classList.toggle('is-selected', active);
        const detail = control.classList.contains('cn-compare-detail');
        control.textContent = active ? (detail ? 'Aus Vergleich entfernen' : '✓ Ausgewählt') : (detail ? 'Zum Vergleich hinzufügen' : '+ Vergleichen');
        control.setAttribute('aria-label', `${control.dataset.productName} ${active ? 'aus dem Vergleich entfernen' : 'zum Vergleich hinzufügen'}`);
    });

    let bar = document.querySelector('[data-compare-bar]');
    if (!codes.length) { if (bar) bar.remove(); return; }
    if (!bar) {
        bar = document.createElement('aside');
        bar.className = 'cn-compare-bar';
        bar.dataset.compareBar = '';
        bar.setAttribute('aria-live', 'polite');
        document.body.appendChild(bar);
    }
    const url = controls().find(control => control.dataset.compareUrl)?.dataset.compareUrl || `${window.location.pathname.replace(/\/[^/]*$/, '')}/produktvergleich`;
    const items = codes.map(code => `<span class="cn-compare-bar__item"><span>${escapeHtml(code)}</span><button type="button" data-bar-remove="${escapeHtml(code)}" aria-label="${escapeHtml(code)} entfernen">×</button></span>`).join('');
    const ready = codes.length >= 2;
    bar.innerHTML = `<div class="cn-container cn-compare-bar__inner"><strong>Produktvergleich</strong><div class="cn-compare-bar__items">${items}</div><div class="cn-compare-bar__actions"><button type="button" class="cn-compare-clear" data-compare-clear>Vergleich leeren</button>${ready ? `<a class="cn-btn cn-btn--primary cn-btn--small" href="${url}?products=${encodeURIComponent(codes.join(','))}">${codes.length} Produkte vergleichen →</a>` : '<button class="cn-btn cn-btn--secondary cn-btn--small" type="button" disabled>Noch 1 Produkt auswählen</button>'}</div>${message ? `<p class="cn-compare-bar__message" role="alert">${escapeHtml(message)}</p>` : ''}</div>`;
};

const escapeHtml = value => value.replace(/[&<>'"]/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'}[character]));

document.addEventListener('click', event => {
    const toggle = event.target.closest('[data-compare-toggle]');
    if (toggle) {
        const codes = read();
        const code = toggle.dataset.productCode;
        const selected = codes.includes(code);
        message = '';
        if (selected) codes.splice(codes.indexOf(code), 1);
        else if (codes.length >= MAX_PRODUCTS) message = 'Sie können maximal 3 Produkte vergleichen.';
        else {
            const currentGroup = window.localStorage.getItem(GROUP_KEY);
            if (codes.length && currentGroup && toggle.dataset.productGroup && currentGroup !== toggle.dataset.productGroup) message = 'Diese Produkte gehören zu unterschiedlichen Produktbereichen und können nicht sinnvoll miteinander verglichen werden.';
            else codes.push(code);
        }
        write(codes, toggle.dataset.productGroup);
        render();
        return;
    }
    const remove = event.target.closest('[data-bar-remove], [data-compare-remove]');
    if (remove) {
        const code = remove.dataset.barRemove || remove.dataset.compareRemove;
        write(read().filter(item => item !== code));
        if (remove.dataset.compareRemove) {
            const next = read();
            window.location.assign(`${window.location.pathname}${next.length ? `?products=${encodeURIComponent(next.join(','))}` : ''}`);
        } else render();
    }
    if (event.target.closest('[data-compare-clear]')) { message = ''; write([]); render(); }
});

document.querySelector('[data-compare-differences]')?.addEventListener('change', event => {
    document.querySelectorAll('[data-compare-row]').forEach(row => { row.hidden = event.target.checked && row.dataset.different !== 'true'; });
});

const page = document.querySelector('[data-compare-page]');
if (page && page.dataset.validCodes) write(page.dataset.validCodes.split(',').filter(Boolean), window.localStorage.getItem(GROUP_KEY));
window.addEventListener('storage', render);
window.addEventListener('pageshow', render);
render();
