import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    static values = {
        message: { type: String, default: 'Soll dieser Eintrag wirklich gelöscht werden?' },
    };

    connect() {
        this._modalEl = document.getElementById('deleteModal');
        if (this._modalEl) {
            this._modal = Modal.getOrCreateInstance(this._modalEl);
            this._confirmBtn = this._modalEl.querySelector('[data-action="confirm#submit"]');
        }
    }

    submit(event) {
        if (event) event.preventDefault();
        this.element.submit();
    }

    // Intercept form submission to show confirm modal
    submitStart(event) {
        event.preventDefault();
    }

    // Called when the form's submit button is clicked
    handleSubmit(event) {
        event.preventDefault();

        if (this._modal && this._modalEl) {
            const body = this._modalEl.querySelector('.modal-body');
            if (body) body.textContent = this.messageValue;

            // Replace the confirm button handler
            const newBtn = this._confirmBtn.cloneNode(true);
            this._confirmBtn.parentNode.replaceChild(newBtn, this._confirmBtn);
            this._confirmBtn = newBtn;

            newBtn.addEventListener('click', () => {
                this._modal.hide();
                this.element.submit();
            });

            this._modal.show();
        } else {
            if (confirm(this.messageValue)) {
                this.element.submit();
            }
        }
    }

    // Override the default form submit
    initialize() {
        this.element.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleSubmit(e);
        });
    }
}
