import { Controller } from '@hotwired/stimulus';

class RequestError extends Error {
  constructor(message, status) {
    super(message);
    this.name = 'RequestError';
    this.status = status;
  }
}

export default class extends Controller {
  static targets = [
    'search',
    'results',
    'products',
    'count',
    'status',
    'sortStatus',
    'empty',
    'resultTemplate',
    'productTemplate',
  ];

  static values = {
    expertId: Number,
    searchUrl: String,
    addUrl: String,
    removeUrl: String,
    sortUrl: String,
    csrfToken: String,
  };

  connect() {
    this.searchTimer = null;
    this.searchRequest = null;
    this.draggedItem = null;
    this.orderBeforeDrag = [];
    this.updateAssortmentState();
  }

  disconnect() {
    window.clearTimeout(this.searchTimer);
    this.searchRequest?.abort();
  }

  scheduleSearch() {
    window.clearTimeout(this.searchTimer);
    this.searchRequest?.abort();

    const query = this.searchTarget.value.trim();
    if (query.length < 2) {
      this.resultsTarget.replaceChildren();
      return;
    }

    this.renderResultsMessage('Suche läuft …', 'text-secondary');
    this.searchTimer = window.setTimeout(() => this.performSearch(query), 300);
  }

  async performSearch(query) {
    this.searchRequest = new AbortController();
    const url = new URL(this.searchUrlValue, window.location.origin);
    url.searchParams.set('q', query);
    url.searchParams.set('expert', String(this.expertIdValue));

    try {
      const products = await this.request(url.toString(), { signal: this.searchRequest.signal });
      this.renderSearchResults(products);
    } catch (error) {
      if (error.name === 'AbortError') return;
      console.error('Product expert assortment search failed.', error);
      this.renderResultsMessage(
        'Die Produktsuche konnte nicht geladen werden. Bitte versuchen Sie es erneut.',
        'text-danger',
      );
    } finally {
      this.searchRequest = null;
    }
  }

  async add(event) {
    const button = event.currentTarget;
    const result = button.closest('[data-search-result]');
    const productId = result?.dataset.productId;
    if (!result || !productId || button.disabled) return;

    const label = button.querySelector('[data-button-label]');
    button.disabled = true;
    if (label) label.textContent = 'Wird hinzugefügt …';

    try {
      const body = new URLSearchParams({ product: productId });
      const response = await this.request(this.addUrlValue, { method: 'POST', body });
      this.productsTarget.append(this.buildProductRow(response.itemId, response.product));
      this.setResultSelected(result, true);
      this.updateAssortmentState();
      this.showFeedback('Produkt wurde hinzugefügt.', 'success');
    } catch (error) {
      console.error('Adding product to assortment failed.', error);
      this.showFeedback(error.message, 'danger');
      button.disabled = false;
      if (label) label.textContent = 'Hinzufügen';
    }
  }

  async remove(event) {
    const button = event.target.closest('[data-remove]');
    if (!button) return;

    const row = button.closest('[data-assortment-item]');
    if (!row || button.disabled) return;

    button.disabled = true;
    const originalContent = button.innerHTML;
    button.textContent = 'Wird entfernt …';

    try {
      const url = this.removeUrlValue.replace('__ITEM__', encodeURIComponent(row.dataset.itemId));
      const response = await this.request(url, { method: 'POST', body: new URLSearchParams() });
      row.remove();
      this.markSearchProductSelected(response.productId || row.dataset.productId, false);
      this.updateAssortmentState();
      this.showFeedback('Produkt wurde entfernt.', 'success');
    } catch (error) {
      console.error('Removing product from assortment failed.', error);
      this.showFeedback(error.message, 'danger');
      button.disabled = false;
      button.innerHTML = originalContent;
    }
  }

  enableDrag(event) {
    const row = event.currentTarget.closest('[data-assortment-item]');
    if (row) {
      row.draggable = true;
      this.dragHandleItem = row;
    }
  }

  dragStart(event) {
    const row = event.target.closest('[data-assortment-item]');
    if (!row || row !== this.dragHandleItem || row.draggable !== true) {
      event.preventDefault();
      return;
    }

    this.draggedItem = row;
    this.orderBeforeDrag = this.productRows().map((item) => item.dataset.itemId);
    row.classList.add('opacity-50');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', row.dataset.itemId);
  }

  dragOver(event) {
    if (!this.draggedItem) return;
    event.preventDefault();

    const over = event.target.closest('[data-assortment-item]');
    if (!over || over === this.draggedItem) return;

    const insertAfter = event.clientY > over.getBoundingClientRect().top + over.offsetHeight / 2;
    this.productsTarget.insertBefore(this.draggedItem, insertAfter ? over.nextSibling : over);
  }

  async drop(event) {
    if (!this.draggedItem) return;
    event.preventDefault();

    const newOrder = this.productRows().map((item) => item.dataset.itemId);
    if (newOrder.join(',') === this.orderBeforeDrag.join(',')) return;

    this.sortStatusTarget.textContent = 'Reihenfolge wird gespeichert …';
    try {
      await this.request(this.sortUrlValue, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items: newOrder }),
      });
      this.sortStatusTarget.textContent = 'Reihenfolge gespeichert';
    } catch (error) {
      console.error('Sorting product expert assortment failed.', error);
      this.restoreOrder(this.orderBeforeDrag);
      this.sortStatusTarget.textContent = 'Reihenfolge konnte nicht gespeichert werden.';
      this.showFeedback(error.message, 'danger');
    }
  }

  dragEnd() {
    if (this.draggedItem) {
      this.draggedItem.classList.remove('opacity-50');
      this.draggedItem.draggable = false;
    }
    this.draggedItem = null;
    this.dragHandleItem = null;
  }

  async request(url, options = {}) {
    const response = await fetch(url, {
      ...options,
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': this.csrfTokenValue,
        ...(options.headers || {}),
      },
    });

    if (!response.ok) {
      const messages = {
        401: 'Keine Berechtigung oder Sitzung abgelaufen.',
        403: 'Keine Berechtigung oder Sitzung abgelaufen.',
        404: 'Produkt oder Sortiment wurde nicht gefunden.',
        409: 'Produkt ist bereits im Sortiment.',
        500: 'Es ist ein Serverfehler aufgetreten.',
      };
      const message = messages[response.status]
        || (response.status >= 500 ? messages[500] : 'Die Aktion konnte nicht ausgeführt werden.');
      throw new RequestError(message, response.status);
    }

    return response.json();
  }

  renderSearchResults(products) {
    if (!Array.isArray(products) || products.length === 0) {
      this.renderResultsMessage('Keine passenden Produkte gefunden.', 'text-secondary');
      return;
    }

    const list = document.createElement('div');
    list.className = 'list-group';
    products.forEach((product) => list.append(this.buildResultRow(product)));
    this.resultsTarget.replaceChildren(list);
  }

  buildResultRow(product) {
    const row = this.resultTemplateTarget.content.firstElementChild.cloneNode(true);
    row.dataset.productId = String(product.id);
    row.querySelector('[data-result-name]').textContent = product.name || product.code;
    row.querySelector('[data-result-meta]').textContent = this.productMeta(product);
    row.querySelector('[data-result-media]').replaceWith(this.buildMedia(product));
    this.setResultSelected(row, Boolean(product.alreadySelected));
    return row;
  }

  buildProductRow(itemId, product) {
    const row = this.productTemplateTarget.content.firstElementChild.cloneNode(true);
    row.dataset.itemId = String(itemId);
    row.dataset.productId = String(product.id);
    row.querySelector('[data-product-name]').textContent = product.name || product.code;
    row.querySelector('[data-product-meta]').textContent = this.productMeta(product);
    row.querySelector('[data-product-media]').replaceWith(this.buildMedia(product));
    row.querySelector('[data-drag-handle]').setAttribute('aria-label', `${product.name || product.code} verschieben`);
    return row;
  }

  buildMedia(product) {
    if (product.image) {
      const image = document.createElement('img');
      image.className = 'avatar avatar-md object-fit-contain bg-white';
      image.src = product.image;
      image.alt = '';
      image.loading = 'lazy';
      return image;
    }

    const placeholder = document.createElement('span');
    placeholder.className = 'avatar avatar-md bg-secondary-lt text-secondary';
    placeholder.setAttribute('aria-hidden', 'true');
    placeholder.textContent = '–';
    return placeholder;
  }

  productMeta(product) {
    return `${product.manufacturer || 'Hersteller nicht angegeben'} · ${product.code || 'ohne Artikelnummer'}`;
  }

  setResultSelected(row, selected) {
    const button = row.querySelector('[data-add]');
    const label = button?.querySelector('[data-button-label]');
    if (!button || !label) return;
    button.disabled = selected;
    button.classList.toggle('btn-primary', !selected);
    button.classList.toggle('btn-outline-secondary', selected);
    label.textContent = selected ? 'Bereits enthalten' : 'Hinzufügen';
  }

  markSearchProductSelected(productId, selected) {
    const result = this.resultsTarget.querySelector(`[data-search-result][data-product-id="${CSS.escape(String(productId))}"]`);
    if (result) this.setResultSelected(result, selected);
  }

  renderResultsMessage(message, className) {
    const element = document.createElement('div');
    element.className = `${className} py-3 text-center`;
    element.textContent = message;
    this.resultsTarget.replaceChildren(element);
  }

  showFeedback(message, type) {
    this.statusTarget.className = `alert alert-${type} mb-4`;
    this.statusTarget.textContent = message;
    window.clearTimeout(this.feedbackTimer);
    this.feedbackTimer = window.setTimeout(() => {
      this.statusTarget.className = 'd-none';
      this.statusTarget.textContent = '';
    }, 4000);
  }

  updateAssortmentState() {
    const count = this.productRows().length;
    this.countTarget.textContent = `${count} ${count === 1 ? 'Produkt' : 'Produkte'}`;
    this.emptyTarget.classList.toggle('d-none', count > 0);
  }

  productRows() {
    return [...this.productsTarget.querySelectorAll('[data-assortment-item]')];
  }

  restoreOrder(ids) {
    const rows = new Map(this.productRows().map((row) => [row.dataset.itemId, row]));
    ids.forEach((id) => {
      if (rows.has(id)) this.productsTarget.append(rows.get(id));
    });
  }
}
