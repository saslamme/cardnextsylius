import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button'];

    static values = {
        url: String,
        token: String,
    };

    async submit(event) {
        event.preventDefault();

        const button = this.buttonTarget;
        button.disabled = true;

        try {
            const body = new URLSearchParams();
            body.set('_token', this.tokenValue);

            const response = await fetch(this.urlValue, {
                method: 'POST',
                credentials: 'same-origin',
                redirect: 'follow',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            if (response.redirected && response.url) {
                window.location.assign(response.url);
                return;
            }

            window.location.reload();
        } catch (error) {
            console.error('Cardnext: Datenprofil konnte nicht angewendet werden.', error);
            alert('Das Datenprofil konnte nicht angewendet werden.');
            button.disabled = false;
        }
    }
}
