(function () {
    function isNumericText(value) {
        return /^\d+$/.test((value || '').trim());
    }

    function findCartAction() {
        const candidates = Array.from(document.querySelectorAll('.cn-shop-header__actions a, .cn-shop-header__actions button'));
        return candidates.find((el) => {
            const href = (el.getAttribute('href') || '').toLowerCase();
            const aria = (el.getAttribute('aria-label') || '').toLowerCase();
            const text = (el.textContent || '').toLowerCase();
            const target = (el.getAttribute('data-bs-target') || '').toLowerCase();
            const controls = (el.getAttribute('aria-controls') || '').toLowerCase();

            return href.includes('/cart')
                || aria.includes('warenkorb')
                || text.includes('warenkorb')
                || target.includes('offcanvascart')
                || controls.includes('offcanvascart');
        });
    }

    function findIconHost(cartAction) {
        let host = cartAction.querySelector('.cn-header-cart-icon');
        if (host) {
            return host;
        }

        const svg = cartAction.querySelector('svg');
        if (svg) {
            host = document.createElement('span');
            host.className = 'cn-header-cart-icon';
            svg.parentNode.insertBefore(host, svg);
            host.appendChild(svg);
            return host;
        }

        const iconLike = cartAction.querySelector('i, .fa, .icon');
        if (iconLike) {
            host = document.createElement('span');
            host.className = 'cn-header-cart-icon';
            iconLike.parentNode.insertBefore(host, iconLike);
            host.appendChild(iconLike);
            return host;
        }

        return null;
    }

    function findCountSource(cartAction) {
        const descendants = Array.from(cartAction.querySelectorAll('span, small, strong, em, b, div'));
        for (const el of descendants) {
            if (el.classList.contains('cn-header-cart-badge')) continue;
            const text = (el.textContent || '').trim();
            if (isNumericText(text)) {
                return { element: el, value: text };
            }
        }

        const childNodes = Array.from(cartAction.childNodes);
        for (const node of childNodes) {
            if (node.nodeType === Node.TEXT_NODE && isNumericText(node.textContent || '')) {
                return { textNode: node, value: (node.textContent || '').trim() };
            }
        }

        return null;
    }

    function renderBadge() {
        const cartAction = findCartAction();
        if (!cartAction) return;

        const countSource = findCountSource(cartAction);
        if (!countSource) return;

        const count = parseInt(countSource.value, 10);
        if (!Number.isFinite(count) || count <= 0) return;

        const iconHost = findIconHost(cartAction);
        if (!iconHost) return;

        let badge = iconHost.querySelector('.cn-header-cart-badge');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'cn-header-cart-badge';
            iconHost.appendChild(badge);
        }

        badge.textContent = String(count);

        if (countSource.element) {
            countSource.element.classList.add('cn-header-cart-count-source');
            countSource.element.setAttribute('aria-hidden', 'true');
        }

        if (countSource.textNode && countSource.textNode.parentNode) {
            const wrapper = document.createElement('span');
            wrapper.className = 'cn-header-cart-count-source';
            wrapper.setAttribute('aria-hidden', 'true');
            wrapper.textContent = countSource.textNode.textContent;
            countSource.textNode.parentNode.replaceChild(wrapper, countSource.textNode);
        }
    }

    function init() {
        renderBadge();
    }

    document.addEventListener('DOMContentLoaded', init);
    window.addEventListener('load', init);
    document.addEventListener('sylius:ajax-load', init);

    const observer = new MutationObserver(() => {
        renderBadge();
    });

    document.addEventListener('DOMContentLoaded', () => {
        observer.observe(document.body, { childList: true, subtree: true });
    });
})();
