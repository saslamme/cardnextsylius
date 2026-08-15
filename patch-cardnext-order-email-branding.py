from pathlib import Path
import sys

customer = Path("templates/bundles/SyliusShopBundle/email/order_confirmation.html.twig")
internal = Path("templates/email/internal_order_notification.html.twig")

if not customer.exists():
    sys.exit("FEHLER: Kunden-Bestellmail wurde nicht gefunden.")

content = customer.read_text(encoding="utf-8")

replacements = {
    "#145ea8": "#f04b23",
    "#182638": "#05053f",
    "#68768a": "#667085",
    "#7a8797": "#7b8494",
    "#8290a1": "#8a93a3",
    "#59687a": "#667085",
    "#dfe5ec": "#e4e7ec",
    "#e7ebf0": "#e4e7ec",
    "#edf0f3": "#eef0f3",
    "#f3f5f8": "#f5f6f8",
    "#fafbfd": "#fafafa",
}

for old, new in replacements.items():
    content = content.replace(old, new)

old_logo = '<span style="font-size:26px;line-height:1;font-weight:800;letter-spacing:-1px;color:#f04b23;">card</span><span style="font-size:26px;line-height:1;font-weight:800;letter-spacing:-1px;color:#05053f;">next</span>'
new_logo = '<span style="font-family:Arial,Helvetica,sans-serif;font-size:28px;line-height:1;font-weight:800;letter-spacing:-1.4px;color:#111111;">cardne</span><span style="font-family:Arial,Helvetica,sans-serif;font-size:28px;line-height:1;font-weight:800;letter-spacing:-1.4px;color:#f04b23;">X</span><span style="font-family:Arial,Helvetica,sans-serif;font-size:28px;line-height:1;font-weight:800;letter-spacing:-1.4px;color:#111111;">t</span>'

if old_logo not in content:
    sys.exit(
        "ABBRUCH: Der erwartete bisherige Mail-Logo-Block wurde nicht gefunden. "
        "Es wurde nichts gespeichert."
    )

content = content.replace(old_logo, new_logo)
customer.write_text(content, encoding="utf-8")
print("OK: Kundenmail auf Cardnext Navy/Orange + cardneXt umgestellt.")

if internal.exists():
    internal_content = internal.read_text(encoding="utf-8")

    internal_replacements = {
        "#182638": "#05053f",
        "#68768a": "#667085",
        "#788698": "#7b8494",
        "#dfe5ec": "#e4e7ec",
        "#e7ebf0": "#e4e7ec",
        "#edf0f3": "#eef0f3",
        "#f3f5f8": "#f5f6f8",
        "#fafbfd": "#fafafa",
    }

    for old, new in internal_replacements.items():
        internal_content = internal_content.replace(old, new)

    internal.write_text(internal_content, encoding="utf-8")
    print("OK: interne Bestellmail farblich angeglichen.")
