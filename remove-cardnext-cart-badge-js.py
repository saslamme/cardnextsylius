from pathlib import Path
import re
import sys

path = Path("assets/shop/cardnext.js")

if not path.exists():
    sys.exit("FEHLER: assets/shop/cardnext.js wurde nicht gefunden.")

js = path.read_text(encoding="utf-8")

start = "// CARDNEXT HEADER CART BADGE:START"
end = "// CARDNEXT HEADER CART BADGE:END"

pattern = re.compile(
    r'\n*' + re.escape(start) + r'.*?' + re.escape(end) + r'\n*',
    re.S,
)

js, count = pattern.subn("\n\n", js)

path.write_text(js.rstrip() + "\n", encoding="utf-8")

if count:
    print("OK: fehlerhafte JavaScript-DOM-Manipulation vollständig entfernt.")
else:
    print("INFO: alter JavaScript-Badge-Block war bereits nicht mehr vorhanden.")
