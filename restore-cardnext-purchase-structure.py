from pathlib import Path
import re
import sys

outer = Path("templates/bundles/SyliusShopBundle/product/show/content/info/summary/add_to_cart.html.twig")
quantity = Path("templates/bundles/SyliusShopBundle/product/show/content/info/summary/add_to_cart/quantity.html.twig")
submit = Path("templates/bundles/SyliusShopBundle/product/show/content/info/summary/add_to_cart/submit.html.twig")
css_path = Path("assets/shop/styles/cardnext.css")

if not outer.exists():
    sys.exit("FEHLER: Add-to-Cart-Template wurde nicht gefunden.")

content = outer.read_text(encoding="utf-8")

old_root = '<div class="position-relative" {{ attributes }}>'
new_root = '<div class="cn-purchase position-relative" {{ attributes }}>'

if old_root in content:
    content = content.replace(old_root, new_root, 1)
elif new_root not in content:
    sys.exit(
        "ABBRUCH: Der erwartete Root-Wrapper wurde nicht gefunden. "
        "Es wurde nichts am Haupttemplate verändert."
    )

outer.write_text(content, encoding="utf-8")
print("OK: cn-purchase am bestehenden Offcanvas-Template wiederhergestellt.")

quantity.parent.mkdir(parents=True, exist_ok=True)

if quantity.exists():
    q = quantity.read_text(encoding="utf-8")
    if "cn-quantity" not in q:
        q2, count = re.subn(
            r'<div\s+class="([^"]*)"',
            lambda m: '<div class="' + ('cn-quantity ' + m.group(1)).strip() + '"',
            q,
            count=1,
        )
        if count == 0:
            q2 = '<div class="cn-quantity">\n' + q.rstrip() + '\n</div>\n'
        quantity.write_text(q2, encoding="utf-8")
        print("OK: bestehendes Quantity-Template um cn-quantity ergänzt.")
    else:
        print("OK: Quantity-Template besitzt bereits cn-quantity.")
else:
    quantity.write_text(
        '<div class="cn-quantity">\n'
        '    {{ form_row(hookable_metadata.context.form.cartItem.quantity, sylius_test_form_attribute(\'quantity\')) }}\n'
        '</div>\n',
        encoding="utf-8",
    )
    print("OK: Quantity-Override mit cn-quantity angelegt.")

if submit.exists():
    s = submit.read_text(encoding="utf-8")
    if "cn-purchase__submit" not in s:
        s2, count = re.subn(
            r'<div\s+class="([^"]*)"',
            lambda m: '<div class="' + ('cn-purchase__submit ' + m.group(1)).strip() + '"',
            s,
            count=1,
        )
        if count == 0:
            s2 = '<div class="cn-purchase__submit">\n' + s.rstrip() + '\n</div>\n'
        submit.write_text(s2, encoding="utf-8")
        print("OK: bestehendes Submit-Template um cn-purchase__submit ergänzt.")
    else:
        print("OK: Submit-Template besitzt bereits cn-purchase__submit.")
else:
    submit.write_text(
        "{% import '@SyliusShop/shared/buttons.html.twig' as buttons %}\n"
        "{% set form = hookable_metadata.context.form %}\n"
        "{% set variant = form.cartItem.vars.value.variant %}\n"
        "{% set enabled = form.vars.valid and variant is not null and variant.enabled %}\n\n"
        '<div class="cn-purchase__submit">\n'
        "    {{ buttons.submit('sylius.ui.add_to_cart'|trans, 'add-to-cart-button', null, 'btn-primary', not enabled) }}\n"
        "</div>\n",
        encoding="utf-8",
    )
    print("OK: Submit-Override mit cn-purchase__submit angelegt.")

if css_path.exists():
    css = css_path.read_text(encoding="utf-8")
    start = "/* CARDNEXT PRODUCT BUY ROW FIX:START */"
    end = "/* CARDNEXT PRODUCT BUY ROW FIX:END */"
    pattern = re.compile(
        r'\n*' + re.escape(start) + r'.*?' + re.escape(end) + r'\n*',
        flags=re.S,
    )
    css2, count = pattern.subn('\n\n', css)
    if count:
        css_path.write_text(css2.rstrip() + '\n', encoding="utf-8")
        print("OK: wirkungslosen PRODUCT BUY ROW FIX aus cardnext.css entfernt.")
    else:
        print("INFO: alter PRODUCT BUY ROW FIX war nicht vorhanden.")

print("Fertig: Cardnext-Kaufbereich strukturell wiederhergestellt.")
