from pathlib import Path
import re
import sys

path = Path("assets/shop/styles/cardnext.css")

if not path.exists():
    sys.exit("FEHLER: assets/shop/styles/cardnext.css wurde nicht gefunden.")

css = path.read_text(encoding="utf-8")

start = "/* CARDNEXT CONTACT SUBMIT:START */"
end = "/* CARDNEXT CONTACT SUBMIT:END */"

block = r"""
/* CARDNEXT CONTACT SUBMIT:START */

.cn-contact-form__submit {
    width: 100%;
    min-height: 50px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 13px 20px;
    margin: 0;
    border: 1px solid var(--cn-primary, #f04b23);
    border-radius: var(--cn-radius, 3px);
    background: var(--cn-primary, #f04b23);
    color: #fff;
    box-shadow: none;
    font: inherit;
    font-weight: 750;
    line-height: 1.2;
    text-decoration: none;
    cursor: pointer;
    transition:
        background-color .18s ease,
        border-color .18s ease,
        color .18s ease;
}

.cn-contact-form__submit:hover,
.cn-contact-form__submit:focus-visible {
    border-color: var(--cn-primary-hover, #d93e19);
    background: var(--cn-primary-hover, #d93e19);
    color: #fff;
}

.cn-contact-form__submit:focus-visible {
    outline: 3px solid rgba(240, 75, 35, .22);
    outline-offset: 2px;
}

.cn-contact-form__submit:disabled,
.cn-contact-form__submit[disabled] {
    opacity: .55;
    cursor: not-allowed;
}

.cn-contact-form__submit > span {
    color: inherit;
}

/* CARDNEXT CONTACT SUBMIT:END */
""".strip()

pattern = re.compile(
    re.escape(start) + r".*?" + re.escape(end),
    flags=re.S,
)

if pattern.search(css):
    css = pattern.sub(block, css)
else:
    css = css.rstrip() + "\n\n" + block + "\n"

path.write_text(css, encoding="utf-8")
print("OK: Kontakt-Button wieder auf Cardnext-Orange gesetzt.")
