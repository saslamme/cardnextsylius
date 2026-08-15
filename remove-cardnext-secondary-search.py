from pathlib import Path
import sys

path = Path("templates/shop/layout/header/categories.html.twig")
if not path.exists():
    sys.exit("FEHLER: categories.html.twig wurde nicht gefunden.")

content = path.read_text(encoding="utf-8")

needle = '<form class="cn-search-form"'
form_pos = content.find(needle)

if form_pos < 0:
    print("OK: kein zweites Desktop-Suchformular mehr vorhanden.")
    raise SystemExit(0)

# The obsolete desktop search is wrapped in its own {% if rootTaxon %} block
# at the end of the categories template.
block_start = content.rfind('{% if rootTaxon %}', 0, form_pos)

if block_start < 0:
    sys.exit(
        "ABBRUCH: Zweites Suchformular gefunden, aber der zugehörige "
        "rootTaxon-Block konnte nicht sicher bestimmt werden."
    )

block_end = content.find('{% endif %}', form_pos)
if block_end < 0:
    sys.exit("ABBRUCH: Ende des alten Suchblocks wurde nicht gefunden.")

block_end += len('{% endif %}')

candidate = content[block_start:block_end]

if 'cn-search-form' not in candidate or 'data-cardnext-search-close' not in candidate:
    sys.exit(
        "ABBRUCH: Der gefundene Block sieht nicht wie das alte Desktop-Suchpanel aus."
    )

content = content[:block_start].rstrip() + "\n" + content[block_end:].lstrip("\n")

path.write_text(content, encoding="utf-8")
print("OK: zweite Desktop-Suche aus categories.html.twig entfernt.")
