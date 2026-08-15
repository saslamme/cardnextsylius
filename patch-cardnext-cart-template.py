from pathlib import Path
import re
import sys

path = Path("templates/shop/layout/header/cart.html.twig")

if not path.exists():
    sys.exit("FEHLER: templates/shop/layout/header/cart.html.twig wurde nicht gefunden.")

content = path.read_text(encoding="utf-8")

badge_pattern = re.compile(
    r'(?P<badge><span\s+class="cardnext-cart-badge"[^>]*>.*?</span>)',
    re.S,
)

badge_match = badge_pattern.search(content)
if not badge_match:
    sys.exit(
        "ABBRUCH: .cardnext-cart-badge wurde im aktuellen Cart-Template nicht gefunden. "
        "Es wurde nichts verändert."
    )

badge = badge_match.group("badge")

icon_pattern = re.compile(
    r'(<span\s+class="cn-shop-header__action-icon"[^>]*>)(.*?)(</span>)',
    re.S,
)

icon_match = icon_pattern.search(content)
if not icon_match:
    sys.exit(
        "ABBRUCH: .cn-shop-header__action-icon wurde nicht gefunden. "
        "Es wurde nichts verändert."
    )

# Bereits korrekt verschachtelt?
if badge_match.start() > icon_match.start() and badge_match.end() < icon_match.end():
    print("OK: Badge liegt bereits im Icon-Wrapper.")
    raise SystemExit(0)

# Badge zunächst an der bisherigen Stelle entfernen.
content = content[:badge_match.start()] + content[badge_match.end():]

# Icon nach dem Entfernen neu suchen.
icon_match = icon_pattern.search(content)
if not icon_match:
    sys.exit("ABBRUCH: Icon-Wrapper konnte nach dem Entfernen nicht erneut gefunden werden.")

replacement = (
    icon_match.group(1)
    + icon_match.group(2).rstrip()
    + "\n"
    + badge
    + "\n"
    + icon_match.group(3)
)

content = content[:icon_match.start()] + replacement + content[icon_match.end():]

path.write_text(content, encoding="utf-8")
print("OK: vorhandene .cardnext-cart-badge direkt in den Icon-Wrapper verschoben.")
