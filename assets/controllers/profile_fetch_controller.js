import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    static values = {
        url: String,
        token: String,
        identifier: String,
    };

    async fetch() {
        const modal = document.getElementById('fetchModal');
        const bsModal = Modal.getOrCreateInstance(modal);

        const body = modal.querySelector('.modal-body');
        body.innerHTML = this._renderLoading();
        modal.querySelector('.modal-footer').classList.add('d-none');
        bsModal.show();

        const formData = new FormData();
        formData.append('_token', this.tokenValue);

        try {
            const response = await window.fetch(this.urlValue, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            const data = await response.json();

            if (response.ok && data.success) {
                body.innerHTML = this._renderSuccess(data);
                this._updateFetchStatus(data);
            } else {
                body.innerHTML = this._renderError(data.error || 'Unbekannter Fehler');
            }
        } catch (error) {
            body.innerHTML = this._renderError(error.message);
        }

        modal.querySelector('.modal-footer').classList.remove('d-none');
    }

    _renderLoading() {
        return `
            <div class="text-center py-4">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Laden...</span>
                </div>
                <p class="mb-0 text-muted">Lade Einträge für <strong>${this._escapeHtml(this.identifierValue)}</strong>…</p>
            </div>
        `;
    }

    _renderSuccess(data) {
        return `
            <div class="py-2">
                <div class="alert alert-success mb-3">
                    <i class="fas fa-check-circle me-1"></i>Import abgeschlossen
                </div>
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <th style="width: 160px;">Fetcher</th>
                        <td><code>${this._escapeHtml(data.fetcher)}</code></td>
                    </tr>
                    <tr>
                        <th>Einträge geladen</th>
                        <td><span class="badge text-bg-primary">${data.fetched}</span></td>
                    </tr>
                    <tr>
                        <th>Neu gespeichert</th>
                        <td><span class="badge text-bg-success">${data.new}</span></td>
                    </tr>
                    <tr>
                        <th>Duplikate</th>
                        <td><span class="badge text-bg-secondary">${data.duplicates}</span></td>
                    </tr>
                </table>
            </div>
        `;
    }

    _renderError(message) {
        return `
            <div class="py-2">
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-exclamation-triangle me-1"></i>${this._escapeHtml(message)}
                </div>
            </div>
        `;
    }

    _updateFetchStatus(data) {
        const row = this.element.closest('tr');

        if (row) {
            const cell = row.querySelector('[data-fetch-status]');
            if (cell) {
                cell.innerHTML = `
                    <span class="text-success" title="${this._escapeHtml(data.lastFetchDateTimeFull)}">
                        <i class="fas fa-check me-1"></i>${this._escapeHtml(data.lastFetchDateTime)}
                    </span>
                `;
            }
        } else {
            const card = document.querySelector('[data-fetch-status]');
            if (card) {
                card.innerHTML = `
                    <div class="mb-3">
                        <small class="text-muted d-block">Letzter erfolgreicher Fetch</small>
                        <span class="text-success">
                            <i class="fas fa-check me-1"></i>${this._escapeHtml(data.lastFetchDateTimeFull)}
                        </span>
                    </div>
                `;
            }
        }
    }

    _escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
