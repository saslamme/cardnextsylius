import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['locale'];

  channelChanged(event) {
    const option = event.target.selectedOptions[0];
    const locales = JSON.parse(option?.dataset.locales || '[]');
    const previous = this.localeTarget.value;
    const allowed = locales.map(({ code }) => code);
    const selected = allowed.includes(previous) ? previous : (option?.dataset.defaultLocale || allowed[0] || '');

    this.localeTarget.replaceChildren(...locales.map(({ code, label }) => {
      const localeOption = document.createElement('option');
      localeOption.value = code;
      localeOption.textContent = label;
      localeOption.selected = code === selected;
      return localeOption;
    }));
    this.localeTarget.dispatchEvent(new Event('change', { bubbles: true }));
  }
}
