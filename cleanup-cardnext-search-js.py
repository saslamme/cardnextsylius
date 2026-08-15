from pathlib import Path
import re
import sys

path = Path("assets/shop/cardnext.js")
if not path.exists():
    sys.exit("FEHLER: assets/shop/cardnext.js wurde nicht gefunden.")

js = path.read_text(encoding="utf-8")

# Remove the unsuccessful temporary fix from the previous step.
js = re.sub(
    r'\n*// CARDNEXT SMART HEADER SEARCH DIRECT:START.*?// CARDNEXT SMART HEADER SEARCH DIRECT:END\n*',
    '\n\n',
    js,
    flags=re.S,
)

# Remove the old "open a second search panel" implementation.
old_panel_pattern = re.compile(
    r'\n*const searchOpenButton = document\.querySelector\(\'\[data-cardnext-search-open\]\'\);'
    r'.*?searchCloseButton\?\.addEventListener\(\'click\', closeProductSearch\);\n*',
    flags=re.S,
)

js, removed = old_panel_pattern.subn('\n\n', js, count=1)

# Preserve the Escape handler for mobile navigation, but remove only the dead
# desktop-search-panel part from it.
js = re.sub(
    r'\n\s*if \(searchPanel && !searchPanel\.hidden\) \{\s*'
    r'closeProductSearch\(\);\s*'
    r'searchOpenButton\?\.focus\(\);\s*'
    r'\}',
    '',
    js,
    flags=re.S,
)

# Older direct-search implementation is obsolete now that the header itself
# is the real GET form.
marker = "// Cardnext direct header search"
if marker in js:
    before, after = js.split(marker, 1)
    # That old block was appended as a terminal block by its installer.
    # Remove it only up to the smart-search block if present; otherwise to EOF.
    smart_marker = "// Cardnext smart search suggestions"
    if smart_marker in after:
        after = smart_marker + after.split(smart_marker, 1)[1]
        js = before.rstrip() + "\n\n" + after
    else:
        js = before.rstrip() + "\n"

# The rich smart-search implementation should already exist from the search
# packages. Do not duplicate it. Fail loudly if it is missing.
if "// Cardnext smart search suggestions" not in js:
    sys.exit(
        "ABBRUCH: Die bestehende Smart-Search-Suggestions-Logik wurde in "
        "assets/shop/cardnext.js nicht gefunden. Bitte nichts weiter installieren."
    )

path.write_text(js.rstrip() + "\n", encoding="utf-8")

if removed:
    print("OK: alte Search-Panel-Open/Close-Logik aus cardnext.js entfernt.")
else:
    print("INFO: alte Search-Panel-JS-Logik war bereits nicht mehr vorhanden.")

print("OK: bestehende Smart-Search-Suggestions bleiben erhalten.")
