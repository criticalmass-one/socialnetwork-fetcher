import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        id: Number,
        token: String,
    };

    static targets = ['hiddenBtn', 'deletedBtn', 'hiddenLabel', 'deletedLabel'];

    async toggleHidden() {
        await this._toggle('toggle-hidden', 'hidden');
    }

    async toggleDeleted() {
        await this._toggle('toggle-deleted', 'deleted');
    }

    async _toggle(action, field) {
        const url = `/items/${this.idValue}/${action}`;
        const body = new FormData();
        body.append('_token', this.tokenValue);

        try {
            const response = await fetch(url, { method: 'POST', body });
            if (!response.ok) throw new Error('Request failed');

            const data = await response.json();
            this._updateUI(field, data[field]);
        } catch (error) {
            console.error('Toggle failed:', error);
        }
    }

    _updateUI(field, isActive) {
        if (field === 'hidden') {
            this._updateButton(this.hiddenBtnTargets, isActive, 'btn-warning', 'btn-outline-warning');
            this.hiddenLabelTargets.forEach(el => {
                el.textContent = isActive ? 'Versteckt' : 'Nicht versteckt';
            });
        } else {
            this._updateButton(this.deletedBtnTargets, isActive, 'btn-danger', 'btn-outline-danger');
            this.deletedLabelTargets.forEach(el => {
                el.textContent = isActive ? 'Gelöscht' : 'Nicht gelöscht';
            });
        }

        const row = this.element.closest('tr');
        if (row) {
            row.classList.toggle('table-row-hidden', field === 'hidden' && isActive);
            row.classList.toggle('table-row-deleted', field === 'deleted' && isActive);
        }
    }

    _updateButton(targets, isActive, activeClass, inactiveClass) {
        targets.forEach(btn => {
            btn.classList.toggle(activeClass, isActive);
            btn.classList.toggle(inactiveClass, !isActive);
        });
    }
}
