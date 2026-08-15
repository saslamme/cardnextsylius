from pathlib import Path
import re
import sys

path = Path("assets/shop/cardnext.js")
if not path.exists():
    sys.exit("FEHLER: assets/shop/cardnext.js wurde nicht gefunden.")

js = path.read_text(encoding="utf-8")

start = "// CARDNEXT HEADER CART BADGE:START"
end = "// CARDNEXT HEADER CART BADGE:END"

block = r"""// CARDNEXT HEADER CART BADGE:START
const cnInitHeaderCartBadge = () => {
    const cartAction =
        document.querySelector('.cn-shop-header__actions [data-bs-target="#offcanvasCart"]')
        || document.querySelector('.cn-shop-header__actions [aria-controls="offcanvasCart"]')
        || Array.from(document.querySelectorAll('.cn-shop-header__actions a, .cn-shop-header__actions button')).find((element) => {
            const href = (element.getAttribute('href') || '').toLowerCase();
            const aria = (element.getAttribute('aria-label') || '').toLowerCase();
            const text = (element.textContent || '').toLowerCase();

            return href.includes('/cart') || aria.includes('warenkorb') || text.includes('warenkorb');
        });

    if (!cartAction) {
        return;
    }

    let countSource = null;
    let count = null;

    const explicitSources = cartAction.querySelectorAll(
        '[data-test-cart-quantity], [data-test-cart-count], .badge, .cn-cart-count, .cn-shop-header__cart-count'
    );

    for (const element of explicitSources) {
        const value = (element.textContent || '').trim();

        if (/^\d+$/.test(value)) {
            countSource = element;
            count = Number.parseInt(value, 10);
            break;
        }
    }

    if (count === null) {
        const candidates = Array.from(
            cartAction.querySelectorAll('span, small, strong, b, em, div')
        );

        for (const element of candidates) {
            if (element.classList.contains('cn-header-cart-badge')) {
                continue;
            }

            const value = (element.textContent || '').trim();

            if (/^\d+$/.test(value)) {
                countSource = element;
                count = Number.parseInt(value, 10);
                break;
            }
        }
    }

    if (count === null) {
        for (const node of cartAction.childNodes) {
            if (node.nodeType !== Node.TEXT_NODE) {
                continue;
            }

            const value = (node.textContent || '').trim();

            if (/^\d+$/.test(value)) {
                count = Number.parseInt(value, 10);

                const source = document.createElement('span');
                source.className = 'cn-header-cart-count-source';
                source.setAttribute('aria-hidden', 'true');
                source.textContent = value;

                node.parentNode.replaceChild(source, node);
                countSource = source;
                break;
            }
        }
    }

    let iconHost = cartAction.querySelector('.cn-header-cart-icon');

    if (!iconHost) {
        const icon = cartAction.querySelector('svg, i');

        if (!icon) {
            return;
        }

        iconHost = document.createElement('span');
        iconHost.className = 'cn-header-cart-icon';

        icon.parentNode.insertBefore(iconHost, icon);
        iconHost.appendChild(icon);
    }

    let badge = iconHost.querySelector('.cn-header-cart-badge');

    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'cn-header-cart-badge';
        badge.setAttribute('aria-hidden', 'true');
        iconHost.appendChild(badge);
    }

    if (Number.isFinite(count) && count > 0) {
        badge.textContent = String(count);
        badge.hidden = false;

        if (countSource && countSource !== badge) {
            countSource.classList.add('cn-header-cart-count-source');
            countSource.setAttribute('aria-hidden', 'true');
        }
    } else {
        badge.hidden = true;
    }
};

const cnScheduleHeaderCartBadge = () => {
    window.requestAnimationFrame(cnInitHeaderCartBadge);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', cnScheduleHeaderCartBadge, { once: true });
} else {
    cnScheduleHeaderCartBadge();
}

window.addEventListener('load', cnScheduleHeaderCartBadge);

const cnHeaderCartBadgeObserver = new MutationObserver((mutations) => {
    const relevant = mutations.some((mutation) => {
        const target = mutation.target instanceof Element ? mutation.target : mutation.target.parentElement;

        return target?.closest?.('.cn-shop-header__actions');
    });

    if (relevant) {
        cnScheduleHeaderCartBadge();
    }
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        const actions = document.querySelector('.cn-shop-header__actions');

        if (actions) {
            cnHeaderCartBadgeObserver.observe(actions, {
                childList: true,
                subtree: true,
                characterData: true,
            });
        }
    }, { once: true });
} else {
    const actions = document.querySelector('.cn-shop-header__actions');

    if (actions) {
        cnHeaderCartBadgeObserver.observe(actions, {
            childList: true,
            subtree: true,
            characterData: true,
        });
    }
}
// CARDNEXT HEADER CART BADGE:END"""

pattern = re.compile(re.escape(start) + r".*?" + re.escape(end), re.S)

if pattern.search(js):
    js = pattern.sub(block, js)
else:
    js = js.rstrip() + "\n\n" + block + "\n"

path.write_text(js, encoding="utf-8")
print("OK: Warenkorb-Badge direkt in assets/shop/cardnext.js integriert.")
