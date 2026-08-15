from pathlib import Path
import re
import sys

path = Path("assets/shop/styles/cardnext.css")

if not path.exists():
    sys.exit("FEHLER: assets/shop/styles/cardnext.css wurde nicht gefunden.")

css = path.read_text(encoding="utf-8")

start = "/* CARDNEXT HEADER CART ALIGNMENT:START */"
end = "/* CARDNEXT HEADER CART ALIGNMENT:END */"

block = r"""
/* CARDNEXT HEADER CART ALIGNMENT:START */

/* Konto und Warenkorb auf exakt dieselbe optische Höhe bringen. */
.cn-shop-header__actions {
    align-items: center;
}

.cn-shop-header__actions .cn-shop-header__action,
.cn-shop-header__actions [data-bs-target="#offcanvasCart"],
.cn-shop-header__actions [aria-controls="offcanvasCart"] {
    position: relative;
    min-width: 82px;
    min-height: 58px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 4px 8px;
    line-height: 1.1;
    text-align: center;
}

.cn-shop-header__actions .cn-shop-header__action svg,
.cn-shop-header__actions [data-bs-target="#offcanvasCart"] svg,
.cn-shop-header__actions [aria-controls="offcanvasCart"] svg {
    width: 22px;
    height: 22px;
    flex: 0 0 22px;
    display: block;
    margin: 0;
}

/* Warenkorb-Menge nicht mehr als dritte Textzeile anzeigen. */
.cn-shop-header__actions [data-bs-target="#offcanvasCart"] .badge,
.cn-shop-header__actions [aria-controls="offcanvasCart"] .badge,
.cn-shop-header__actions [data-bs-target="#offcanvasCart"] [data-test-cart-quantity],
.cn-shop-header__actions [aria-controls="offcanvasCart"] [data-test-cart-quantity],
.cn-shop-header__actions [data-bs-target="#offcanvasCart"] [data-test-cart-count],
.cn-shop-header__actions [aria-controls="offcanvasCart"] [data-test-cart-count],
.cn-shop-header__actions [data-bs-target="#offcanvasCart"] .cn-cart-count,
.cn-shop-header__actions [aria-controls="offcanvasCart"] .cn-cart-count,
.cn-shop-header__actions [data-bs-target="#offcanvasCart"] .cn-shop-header__cart-count,
.cn-shop-header__actions [aria-controls="offcanvasCart"] .cn-shop-header__cart-count {
    position: absolute;
    top: 0;
    left: 50%;
    min-width: 17px;
    height: 17px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    margin-left: 7px;
    border-radius: 999px;
    background: var(--cn-primary, #f04b23);
    color: #fff;
    font-size: .62rem;
    font-weight: 800;
    line-height: 17px;
    white-space: nowrap;
}

/* Falls die Menge als direktes kleines Element nach der Beschriftung gerendert wird. */
.cn-shop-header__actions [data-bs-target="#offcanvasCart"] > small:last-child,
.cn-shop-header__actions [aria-controls="offcanvasCart"] > small:last-child {
    position: absolute;
    top: 0;
    left: 50%;
    min-width: 17px;
    height: 17px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-left: 7px;
    padding: 0 4px;
    border-radius: 999px;
    background: var(--cn-primary, #f04b23);
    color: #fff;
    font-size: .62rem;
    font-weight: 800;
}

/* Header bleibt auch mit Warenkorb-Badge kompakt. */
.cn-shop-header__actions [data-bs-target="#offcanvasCart"],
.cn-shop-header__actions [aria-controls="offcanvasCart"] {
    overflow: visible;
}

@media (max-width: 767.98px) {
    .cn-shop-header__actions .cn-shop-header__action,
    .cn-shop-header__actions [data-bs-target="#offcanvasCart"],
    .cn-shop-header__actions [aria-controls="offcanvasCart"] {
        min-width: 48px;
        min-height: 48px;
        padding-inline: 4px;
    }
}

/* CARDNEXT HEADER CART ALIGNMENT:END */
"""

pattern = re.compile(
    re.escape(start) + r".*?" + re.escape(end),
    flags=re.S,
)

if pattern.search(css):
    css = pattern.sub(block.strip(), css)
else:
    css = css.rstrip() + "\n\n" + block.strip() + "\n"

path.write_text(css, encoding="utf-8")
print("OK: Warenkorb-Icon im Header ausgerichtet.")
