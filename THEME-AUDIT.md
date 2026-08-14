# Cardnext Theme Audit – v2

Geprüft wurden die vollständig hochgeladenen Ordner `assets/` und `templates/`.

## Behobene Punkte

1. Breadcrumbs vereinheitlicht
   - Kategorie- und Produktseite verwenden jetzt dieselbe `cardnext-breadcrumbs`-Komponente.
   - identische Höhe, Hintergrundfarbe, Typografie und Trennlinien.
   - Produkt-Breadcrumb enthält nun ebenfalls die übergeordnete Kategorie `Produkte`.

2. Container vereinheitlicht
   - `cn-category__container` und `cn-product__container` wurden aus den Twig-Dateien entfernt.
   - Seitenbreiten laufen jetzt über den globalen `cardnext-container`.

3. Footer-Logo ausgerichtet
   - die sichtbare Logografik beginnt nun bündig mit dem Footer-Inhalt.
   - der bisherige linke Innenabstand des weißen Logo-Trägers wurde entfernt.

4. Footer-Hover korrigiert
   - Footer-Links wechseln nicht mehr auf Weiß.
   - Hover/Focus verwendet jetzt Cardnext-Orange und bleibt auf hellem wie dunklem Footer sichtbar.

5. Typografie vergrößert
   - Desktop-Basis auf 17 px angehoben.
   - mobile Basis bleibt bei 16 px.
   - besonders kleine Filter-, Meta-, SKU-, Download-, Footer- und Produkttexte wurden zusätzlich angehoben.

6. Downloads wieder mit Markenakzent
   - orange linke Akzentlinie.
   - hellorange Dokument-Icon-Fläche.
   - orange Pfeilfläche.
   - dezenter farbiger Hover.

7. CSS-Struktur
   - veraltete Komponenten-Container/Breadcrumb-Regeln entfernt.
   - keine zusätzliche Hotfix-Datei angelegt.
   - bestehende Sylius-/Twig-Funktionalität wurde nicht verändert.

## Bewusst nicht automatisch geändert

Im Header/Footer existieren Links wie `#downloads`, `#brands` und teilweise ältere Inhaltsanker,
für die in der hochgeladenen Startseite kein entsprechender Abschnitt vorhanden ist.
Diese Links wurden nicht auf fachlich falsche Ziele umgebogen. Dafür sollte später eine echte
Downloads-/Marken-Seite bzw. ein definierter Zielabschnitt angelegt werden.
