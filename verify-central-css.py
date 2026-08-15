#!/usr/bin/env python3
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parent
css_path = root / 'assets/shop/styles/cardnext.css'
entry_path = root / 'assets/shop/entrypoint.js'

if not css_path.exists():
    sys.exit('FEHLER: cardnext.css fehlt.')

css = css_path.read_text(encoding='utf-8')
entry = entry_path.read_text(encoding='utf-8')

refs = set(re.findall(r'var\((--cn-[\w-]+)', css))
defs = set(re.findall(r'(--cn-[\w-]+)\s*:', css))
missing = sorted(refs - defs)

errors = []
if css.count('{') != css.count('}'):
    errors.append('CSS-Klammern sind nicht ausgeglichen.')
if missing:
    errors.append('Nicht definierte --cn-* Variablen: ' + ', '.join(missing))
if 'var(--cardnext-' in css:
    errors.append('Alte --cardnext-* Variablen werden noch referenziert.')
if 'cardnext.scss' in entry:
    errors.append('entrypoint.js referenziert noch cardnext.scss.')
if "./styles/cardnext.css" not in entry:
    errors.append('entrypoint.js lädt cardnext.css nicht.')

if errors:
    print('\n'.join('FEHLER: ' + error for error in errors))
    sys.exit(1)

print('OK')
print(f'Definierte Design-Tokens: {len(defs)}')
print(f'Referenzierte Design-Tokens: {len(refs)}')
print('Nicht definierte Design-Tokens: 0')
print('Alte --cardnext-* Referenzen: 0')
print('Entrypoint lädt ausschließlich cardnext.css als Cardnext-Stylesheet.')
