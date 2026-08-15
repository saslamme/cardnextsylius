from pathlib import Path
import re
import sys

path = Path("assets/shop/styles/cardnext.css")

if not path.exists():
    sys.exit("FEHLER: assets/shop/styles/cardnext.css wurde nicht gefunden.")

css = path.read_text(encoding="utf-8")

start = "/* CARDNEXT PRODUCT BUY ROW FIX:START */"
end = "/* CARDNEXT PRODUCT BUY ROW FIX:END */"

block = r"""
/* CARDNEXT PRODUCT BUY ROW FIX:START */

/*
 * Der aktuelle Sylius/LiveComponent-Submit kann in einem Wrapper liegen,
 * der nicht mehr .cn-purchase__submit trägt.
 * Deshalb wird der direkte Formular-Child mit Submit-Button explizit
 * in die zweite Grid-Spalte gesetzt.
 */
.cn-purchase form > :has(button[type="submit"]),
.cn-purchase form > :has(input[type="submit"]) {
    grid-column: 2 !important;
    grid-row: auto;
    min-width: 0;
    align-self: end;
}

.cn-purchase form > :has(button[type="submit"]) button[type="submit"],
.cn-purchase form > :has(input[type="submit"]) input[type="submit"] {
    width: 100%;
    min-height: 50px;
    height: 50px;
}

/* Mengenfeld und CTA bewusst in einer gemeinsamen Zeile. */
.cn-purchase form > .cn-quantity {
    grid-column: 1 !important;
    align-self: end;
}

.cn-purchase form {
    grid-template-columns: 96px minmax(0, 1fr);
    align-items: end;
}

/* Die generische Full-Width-Regel darf den Submit-Wrapper nicht mehr erfassen. */
.cn-purchase form > :not(.cn-quantity):not(.cn-purchase__submit):not(:has(button[type="submit"])):not(:has(input[type="submit"])) {
    grid-column: 1 / -1;
}

/* Falls der Submit doch direkt die bekannte Klasse trägt, bleibt er ebenfalls korrekt. */
.cn-purchase__submit {
    grid-column: 2 !important;
    align-self: end;
    width: 100%;
    min-height: 50px;
    height: 50px;
}

@media (max-width: 767.98px) {
    .cn-purchase form {
        grid-template-columns: 86px minmax(0, 1fr);
    }

    .cn-qty {
        width: 86px;
    }
}

@media (max-width: 419.98px) {
    .cn-purchase form {
        grid-template-columns: 82px minmax(0, 1fr);
        gap: 8px;
    }

    .cn-qty {
        width: 82px;
    }

    .cn-purchase form > :has(button[type="submit"]) button[type="submit"],
    .cn-purchase__submit {
        font-size: .75rem;
        padding-inline: 12px;
    }
}

/* CARDNEXT PRODUCT BUY ROW FIX:END */
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
print("OK: Mengenwahl und Warenkorb-Button wieder in einer Zeile ausgerichtet.")
