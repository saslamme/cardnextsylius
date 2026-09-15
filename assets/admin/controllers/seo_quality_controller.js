import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['item'];
  static values = { url: String, pageId: Number };

  connect() {
    this.element.addEventListener('input', () => this.changed());
    this.element.addEventListener('change', () => this.changed());
    this.changed();
  }

  changed() {
    this.updateEditorialChecks();
    window.clearTimeout(this.timer);
    this.timer = window.setTimeout(() => this.updateDatabaseChecks(), 400);
  }

  field(name) { return this.element.querySelector(`[name$="[${name}]"]`); }
  value(name) { return this.field(name)?.value.trim() || ''; }
  set(key, status, text) {
    const item = this.itemTargets.find((element) => element.dataset.check === key);
    if (!item) return;
    item.className = status === 'ok' ? 'text-success mb-2' : status === 'error' ? 'text-danger mb-2' : 'text-warning mb-2';
    item.textContent = `${status === 'ok' ? '✓' : status === 'error' ? '✕' : '⚠'} ${text}`;
  }

  updateEditorialChecks() {
    const titleLength = [...this.value('metaTitle')].length;
    const descriptionLength = [...this.value('metaDescription')].length;
    this.set('title', titleLength >= 30 && titleLength <= 60 ? 'ok' : 'warning', `Meta Title: ${titleLength} Zeichen${titleLength < 30 ? ' – eher kurz' : titleLength > 60 ? ' – eher lang' : ''}`);
    this.set('description', descriptionLength >= 120 && descriptionLength <= 160 ? 'ok' : 'warning', descriptionLength === 0 ? 'Meta Description fehlt' : `Meta Description: ${descriptionLength} Zeichen${descriptionLength < 120 ? ' – eher kurz' : descriptionLength > 160 ? ' – eher lang' : ''}`);
    this.set('h1', this.value('h1') ? 'ok' : 'error', this.value('h1') ? 'H1 vorhanden' : 'H1 fehlt');
    this.set('index', this.field('robotsIndex')?.checked ? 'ok' : 'warning', this.field('robotsIndex')?.checked ? 'Seite wird indexiert' : 'Seite steht auf Noindex');
    this.set('follow', this.field('robotsFollow')?.checked ? 'ok' : 'warning', this.field('robotsFollow')?.checked ? 'Links werden verfolgt' : 'Links stehen auf Nofollow');
    this.set('canonical', this.value('canonicalUrl') ? 'warning' : 'ok', this.value('canonicalUrl') ? `Canonical Override aktiv: ${this.value('canonicalUrl')}` : 'Self-Canonical wird automatisch verwendet');
    this.set('content', this.value('topContent') || this.value('bottomContent') ? 'ok' : 'warning', this.value('topContent') || this.value('bottomContent') ? 'Redaktioneller Text vorhanden' : 'Die Landingpage enthält noch keinen redaktionellen Text');
  }

  async updateDatabaseChecks() {
    const channel = this.value('channel'); const locale = this.value('locale');
    if (!channel || !locale || !this.value('path')) return;
    const query = new URLSearchParams({ channel, locale, path: this.value('path'), metaTitle: this.value('metaTitle'), h1: this.value('h1') });
    if (this.hasPageIdValue) query.set('id', this.pageIdValue);
    const response = await fetch(`${this.urlValue}?${query}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) return;
    const result = await response.json();
    this.set('path', result.pathValid && result.pathUnique ? 'ok' : 'error', !result.pathValid ? 'URL ist technisch nicht als Landingpage verwendbar' : result.pathUnique ? 'URL eindeutig und technisch erreichbar' : 'Dieser URL-Pfad wird für diesen Verkaufskanal und diese Sprache bereits verwendet');
    this.set('titleDuplicate', result.metaTitleUnique ? 'ok' : 'warning', result.metaTitleUnique ? 'Meta Title ist eindeutig' : 'Dieser Meta Title wird bereits von einer anderen SEO-Landingpage verwendet');
    this.set('h1Duplicate', result.h1Unique ? 'ok' : 'warning', result.h1Unique ? 'H1 ist eindeutig' : 'Diese H1 wird bereits auf einer anderen SEO-Landingpage verwendet');
  }
}
