# Cardnext – Kontaktbutton Farbe wiederherstellen

Stellt das Cardnext-Design für den Kontaktformular-Button wieder her.

Der vorhandene Button:

```html
<button type="submit" class="cn-contact-form__submit">
    Nachricht senden →
</button>
```

wird wieder dargestellt mit:

- Cardnext-Orange
- weißer Schrift
- orangem/dunklerem Hover
- sauberem Focus-State
- voller Formularbreite

Es wird ausschließlich die zentrale Datei

`assets/shop/styles/cardnext.css`

angepasst.

## Installation

```bash
cd ~/public_html/cardnext

unzip -o cardnext-contact-button-color-restore.zip
chmod +x apply-cardnext-contact-button-color-restore.sh
./apply-cardnext-contact-button-color-restore.sh
```
