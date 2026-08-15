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
