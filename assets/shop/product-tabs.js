const root = document.querySelector('[data-cn-product-tabs]');

if (root) {
    const tabs = Array.from(root.querySelectorAll('[data-cn-product-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-cn-product-panel]'));

    const activate = (id, updateHash = true) => {
        const tab = tabs.find((item) => item.dataset.cnProductTab === id);
        const panel = panels.find((item) => item.dataset.cnProductPanel === id);

        if (!tab || !panel) {
            return;
        }

        tabs.forEach((item) => {
            const active = item === tab;

            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
            item.tabIndex = active ? 0 : -1;
        });

        panels.forEach((item) => {
            const active = item === panel;

            item.hidden = !active;
            item.classList.toggle('is-active', active);
        });

        if (updateHash) {
            const url = new URL(window.location.href);
            url.hash = id;
            window.history.replaceState({}, '', url);
        }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => {
            activate(tab.dataset.cnProductTab);
        });

        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                return;
            }

            event.preventDefault();

            let targetIndex = index;

            if (event.key === 'ArrowRight') targetIndex = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') targetIndex = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') targetIndex = 0;
            if (event.key === 'End') targetIndex = tabs.length - 1;

            tabs[targetIndex].focus();
            activate(tabs[targetIndex].dataset.cnProductTab);
        });
    });

    const initial = window.location.hash.replace('#', '');

    if (initial && panels.some((panel) => panel.dataset.cnProductPanel === initial)) {
        activate(initial, false);
    } else {
        activate('overview', false);
    }
}
