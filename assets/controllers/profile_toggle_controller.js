import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        id: Number,
        field: String,
        token: String,
        state: Boolean,
    };

    async toggle() {
        const checkbox = this.element.querySelector('input[type="checkbox"]');
        checkbox.disabled = true;
        this.element.classList.add('toggle-loading');

        const url = `/profiles/${this.idValue}/toggle-${this.fieldValue}`;
        const body = new FormData();
        body.append('_token', this.tokenValue);

        try {
            const response = await fetch(url, { method: 'POST', body });
            if (!response.ok) throw new Error('Request failed');

            const data = await response.json();
            const newState = data[this.fieldValue];

            this.stateValue = newState;
            checkbox.checked = newState;
        } catch (error) {
            checkbox.checked = this.stateValue;
            console.error('Toggle failed:', error);
        } finally {
            checkbox.disabled = false;
            this.element.classList.remove('toggle-loading');
        }
    }
}
