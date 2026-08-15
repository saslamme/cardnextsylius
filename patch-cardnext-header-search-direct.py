from pathlib import Path
import re
import sys

path = Path("assets/shop/cardnext.js")

if not path.exists():
    sys.exit("FEHLER: assets/shop/cardnext.js wurde nicht gefunden.")

js = path.read_text(encoding="utf-8")

old_markers = [
    ("// CARDNEXT HEADER SEARCH DIRECT:START", "// CARDNEXT HEADER SEARCH DIRECT:END"),
    ("// CARDNEXT DIRECT HEADER SEARCH:START", "// CARDNEXT DIRECT HEADER SEARCH:END"),
    ("// CARDNEXT HEADER SEARCH RESTORE:START", "// CARDNEXT HEADER SEARCH RESTORE:END"),
]

for start, end in old_markers:
    js = re.sub(
        r"\n*" + re.escape(start) + r".*?" + re.escape(end) + r"\n*",
        "\n\n",
        js,
        flags=re.S,
    )

start = "// CARDNEXT SMART HEADER SEARCH DIRECT:START"
end = "// CARDNEXT SMART HEADER SEARCH DIRECT:END"

block = r'''// CARDNEXT SMART HEADER SEARCH DIRECT:START
const cnInitDirectHeaderSearch = () => {
    const forms = Array.from(document.querySelectorAll('form')).filter((form) => {
        const action = form.getAttribute('action') || '';
        const input = form.querySelector(
            'input[name="q"], input[data-cardnext-search-input], input[placeholder*="Hersteller"], input[placeholder*="Artikelnummer"]'
        );

        return Boolean(
            input
            && (
                action.includes('/suche')
                || action.includes('/search')
                || input.name === 'q'
            )
        );
    });

    for (const form of forms) {
        if (form.dataset.cnDirectSearchReady === '1') {
            continue;
        }

        form.dataset.cnDirectSearchReady = '1';

        const toggleNodes = [
            form,
            ...form.querySelectorAll('[data-bs-toggle], [data-bs-target], [aria-controls]')
        ];

        for (const node of toggleNodes) {
            const target = (
                node.getAttribute('data-bs-target')
                || node.getAttribute('aria-controls')
                || ''
            ).toLowerCase();

            const toggle = (node.getAttribute('data-bs-toggle') || '').toLowerCase();

            if (
                toggle.includes('collapse')
                || toggle.includes('offcanvas')
                || target.includes('search')
                || target.includes('suche')
            ) {
                node.removeAttribute('data-bs-toggle');
                node.removeAttribute('data-bs-target');
                node.removeAttribute('aria-controls');
                node.removeAttribute('aria-expanded');
            }
        }

        const input = form.querySelector(
            'input[name="q"], input[data-cardnext-search-input], input[placeholder*="Hersteller"], input[placeholder*="Artikelnummer"]'
        );

        const submit = form.querySelector(
            'button[type="submit"], input[type="submit"], button:not([type])'
        );

        if (submit) {
            submit.addEventListener('click', (event) => {
                event.stopPropagation();

                if (typeof event.stopImmediatePropagation === 'function') {
                    event.stopImmediatePropagation();
                }

                if (!input || input.value.trim() === '') {
                    input?.focus();
                    event.preventDefault();
                    return;
                }

                event.preventDefault();

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }, true);
        }

        let parent = form.parentElement;

        for (let depth = 0; parent && depth < 3; depth += 1, parent = parent.parentElement) {
            const target = (
                parent.getAttribute('data-bs-target')
                || parent.getAttribute('aria-controls')
                || ''
            ).toLowerCase();

            const toggle = (parent.getAttribute('data-bs-toggle') || '').toLowerCase();

            if (
                toggle.includes('collapse')
                || toggle.includes('offcanvas')
                || target.includes('search')
                || target.includes('suche')
            ) {
                parent.removeAttribute('data-bs-toggle');
                parent.removeAttribute('data-bs-target');
                parent.removeAttribute('aria-controls');
                parent.removeAttribute('aria-expanded');
            }
        }
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', cnInitDirectHeaderSearch, { once: true });
} else {
    cnInitDirectHeaderSearch();
}
// CARDNEXT SMART HEADER SEARCH DIRECT:END'''

pattern = re.compile(
    re.escape(start) + r".*?" + re.escape(end),
    flags=re.S,
)

if pattern.search(js):
    js = pattern.sub(block, js)
else:
    js = js.rstrip() + "\n\n" + block + "\n"

path.write_text(js, encoding="utf-8")
print("OK: Desktop-Suche wieder auf direkte Smart Search gestellt.")
