import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this._timeout = setTimeout(() => {
            this.element.classList.remove('show');
            this.element.addEventListener('transitionend', () => {
                this.element.remove();
            });
        }, 5000);
    }

    disconnect() {
        if (this._timeout) {
            clearTimeout(this._timeout);
        }
    }
}
