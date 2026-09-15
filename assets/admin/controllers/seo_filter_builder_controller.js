import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['taxon', 'definition', 'filters', 'status'];
  static values = { url: String };

  connect() {
    this.selected = this.parse(this.definitionTarget.value);
    this.previousTaxon = this.taxonTarget.value;
    this.element.addEventListener('submit', () => this.sync());
    this.load();
  }

  taxonChanged(event) {
    if (this.hasSelection() && !window.confirm('Nicht passende Filterwerte werden beim Wechsel des Basistaxons entfernt. Fortfahren?')) {
      event.target.value = this.previousTaxon;
      return;
    }
    this.previousTaxon = event.target.value;
    this.load();
  }

  async load() {
    const channel = this.element.querySelector('[name$="[channel]"]');
    if (!this.taxonTarget.value || !channel?.value) {
      this.filtersTarget.replaceChildren();
      return;
    }
    this.statusTarget.textContent = 'Filter werden geladen …';
    const locale = this.element.querySelector('[name$="[locale]"]')?.value || 'de_DE';
    const response = await fetch(`${this.urlValue}?taxon=${encodeURIComponent(this.taxonTarget.value)}&channel=${encodeURIComponent(channel.value)}&locale=${encodeURIComponent(locale)}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
      this.statusTarget.textContent = 'Filter konnten nicht geladen werden.';
      return;
    }
    this.render(await response.json());
  }

  render(data) {
    this.filtersTarget.replaceChildren();
    if (data.manufacturers.length) this.addGroup('Hersteller', 'manufacturers', data.manufacturers);
    data.facets.forEach((facet) => this.addGroup(facet.label, facet.attribute, facet.choices, facet.type));
    this.statusTarget.textContent = this.filtersTarget.children.length ? 'Mehrere Werte innerhalb eines Filters werden mit ODER, unterschiedliche Filter mit UND verknüpft.' : 'Für dieses Basistaxon sind keine Filter verfügbar. Die Landingpage kann dennoch gespeichert werden.';
    this.sync();
  }

  addGroup(label, key, choices, type = 'select') {
    const group = document.createElement('fieldset');
    group.className = 'col-md-6';
    const legend = document.createElement('legend');
    legend.className = 'form-label';
    legend.textContent = label;
    group.append(legend);
    choices.forEach((choice) => {
      const wrapper = document.createElement('label');
      wrapper.className = 'btn btn-outline-secondary btn-sm me-2 mb-2';
      const input = document.createElement('input');
      input.type = 'checkbox'; input.className = 'me-1'; input.value = choice.value; input.dataset.filterKey = key;
      input.checked = this.valuesFor(key).includes(String(choice.value));
      input.addEventListener('change', () => this.sync());
      wrapper.append(input, document.createTextNode(type === 'boolean' ? (choice.value === '1' ? 'Ja' : 'Nein') : choice.label));
      group.append(wrapper);
    });
    this.filtersTarget.append(group);
  }

  valuesFor(key) { return key === 'manufacturers' ? (this.selected.manufacturers || []) : (this.selected.attributes?.[key] || []); }
  hasSelection() { return (this.selected.manufacturers || []).length > 0 || Object.values(this.selected.attributes || {}).some((values) => values.length); }
  parse(value) { try { const parsed = JSON.parse(value); return parsed && typeof parsed === 'object' ? parsed : {}; } catch (_) { return {}; } }
  sync() {
    const definition = {};
    this.filtersTarget.querySelectorAll('input:checked').forEach((input) => {
      const key = input.dataset.filterKey;
      if (key === 'manufacturers') (definition.manufacturers ||= []).push(input.value);
      else ((definition.attributes ||= {})[key] ||= []).push(input.value);
    });
    this.selected = definition;
    this.definitionTarget.value = JSON.stringify(definition);
  }
}
