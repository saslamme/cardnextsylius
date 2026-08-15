from pathlib import Path
import re
import sys

path = Path("assets/shop/styles/cardnext.css")

if not path.exists():
    sys.exit("FEHLER: assets/shop/styles/cardnext.css wurde nicht gefunden.")

css = path.read_text(encoding="utf-8")

# Die beiden vorherigen, inzwischen überflüssigen Badge-Fixes vollständig entfernen.
markers = [
    ("/* CARDNEXT HEADER CART ALIGNMENT:START */", "/* CARDNEXT HEADER CART ALIGNMENT:END */"),
    ("/* CARDNEXT CART BADGE DOT:START */", "/* CARDNEXT CART BADGE DOT:END */"),
]

for start, end in markers:
    css = re.sub(
        r'\n*' + re.escape(start) + r'.*?' + re.escape(end) + r'\n*',
        '\n\n',
        css,
        flags=re.S,
    )

start = "/* CARDNEXT CART BADGE TEMPLATE:START */"
end = "/* CARDNEXT CART BADGE TEMPLATE:END */"

block = r"""
/* CARDNEXT CART BADGE TEMPLATE:START */

.cn-shop-header__cart .cn-shop-header__action {
    overflow: visible;
}

.cn-shop-header__cart .cn-shop-header__action-icon {
    position: relative;
    overflow: visible;
}

.cn-shop-header__cart .cn-shop-header__action-icon > svg {
    position: relative;
    z-index: 1;
}

.cn-shop-header__cart .cardnext-cart-badge {
    position: absolute;
    top: -8px;
    right: -10px;
    min-width: 19px;
    height: 19px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    margin: 0;
    border: 2px solid #fff;
    border-radius: 999px;
    background: var(--cn-primary, #f04b23);
    color: #fff;
    box-sizing: border-box;
    font-size: .64rem;
    font-weight: 800;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    z-index: 3;
}

.cn-shop-header__cart .cardnext-cart-badge:empty {
    display: none;
}

@media (max-width: 767.98px) {
    .cn-shop-header__cart .cardnext-cart-badge {
        top: -7px;
        right: -9px;
        min-width: 18px;
        height: 18px;
        font-size: .6rem;
    }
}

/* CARDNEXT CART BADGE TEMPLATE:END */
""".strip()

pattern = re.compile(re.escape(start) + r".*?" + re.escape(end), re.S)

if pattern.search(css):
    css = pattern.sub(block, css)
else:
    css = css.rstrip() + "\n\n" + block + "\n"

path.write_text(css, encoding="utf-8")
print("OK: alten Badge-CSS-Ballast entfernt und zentralen Template-Badge-Stil gesetzt.")
