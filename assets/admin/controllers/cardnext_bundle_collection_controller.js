import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['empty'];

  connect() {
    this.refresh();
  }

  add(event) {
    const button = event.currentTarget;
    let holder = button.previousElementSibling;
    while (holder && !holder.matches('[data-collection-holder]')) holder = holder.previousElementSibling;
    if (!holder || !holder.matches('[data-collection-holder]')) return;

    const index = Number(holder.dataset.index || holder.children.length);
    const placeholder = holder.dataset.placeholder;
    holder.insertAdjacentHTML('beforeend', holder.dataset.prototype.split(placeholder).join(String(index)));
    holder.dataset.index = String(index + 1);
    this.refresh();
  }

  remove(event) {
    event.currentTarget.closest('[data-collection-entry]')?.remove();
    this.refresh();
  }

  discountChanged(event) {
    const entry = event.currentTarget.closest('[data-collection-entry]');
    if (entry) entry.dataset.discountType = event.currentTarget.value;
    this.refresh();
  }

  refresh() {
    this.element.querySelectorAll('[data-discount-type]').forEach((entry) => {
      entry.querySelectorAll('[data-discount-field]').forEach((field) => {
        field.classList.toggle('d-none', field.dataset.discountField !== entry.dataset.discountType);
      });
    });
    if (this.hasEmptyTarget) {
      const root = this.element.querySelector('[data-placeholder="__bundle__"]');
      this.emptyTarget.classList.toggle('d-none', Boolean(root?.querySelector(':scope > [data-collection-entry]')));
    }
  }
}
