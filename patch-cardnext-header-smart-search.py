from pathlib import Path
import re
import sys

path = Path("templates/shop/layout/header/content.html.twig")
if not path.exists():
    sys.exit("FEHLER: Header-Template wurde nicht gefunden.")

content = path.read_text(encoding="utf-8")

actions_marker = '            <div class="cn-shop-header__actions">'
end = content.find(actions_marker)
if end < 0:
    sys.exit("ABBRUCH: Header-Actions konnten nicht gefunden werden.")

# Supports both the regressed div-based opener and an older form-based search.
search_start_candidates = [
    content.find('            <div class="cn-shop-header__search">'),
    content.find('            <form\n                class="cn-shop-header__search"'),
]
search_start_candidates = [i for i in search_start_candidates if i >= 0]

if not search_start_candidates:
    sys.exit("ABBRUCH: Header-Suchbereich wurde nicht gefunden.")

start = min(search_start_candidates)
if end <= start:
    sys.exit("ABBRUCH: Header-Struktur ist unerwartet.")

replacement = '''            <form
                class="cn-shop-header__search"
                method="get"
                action="{{ path('cardnext_shop_search', {'_locale': app.request.locale}) }}"
                role="search"
                data-cn-smart-search-form
                data-cn-suggest-url="{{ path('cardnext_shop_search_suggest', {'_locale': app.request.locale}) }}"
            >
                <label class="visually-hidden" for="cn-header-search-input">Produkte suchen</label>

                <div class="cn-shop-header__search-box">
                    <input
                        id="cn-header-search-input"
                        class="cn-shop-header__search-input"
                        type="search"
                        name="q"
                        placeholder="Produkte, Hersteller oder Artikelnummer suchen"
                        autocomplete="off"
                        aria-label="Produkte suchen"
                        data-cn-smart-search-input
                    >

                    <button
                        class="cn-shop-header__search-button"
                        type="submit"
                        aria-label="Suche starten"
                    >
                        <svg aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="6.5"/>
                            <path d="m16 16 4.2 4.2"/>
                        </svg>
                    </button>
                </div>

                <div class="cn-search-suggest" data-cn-search-suggest hidden aria-live="polite"></div>
            </form>

'''

content = content[:start] + replacement + content[end:]

for forbidden in (
    'data-cardnext-search-open',
    'aria-controls="cardnext-product-search"',
    'readonly',
):
    if forbidden in content[start:content.find(actions_marker)]:
        sys.exit(f"ABBRUCH: Alter Search-Trigger blieb im Header erhalten: {forbidden}")

path.write_text(content, encoding="utf-8")
print("OK: sichtbare Header-Suche ist wieder die echte Cardnext Smart Search.")
