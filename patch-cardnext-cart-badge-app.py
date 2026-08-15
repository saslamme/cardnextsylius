from pathlib import Path
import sys

path = Path("assets/shop/app.js")
if not path.exists():
    sys.exit("FEHLER: assets/shop/app.js wurde nicht gefunden.")

content = path.read_text(encoding="utf-8")
needle = "import './cardnext/header-cart-badge';"

if needle not in content:
    content = content.rstrip() + "\n" + needle + "\n"
    path.write_text(content, encoding="utf-8")
    print("OK: Import in assets/shop/app.js ergänzt.")
else:
    print("OK: Import bereits vorhanden.")
