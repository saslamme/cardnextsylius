const openButton = document.querySelector('[data-cardnext-menu-open]');
const closeButton = document.querySelector('[data-cardnext-menu-close]');
const overlay = document.querySelector('[data-cardnext-mobile-overlay]');

const openMenu = () => {
    if (!overlay) return;
    overlay.hidden = false;
    document.body.classList.add('cn-nav-open');
    closeButton?.focus();
};

const closeMenu = () => {
    if (!overlay) return;
    overlay.hidden = true;
    document.body.classList.remove('cn-nav-open');
    openButton?.focus();
};

openButton?.addEventListener('click', openMenu);
closeButton?.addEventListener('click', closeMenu);
overlay?.addEventListener('click', (event) => {
    if (event.target === overlay) closeMenu();
});

const productGallery = document.querySelector('[data-cn-product-gallery]');
if (productGallery) {
    const mainImage = productGallery.querySelector('[data-cn-product-main-image]');
    const thumbs = productGallery.querySelectorAll('[data-cn-product-thumb]');
    thumbs.forEach((thumb) => {
        thumb.addEventListener('click', () => {
            if (!mainImage) return;
            const source = thumb.dataset.cnProductImage;
            if (!source) return;
            mainImage.src = source;
            thumbs.forEach((item) => {
                item.classList.remove('is-active');
                item.setAttribute('aria-pressed', 'false');
            });
            thumb.classList.add('is-active');
            thumb.setAttribute('aria-pressed', 'true');
        });
    });
}

document.addEventListener('click', (event) => {
    const minus = event.target.closest('[data-cn-qty-minus]');
    const plus = event.target.closest('[data-cn-qty-plus]');
    if (!minus && !plus) return;
    const wrapper = event.target.closest('[data-cn-product-qty]');
    const input = wrapper?.querySelector('input[type="number"]');
    if (!input) return;
    const minimum = Number.parseInt(input.min || '1', 10) || 1;
    const increment = Number.parseInt(input.step || '1', 10) || 1;
    const current = Number.parseInt(input.value || String(minimum), 10) || minimum;
    input.value = String(Math.max(minimum, current + (plus ? increment : -increment)));
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (overlay && !overlay.hidden) {
        closeMenu();
        return;
    }
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-configured-item-action]');
    if (!button || button.disabled) return;

    const row = button.closest('[data-configured-order-item]');
    const endpoint = button.dataset.endpoint;
    const token = button.dataset.csrfToken;
    if (!row || !endpoint || !token) return;

    const body = new URLSearchParams({ _token: token });
    if (button.dataset.configuredItemAction === 'quantity') {
        const quantity = row.querySelector('[data-configured-item-quantity]')?.value;
        if (!quantity) return;
        body.set('quantity', quantity);
    }

    button.disabled = true;
    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) throw new Error(`Configured cart action failed (${response.status})`);
        window.location.reload();
    } catch (error) {
        button.disabled = false;
        window.console.error(error);
    }
});
// Cardnext desktop mega menu
(() => {
    const nav = document.querySelector('[data-cn-mega-nav]');
    if (!nav) return;

    const items = Array.from(nav.querySelectorAll('[data-cn-mega-item]'));
    if (!items.length) return;

    const openDelay = 140;
    const closeDelay = 200;
    let openTimer = null;
    let closeTimer = null;
    let currentItem = null;

    const clearTimers = () => {
        window.clearTimeout(openTimer);
        window.clearTimeout(closeTimer);
        openTimer = null;
        closeTimer = null;
    };

    const closeItem = (item) => {
        if (!item) return;

        item.classList.remove('is-open');
        item.querySelector('[data-cn-mega-trigger]')?.setAttribute('aria-expanded', 'false');

        if (currentItem === item) {
            currentItem = null;
        }
    };

    const closeAll = (except = null) => {
        items.forEach((item) => {
            if (item !== except) closeItem(item);
        });
    };

    const openItem = (item) => {
        if (!item) return;

        clearTimers();
        closeAll(item);

        item.classList.add('is-open');
        item.querySelector('[data-cn-mega-trigger]')?.setAttribute('aria-expanded', 'true');
        currentItem = item;
    };

    const scheduleOpen = (item) => {
        window.clearTimeout(closeTimer);
        window.clearTimeout(openTimer);

        if (currentItem === item) return;

        openTimer = window.setTimeout(() => openItem(item), openDelay);
    };

    const scheduleClose = (item) => {
        window.clearTimeout(openTimer);
        window.clearTimeout(closeTimer);

        closeTimer = window.setTimeout(() => closeItem(item), closeDelay);
    };

    items.forEach((item) => {
        const trigger = item.querySelector('[data-cn-mega-trigger]');

        item.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'touch') return;
            scheduleOpen(item);
        });

        item.addEventListener('pointerleave', (event) => {
            if (event.pointerType === 'touch') return;
            scheduleClose(item);
        });

        item.addEventListener('focusin', () => {
            openItem(item);
        });

        item.addEventListener('focusout', () => {
            window.requestAnimationFrame(() => {
                if (!item.contains(document.activeElement)) {
                    scheduleClose(item);
                }
            });
        });

        trigger?.addEventListener('click', (event) => {
            if (!window.matchMedia('(hover: none)').matches) return;

            if (!item.classList.contains('is-open')) {
                event.preventDefault();
                openItem(item);
            }
        });
    });

    document.addEventListener('pointerdown', (event) => {
        if (!nav.contains(event.target)) {
            clearTimers();
            closeAll();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !currentItem) return;

        const trigger = currentItem.querySelector('[data-cn-mega-trigger]');
        clearTimers();
        closeAll();
        trigger?.focus();
    });

    document.querySelector('[data-cardnext-search-open]')?.addEventListener('click', () => {
        clearTimers();
        closeAll();
    });

    window.addEventListener('blur', () => {
        clearTimers();
        closeAll();
    });
})();

// Cardnext smart search suggestions
const cnSmartSearchForm = document.querySelector('[data-cn-smart-search-form]');
const cnSmartSearchInput = cnSmartSearchForm?.querySelector('[data-cn-smart-search-input]');
const cnSmartSearchSuggest = cnSmartSearchForm?.querySelector('[data-cn-search-suggest]');
const cnSmartSearchUrl = cnSmartSearchForm?.dataset.cnSuggestUrl;

let cnSmartSearchTimer = null;
let cnSmartSearchController = null;

const cnCloseSearchSuggestions = () => {
    if (!cnSmartSearchSuggest) return;
    cnSmartSearchSuggest.hidden = true;
    cnSmartSearchSuggest.replaceChildren();
};

const cnLoadSearchSuggestions = async () => {
    if (!cnSmartSearchInput || !cnSmartSearchSuggest || !cnSmartSearchUrl) return;

    const query = cnSmartSearchInput.value.trim();

    if (query.length < 2) {
        cnCloseSearchSuggestions();
        return;
    }

    cnSmartSearchController?.abort();
    cnSmartSearchController = new AbortController();

    const url = new URL(cnSmartSearchUrl, window.location.origin);
    url.searchParams.set('q', query);

    try {
        const response = await fetch(url, {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: cnSmartSearchController.signal,
        });

        if (response.status === 204) {
            cnCloseSearchSuggestions();
            return;
        }

        if (!response.ok) {
            cnCloseSearchSuggestions();
            return;
        }

        const html = await response.text();

        if (cnSmartSearchInput.value.trim() !== query) return;

        cnSmartSearchSuggest.innerHTML = html;
        cnSmartSearchSuggest.hidden = html.trim() === '';
    } catch (error) {
        if (error.name !== 'AbortError') {
            cnCloseSearchSuggestions();
        }
    }
};

cnSmartSearchInput?.addEventListener('input', () => {
    window.clearTimeout(cnSmartSearchTimer);
    cnSmartSearchTimer = window.setTimeout(cnLoadSearchSuggestions, 220);
});

cnSmartSearchInput?.addEventListener('focus', () => {
    if (cnSmartSearchInput.value.trim().length >= 2) {
        window.clearTimeout(cnSmartSearchTimer);
        cnSmartSearchTimer = window.setTimeout(cnLoadSearchSuggestions, 80);
    }
});

document.addEventListener('pointerdown', (event) => {
    if (!cnSmartSearchForm || cnSmartSearchForm.contains(event.target)) return;
    cnCloseSearchSuggestions();
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        cnCloseSearchSuggestions();
    }
});

// CARDNEXT CART OFFCANVAS AFTER ADD:START
const cnOpenCartAfterAdd = () => {
    const url = new URL(window.location.href);

    if (url.searchParams.get('cnCart') !== 'open') {
        return;
    }

    url.searchParams.delete('cnCart');
    window.history.replaceState(
        {},
        '',
        `${url.pathname}${url.search}${url.hash}`
    );

    const cartTrigger = document.querySelector(
        '[data-bs-toggle="offcanvas"][data-bs-target="#offcanvasCart"]'
    );

    if (!cartTrigger) {
        return;
    }

    window.setTimeout(() => {
        cartTrigger.click();
    }, 80);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', cnOpenCartAfterAdd, { once: true });
} else {
    cnOpenCartAfterAdd();
}
// CARDNEXT CART OFFCANVAS AFTER ADD:END
