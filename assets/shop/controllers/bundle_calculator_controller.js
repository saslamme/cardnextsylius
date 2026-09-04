import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['item', 'subtotal', 'total', 'saving', 'notice', 'bundleQuantity'];
    static values = { discountType: String, discountValue: Number };

    connect() { this.recalculate(); }

    recalculate() {
        const multiplier = Math.max(1, Number.parseInt(this.bundleQuantityTarget.value, 10) || 1);
        const selected = this.itemTargets.filter((item) => item.checked);
        const subtotal = selected.reduce((sum, item) => sum + Number(item.dataset.price) * Number(item.dataset.quantity) * multiplier, 0);
        const complete = selected.length === this.itemTargets.length;
        let saving = 0;
        if (complete && this.discountTypeValue === 'FIXED') saving = this.discountValueValue * multiplier;
        if (complete && this.discountTypeValue === 'PERCENT') saving = Math.round(subtotal * this.discountValueValue / 10000);
        saving = Math.min(subtotal, saving);
        this.subtotalTarget.textContent = this.money(subtotal);
        this.totalTarget.textContent = this.money(subtotal - saving);
        this.savingTarget.textContent = this.money(saving);
        this.noticeTarget.classList.toggle('d-none', complete || this.discountTypeValue === 'NONE');
    }

    money(value) { return new Intl.NumberFormat(document.documentElement.lang || 'de-DE', { style: 'currency', currency: document.body.dataset.currency || 'EUR' }).format(value / 100); }
}
